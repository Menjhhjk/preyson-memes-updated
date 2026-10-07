<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

// Run from first_app AFTER switching .env to STORAGE_DRIVER=s3:
//   php scripts/sync-media-to-r2.php
// Copies existing local uploads into the configured Cloudflare R2 buckets:
//   storage/app/private/**        -> R2_MEDIA_BUCKET   (private post media)
//   storage/app/public/avatars/** -> R2_AVATARS_BUCKET (avatars)
//   storage/app/public/other/**   -> R2_MEDIA_BUCKET   (legacy public post media)
// Safe to repeat: files already present in R2 with the same size are skipped.

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (config('filesystems.disks.local.driver') !== 's3' || config('filesystems.disks.public.driver') !== 's3') {
    fwrite(STDERR, "Set STORAGE_DRIVER=s3 together with the R2 credentials in .env before syncing.\n");
    exit(1);
}

$files = new Filesystem;
$privateRoot = storage_path('app/private');
$publicRoot = storage_path('app/public');

$isHidden = static fn (string $relative): bool => str_starts_with($relative, '.') || str_contains($relative, '/.');

$jobs = [
    ['source' => $privateRoot, 'disk' => 'local', 'filter' => static fn (string $relative): bool => ! $isHidden($relative)],
    ['source' => $publicRoot, 'disk' => 'public', 'filter' => static fn (string $relative): bool => str_starts_with($relative, 'avatars/') && ! $isHidden($relative)],
    ['source' => $publicRoot, 'disk' => 'local', 'filter' => static fn (string $relative): bool => ! str_starts_with($relative, 'avatars/') && ! $isHidden($relative)],
];

$uploaded = 0;
$skipped = 0;
$failed = 0;

foreach ($jobs as $job) {
    if (! is_dir($job['source'])) {
        continue;
    }
    $destination = Storage::disk($job['disk']);
    /** @var SplFileInfo $file */
    foreach ($files->allFiles($job['source']) as $file) {
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($job['source']) + 1));
        if (! $job['filter']($relative)) {
            continue;
        }
        $size = $file->getSize();
        if ($destination->exists($relative) && $destination->size($relative) === $size) {
            $skipped++;

            continue;
        }
        $stream = @fopen($file->getPathname(), 'rb');
        if ($stream === false) {
            $failed++;
            fwrite(STDERR, "\nCannot read local file: ".$file->getPathname()."\n");

            continue;
        }
        try {
            $written = $destination->writeStream($relative, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
        if (! $written || ! $destination->exists($relative) || $destination->size($relative) !== $size) {
            $failed++;
            fwrite(STDERR, "\nUpload failed (size mismatch or write error): $relative\n");

            continue;
        }
        $uploaded++;
        echo '.';
    }
}

echo "\nUploaded: $uploaded | already in R2: $skipped | failed: $failed\n";
if ($failed > 0) {
    exit(1);
}
echo "Sync complete. Open the app, play a video, and check an avatar before removing local copies.\n";
