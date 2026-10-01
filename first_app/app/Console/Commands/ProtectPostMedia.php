<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Support\PostFiles;
use Illuminate\Console\Command;

class ProtectPostMedia extends Command
{
    protected $signature = 'preyson:protect-media';

    protected $description = 'Move legacy public post media into protected storage, verifying each copy before removal';

    public function handle(): int
    {
        Post::query()->select(['id', 'media_path'])->chunkById(100, function ($posts) {
            foreach ($posts as $post) {
                PostFiles::protect($post->media_path);
            }
        });
        $this->info('Post media is protected. Avatars remain public.');

        return self::SUCCESS;
    }
}
