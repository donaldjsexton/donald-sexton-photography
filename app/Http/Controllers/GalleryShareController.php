<?php

namespace App\Http\Controllers;

use App\Models\Album;
use App\Models\Gallery;
use App\Models\Photo;
use App\Models\ShareToken;
use App\Services\Galleries\PhotoVariant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Public, token-gated delivery of client galleries and albums. The opaque
 * share token is the only credential; the global site scope keeps a token
 * resolvable only on its own tenant's domain. Originals are streamed through
 * the app so access stays gated rather than exposing the storage bucket.
 */
class GalleryShareController extends Controller
{
    private const SESSION_KEY = 'gallery_shares_unlocked';

    public function show(Request $request, string $token): View
    {
        $shareToken = $this->resolveToken($token);

        if ($this->isLocked($request, $shareToken)) {
            return view('galleries.unlock', [
                'token' => $shareToken->token,
                'error' => null,
            ]);
        }

        return view('galleries.share', [
            'token' => $shareToken->token,
            'title' => $this->titleFor($shareToken),
            'photos' => $this->photosFor($shareToken),
        ]);
    }

    public function unlock(Request $request, string $token): RedirectResponse|View
    {
        $shareToken = $this->resolveToken($token);

        if (! $shareToken->isPasswordProtected()) {
            return redirect()->route('galleries.share.show', $shareToken->token);
        }

        $validated = $request->validate(['password' => ['required', 'string']]);

        if (! Hash::check($validated['password'], (string) $shareToken->password)) {
            return view('galleries.unlock', [
                'token' => $shareToken->token,
                'error' => 'That password is not correct.',
            ]);
        }

        $request->session()->push(self::SESSION_KEY, $shareToken->token);

        return redirect()->route('galleries.share.show', $shareToken->token);
    }

    public function photo(Request $request, string $token, string $photo): StreamedResponse
    {
        $shareToken = $this->resolveUnlockedToken($request, $token);
        $model = $this->photoWithin($shareToken, $photo);

        $path = $model->pathForVariant(PhotoVariant::Web);

        return Storage::disk($model->disk ?? 's3')->response($path);
    }

    public function downloadPhoto(Request $request, string $token, string $photo): StreamedResponse
    {
        $shareToken = $this->resolveUnlockedToken($request, $token);
        $model = $this->photoWithin($shareToken, $photo);

        return Storage::disk($model->disk ?? 's3')->download($model->path, $model->downloadName());
    }

    public function downloadAll(Request $request, string $token): BinaryFileResponse
    {
        $shareToken = $this->resolveUnlockedToken($request, $token);
        $photos = $this->photosFor($shareToken);

        abort_if($photos->isEmpty(), 404);

        $archivePath = tempnam(sys_get_temp_dir(), 'gallery-zip').'.zip';
        $zip = new \ZipArchive;
        $zip->open($archivePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $usedNames = [];

        foreach ($photos as $photo) {
            $disk = Storage::disk($photo->disk ?? 's3');

            if (! $disk->exists($photo->path)) {
                continue;
            }

            $zip->addFromString($this->uniqueEntryName($photo, $usedNames), (string) $disk->get($photo->path));
        }

        $zip->close();

        $filename = str($this->titleFor($shareToken))->slug()->value().'.zip';

        return response()->download($archivePath, $filename)->deleteFileAfterSend(true);
    }

    private function resolveToken(string $token): ShareToken
    {
        $shareToken = ShareToken::query()->with('shareable')->where('token', $token)->first();

        abort_if($shareToken === null || $shareToken->isExpired() || $shareToken->shareable === null, 404);

        return $shareToken;
    }

    private function resolveUnlockedToken(Request $request, string $token): ShareToken
    {
        $shareToken = $this->resolveToken($token);

        abort_if($this->isLocked($request, $shareToken), 403);

        return $shareToken;
    }

    private function isLocked(Request $request, ShareToken $shareToken): bool
    {
        if (! $shareToken->isPasswordProtected()) {
            return false;
        }

        return ! in_array($shareToken->token, (array) $request->session()->get(self::SESSION_KEY, []), true);
    }

    /**
     * @return Collection<int, Photo>
     */
    private function photosFor(ShareToken $shareToken): Collection
    {
        $shareable = $shareToken->shareable;

        if ($shareable instanceof Album) {
            return $shareable->photos()->get();
        }

        if ($shareable instanceof Gallery) {
            return $shareable->orderedPhotos();
        }

        return new Collection;
    }

    private function photoWithin(ShareToken $shareToken, string $photoUuid): Photo
    {
        $photo = $this->photosFor($shareToken)->firstWhere('uuid', $photoUuid);

        abort_if($photo === null, 404);

        return $photo;
    }

    private function titleFor(ShareToken $shareToken): string
    {
        $shareable = $shareToken->shareable;

        return match (true) {
            $shareable instanceof Album => $shareable->name,
            $shareable instanceof Gallery => $shareable->title,
            default => 'Gallery',
        };
    }

    /**
     * @param  array<string, int>  $usedNames
     */
    private function uniqueEntryName(Photo $photo, array &$usedNames): string
    {
        $name = $photo->downloadName();

        if (! isset($usedNames[$name])) {
            $usedNames[$name] = 0;

            return $name;
        }

        $usedNames[$name]++;
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $base = pathinfo($name, PATHINFO_FILENAME);

        return $base.'-'.$usedNames[$name].($extension !== '' ? '.'.$extension : '');
    }
}
