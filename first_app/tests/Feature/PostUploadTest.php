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
    Storage::disk('local')->assertExists($post->media_path);
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
        Storage::disk('local')->assertExists($post->media_path);
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

test('a file exactly 100 MB is accepted', function () {
    $this->post(route('posts.store'), [
        'media' => UploadedFile::fake()->create('boundary.mp4', 102400, 'video/mp4'),
    ])->assertSessionHasNoErrors();
    expect(Post::count())->toBe(1);
});

test('a file one byte over 100 MB is rejected with the visible limit', function () {
    $this->post(route('posts.store'), [
        'media' => UploadedFile::fake()->create('oversized.mp4', 0, 'video/mp4')->size(102400 + 1 / 1024),
    ])->assertSessionHasErrors(['media' => 'Each file must be 100 MB or smaller. Select a smaller file.']);
    expect(Post::count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

test('four gigabyte uploads are rejected without publishing', function () {
    $this->post(route('posts.store'), [
        'media' => [UploadedFile::fake()->create('huge.mkv', 4 * 1024 * 1024, 'video/x-matroska')],
    ])->assertSessionHasErrors(['media.0' => 'Each file must be 100 MB or smaller. Select a smaller file.']);
    expect(Post::count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

test('combined uploads over 100 MB are rejected before any file is saved', function () {
    $this->post(route('posts.store'), [
        'media' => [
            UploadedFile::fake()->create('first.mp4', 60 * 1024, 'video/mp4'),
            UploadedFile::fake()->create('second.mp4', 50 * 1024, 'video/mp4'),
        ],
    ])->assertSessionHasErrors(['media' => 'The selected files must total 100 MB or less.']);
    expect(Post::count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

test('an oversized replacement leaves the original post and file intact', function () {
    $this->post(route('posts.store'), ['media' => UploadedFile::fake()->image('original.png')]);
    $post = Post::sole();
    $originalPath = $post->media_path;
    $this->put(route('posts.update', $post), [
        'title' => 'New title',
        'media' => UploadedFile::fake()->create('large.mp4', 102401, 'video/mp4'),
    ])->assertSessionHasErrors(['media' => 'Each file must be 100 MB or smaller. Select a smaller file.']);
    expect($post->fresh()->title)->toBe('original');
    expect($post->fresh()->media_path)->toBe($originalPath);
    Storage::disk('local')->assertExists($originalPath);
});

test('requests exceeding the PHP body limit have a readable error page', function () {
    $this->withServerVariables(['CONTENT_LENGTH' => 4 * 1024 * 1024 * 1024])
        ->post(route('posts.store'))->assertStatus(413)
        ->assertSee('That upload is too large.')
        ->assertSee('100 MB per file and 100 MB total per upload')
        ->assertDontSee('PostTooLargeException');
    expect(Post::count())->toBe(0);
});

test('requests exceeding the PHP body limit return a useful JSON error', function () {
    $this->withServerVariables(['CONTENT_LENGTH' => 4 * 1024 * 1024 * 1024])
        ->postJson(route('posts.store'))->assertStatus(413)
        ->assertJsonPath('errors.media.0', 'Upload too large. Each file and the combined upload must be 100 MB or smaller.');
    expect(Post::count())->toBe(0);
});

test('upload and edit forms show the size limit and enable selection checks', function () {
    $this->post(route('posts.store'), ['media' => UploadedFile::fake()->image('original.png')]);
    $post = Post::sole();
    foreach ([route('dashboard'), route('home'), route('posts.edit', $post)] as $url) {
        $this->get($url)->assertOk()->assertSee('Maximum: 100 MB per file')
            ->assertSee('data-max-upload-bytes="104857600"', false)
            ->assertSee('data-media-upload', false)->assertSee('upload-limits.js', false);
    }
});
