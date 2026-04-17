<?php

namespace App\Traits;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait HandlesAsyncUploads
{
    /**
     * Resolve a temporary upload to its final location.
     *
     * @param  string|null  $tempFilename  The temporary filename from FilePond.
     * @param  string  $destinationFolder  The destination folder in the storage.
     * @param  string|null  $currentPath  The current path to delete if a new file is uploaded.
     * @param  string  $disk  The disk to use.
     * @return string|null The new path or the current path.
     */
    protected function resolveTemporaryUpload(?string $tempFilename, string $destinationFolder, ?string $currentPath = null, string $disk = 'local'): ?string
    {
        if (! $tempFilename) {
            return $currentPath;
        }

        // If it's already a path in the destination folder, return it (backward compatibility or no change)
        if (str_starts_with($tempFilename, $destinationFolder.'/')) {
            return $tempFilename;
        }

        // Check if the temporary file exists
        if (Storage::disk('local')->exists('tmp/'.$tempFilename)) {
            // Delete old file if it exists
            if ($currentPath && Storage::disk($disk)->exists($currentPath)) {
                Storage::disk($disk)->delete($currentPath);
            }

            $extension = pathinfo($tempFilename, PATHINFO_EXTENSION);
            $newPath = $destinationFolder.'/'.Str::uuid().'.'.$extension;

            // Move the file from tmp to final destination
            // Note: FilePond's tmp is on 'local' disk, destination could be different
            $fileContents = Storage::disk('local')->get('tmp/'.$tempFilename);
            Storage::disk($disk)->put($newPath, $fileContents);
            Storage::disk('local')->delete('tmp/'.$tempFilename);

            return $newPath;
        }

        return $currentPath;
    }
}
