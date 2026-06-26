<?php

namespace App\Services\Galleries;

use App\Models\Photo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Builds a downloadable ZIP of photo originals, streamed from storage. Entry
 * names are de-duplicated so repeated original filenames don't collide.
 */
class GalleryArchive
{
    /**
     * @param  Collection<int, Photo>  $photos
     */
    public function download(Collection $photos, string $filename): BinaryFileResponse
    {
        $archivePath = tempnam(sys_get_temp_dir(), 'gallery-zip');
        $zip = new \ZipArchive;

        abort_unless($zip->open($archivePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true, 500);

        $usedNames = [];

        foreach ($photos as $photo) {
            $disk = Storage::disk($photo->disk ?? 's3');

            if (! $disk->exists($photo->path)) {
                continue;
            }

            $zip->addFromString($this->uniqueEntryName($photo, $usedNames), (string) $disk->get($photo->path));
        }

        $zip->close();

        return response()->download($archivePath, $filename)->deleteFileAfterSend(true);
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
