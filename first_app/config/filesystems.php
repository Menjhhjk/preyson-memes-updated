<?php

/*
| STORAGE_DRIVER=local keeps media on this computer (storage/app/*).
| STORAGE_DRIVER=s3 moves both media disks to Cloudflare R2 buckets:
|   local  -> R2_MEDIA_BUCKET (private post media, served via signed URLs)
|   public -> R2_AVATARS_BUCKET (public avatars)
| R2 accepts the S3 ACL header only with the value "private", so both
| disks must stay private; avatar visibility comes from bucket settings.
*/
$useS3 = env('STORAGE_DRIVER', 'local') === 's3';

$s3Base = [
    'driver' => 's3',
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION', 'auto'),
    'endpoint' => env('AWS_ENDPOINT'),
    'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', true),
    'throw' => false,
    'report' => false,
];

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => $useS3 ? array_merge($s3Base, [
            'bucket' => env('R2_MEDIA_BUCKET'),
            'visibility' => 'private',
        ]) : [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => $useS3 ? array_merge($s3Base, [
            'bucket' => env('R2_AVATARS_BUCKET'),
            'url' => env('R2_AVATARS_URL'),
            'visibility' => 'private',
        ]) : [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim((string) env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => array_merge($s3Base, [
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
        ]),

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
