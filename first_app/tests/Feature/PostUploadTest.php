<?php

use App\Models\Post;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->actingAs(User::factory()->create());
});

test('an admin can upload a file larger than the old PHP limit', function () {
    $response = $this->post(route('posts.store'), [
        'media' => UploadedFile::fake()->create('My meme.mp4', 3072, 'video/mp4'),
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect('/dashboard');
    $post = Post::sole();
    expect($post->title)->toBe('My meme');
    expect($post->media_type)->toBe('video');
    Storage::disk('public')->assertExists($post->media_path);
});

test('an admin can import a ZIP archive', function (bool $multiple) {
    auth()->user()->forceFill(['is_admin' => true])->save();
    $path = tempnam(sys_get_temp_dir(), 'meme-zip-');

    try {
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('memes/Example.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aX1sAAAAASUVORK5CYII='));
        $zip->close();

        $upload = new UploadedFile($path, 'memes.zip', 'application/zip', null, true);
        $this->post(route('posts.zip'), [
            'media' => $multiple ? [$upload] : $upload,
        ])->assertSessionHasNoErrors()->assertRedirect('/dashboard');

        $post = Post::sole();
        expect($post->title)->toBe('Example');
        Storage::disk('public')->assertExists($post->media_path);
    } finally {
        if (file_exists($path)) {
            unlink($path);
        }
    }
})->with([false, true]);

test('upload validation errors are visible on the dashboard', function () {
    $response = $this->from('/dashboard')->post(route('posts.store'), [
        'media' => UploadedFile::fake()->create('oversized.mp4', 102401, 'video/mp4'),
    ]);

    $response->assertSessionHasErrors('media')->assertRedirect('/dashboard');
    $errors = session('errors');
    $this->withCookie(session()->getName(), session()->getId())->get('/dashboard')->assertOk()
        ->assertSee('The request could not be completed:')
        ->assertSee($errors->first('media'));
    expect(Post::count())->toBe(0);
});

test('PHP temporary folder failures show an actionable error', function () {
    $this->from('/dashboard')->post(route('posts.store'), [
        'media' => new UploadedFile('', 'test.png', 'image/png', UPLOAD_ERR_NO_TMP_DIR, true),
    ])->assertRedirect('/dashboard')->assertSessionHasErrors([
        'media' => 'The server upload temporary folder is missing or unavailable. Configure a writable upload_tmp_dir in php.ini and restart PHP.',
    ]);

    expect(Post::count())->toBe(0);
});
