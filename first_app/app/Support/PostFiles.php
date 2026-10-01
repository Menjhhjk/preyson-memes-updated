<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PostFiles
{
    /** @param string|array<string> $paths */
    public static function delete(string|array $paths): void
    {
        Storage::disk('local')->delete($paths);
        Storage::disk('public')->delete($paths);
    }

    public static function protect(string $path): void
    {
        $public = Storage::disk('public');
        $private = Storage::disk('local');
        if (! $public->exists($path)) {
            return;
        }
        $source = $public->path($path);
        if (! $private->exists($path)) {
            $stream = $public->readStream($path);
            if (! is_resource($stream)) {
                throw new RuntimeException('Unable to read existing post media.');
            }
            try {
                if (! $private->writeStream($path, $stream)) {
                    throw new RuntimeException('Unable to protect existing post media. Check storage space and permissions.');
                }
            } finally {
                fclose($stream);
            }
        }
        $sourceHash = hash_file('sha256', $source);
        $destinationHash = hash_file('sha256', $private->path($path));
        if ($sourceHash === false || $destinationHash === false || ! hash_equals($sourceHash, $destinationHash)) {
            throw new RuntimeException('Media verification failed. The original file has been preserved.');
        }
        if (! $public->delete($path)) {
            throw new RuntimeException('Unable to remove the public media copy. Profile privacy was not changed.');
        }
    }
}
