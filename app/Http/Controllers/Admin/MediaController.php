<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MediaController extends Controller
{
    public function index(): View
    {
        return view('admin.media.index', [
            'mediaItems' => Media::query()->latest()->paginate(24),
        ]);
    }

    public function picker(Request $request): JsonResponse
    {
        $term = $request->string('q')->trim()->toString();
        $perPage = (int) $request->integer('per_page', 60);
        $perPage = max(12, min(120, $perPage));

        $query = Media::query()->latest('id');

        if ($term !== '') {
            if (ctype_digit($term)) {
                $query->where(function ($builder) use ($term): void {
                    $builder
                        ->where('id', (int) $term)
                        ->orWhereRaw('LOWER(filename) LIKE ?', ['%'.strtolower($term).'%'])
                        ->orWhereRaw('LOWER(alt_text) LIKE ?', ['%'.strtolower($term).'%']);
                });
            } else {
                $needle = '%'.strtolower($term).'%';
                $query->where(function ($builder) use ($needle): void {
                    $builder
                        ->whereRaw('LOWER(filename) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(alt_text) LIKE ?', [$needle]);
                });
            }
        }

        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (Media $media) => [
                'id' => $media->id,
                'filename' => $media->filename,
                'alt_text' => $media->alt_text,
                'url' => $media->publicUrl(),
                'webp_url' => $media->webpPublicUrl(),
            ])->all(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'has_more' => $paginator->hasMorePages(),
            'total' => $paginator->total(),
        ]);
    }

    public function create(): View
    {
        return view('admin.media.form', [
            'media' => new Media(['disk' => 'public']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateMedia($request, true);

        $media = new Media;
        $this->fillMediaFromRequest($media, $request, $validated);

        return redirect()
            ->route('admin.media.edit', $media)
            ->with('status', 'Media item created.');
    }

    public function edit(Media $media): View
    {
        return view('admin.media.form', [
            'media' => $media,
        ]);
    }

    public function update(Request $request, Media $media): RedirectResponse
    {
        $validated = $this->validateMedia($request, false);

        $this->fillMediaFromRequest($media, $request, $validated);

        return redirect()
            ->route('admin.media.edit', $media)
            ->with('status', 'Media item updated.');
    }

    private function validateMedia(Request $request, bool $requireFile): array
    {
        return $request->validate([
            'file' => [$requireFile ? 'required' : 'nullable', 'file', 'image', 'max:12288'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string'],
            'credit' => ['nullable', 'string', 'max:255'],
            'focal_point_x' => ['nullable', 'numeric', 'between:0,1'],
            'focal_point_y' => ['nullable', 'numeric', 'between:0,1'],
        ]);
    }

    private function fillMediaFromRequest(Media $media, Request $request, array $validated): void
    {
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $path = $file->store('media/'.now()->format('Y/m'), 'public');
            [$width, $height] = @getimagesize($file->getRealPath()) ?: [null, null];

            $media->disk = 'public';
            $media->path = $path;
            $media->filename = $file->getClientOriginalName();
            $media->mime_type = $file->getMimeType();
            $media->width = $width;
            $media->height = $height;
        }

        $media->alt_text = $validated['alt_text'] ?? null;
        $media->caption = $validated['caption'] ?? null;
        $media->credit = $validated['credit'] ?? null;
        $media->focal_point_x = $validated['focal_point_x'] ?? null;
        $media->focal_point_y = $validated['focal_point_y'] ?? null;
        $media->save();
    }
}
