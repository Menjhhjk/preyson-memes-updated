<?php

use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function communityPost(User $owner, array $attributes = []): Post
{
    $path = 'memes/'.Str::random(20).'.gif';
    Storage::disk('local')->put($path, base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'));

    $post = new Post;
    $post->forceFill([
        'user_id' => $owner->id, 'title' => 'A community meme', 'media_path' => $path,
        'media_type' => 'gif', ...$attributes,
    ])->save();

    return $post->refresh();
}
