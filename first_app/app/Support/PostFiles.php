<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
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
        $sourceHash = self::hash($public, $path);
        $destinationHash = self::hash($private, $path);
        if ($sourceHash === false || $destinationHash === false || ! hash_equals($sourceHash, $destinationHash)) {
            throw new RuntimeException('Media verification failed. The original file has been preserved.');
        }
        if (! $public->delete($path)) {
            throw new RuntimeException('Unable to remove the public media copy. Profile privacy was not changed.');
        }
    }

    /** Hash a stored file without assuming a local filesystem path (S3/R2 safe). */
    private static function hash(Filesystem $disk, string $path): string|false
    {
        $stream = $disk->readStream($path);
        if (! is_resource($stream)) {
            return false;
        }
        try {
            $context = hash_init('sha256');
            hash_update_stream($context, $stream);

            return hash_final($context);
        } finally {
            fclose($stream);
        }
    }
}
