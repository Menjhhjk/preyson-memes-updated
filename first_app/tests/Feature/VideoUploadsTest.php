<?php

use App\Models\Post;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

// Small container headers exercise PHP's actual Fileinfo detection without
// mocking MIME or requiring FFmpeg. These test uploads, not codec playback.
function recordingContainer(string $extension): string
{
    $packet = "\x47\x1f\xff\x10".str_repeat("\xff", 184);
    $theora = "\x80theora".str_repeat("\0", 35);

    return match ($extension) {
        'mkv' => hex2bin('1a45dfa3a34286810142f7810142f2810442f381084282886d6174726f736b6142878104428581021853806701ffffffffffffff'),
        'webm' => hex2bin('1a45dfa39f4286810142f7810142f2810442f381084282847765626d42878104428581021853806701ffffffffffffff'),
        'mp4', 'm4v' => pack('N', 24).'ftypisom'.pack('N', 512).'isomiso2'.pack('N', 8).'mdat',
        'mov' => pack('N', 20).'ftypqt  '.pack('N', 512).'qt  '.pack('N', 8).'mdat',
        'flv' => "FLV\x01\x05\x00\x00\x00\x09\x00\x00\x00\x00",
        'avi' => 'RIFF'.pack('V', 200).'AVI LIST'.str_repeat("\0", 200),
        'wmv' => hex2bin('3026b2758e66cf11a6d900aa0062ce6c').str_repeat("\0", 200),
        'mpeg', 'mpg' => hex2bin('000001ba440004000401000003f8').str_repeat("\0", 200),
        'ts' => str_repeat($packet, 10),
        'mts', 'm2ts' => str_repeat("\0\0\0\0".$packet, 10),
        'ogv' => 'OggS'."\x00\x02".str_repeat("\0", 20)."\x01".chr(strlen($theora)).$theora,
    };
}

function recordingUpload(string $extension, ?string $filename = null): UploadedFile
{
    return recordingFile($filename ?? 'recording.'.$extension, recordingContainer($extension));
}

function recordingFile(string $filename, string $contents): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'preyson-video-');
    file_put_contents($path, $contents);
    test()->beforeApplicationDestroyed(static fn () => unlink($path));

    // Laravel's fake files infer MIME from the name. Use a real UploadedFile to
    // verify content detection, including generic binary transport streams.
    return new UploadedFile($path, $filename, null, null, true);
}

test('recording containers upload as videos with safe compatible filenames', function (string $extension) {
    $this->actingAs(User::factory()->create())->post(route('posts.store'), [
        'media' => recordingUpload($extension, 'OBS + clip!.'.strtoupper($extension)),
    ])->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));

    $post = Post::sole();
    expect($post->media_type)->toBe('video');
    expect($post->title)->toBe('OBS + clip!');
    expect($post->media_path)->toEndWith('.'.$extension);
    expect(Storage::disk('local')->get($post->media_path))->toBe(recordingContainer($extension));
})->with(['mkv', 'mp4', 'webm', 'mov', 'flv', 'avi', 'm4v', 'mpeg', 'mpg', 'ts', 'mts', 'm2ts', 'wmv', 'ogv']);

test('members can upload a mixed batch of recording formats', function () {
    $this->actingAs(User::factory()->create())->post(route('posts.store'), [
        'media' => [recordingUpload('mkv'), recordingUpload('flv'), recordingUpload('ts')],
    ])->assertSessionHasNoErrors();
    expect(Post::where('media_type', 'video')->count())->toBe(3);
});

test('post replacements accept recordings and remove the previous file', function (string $extension) {
    $owner = User::factory()->create();
    $this->actingAs($owner)->post(route('posts.store'), ['media' => UploadedFile::fake()->image('old.png')]);
    $post = Post::sole();
    $oldPath = $post->media_path;
    $this->put(route('posts.update', $post), [
        'title' => 'Replacement recording', 'media' => recordingUpload($extension), 'return_to' => 'feed',
    ])->assertSessionHasNoErrors()->assertRedirect(route('home'));
    $post->refresh();
    expect($post->media_type)->toBe('video');
    expect($post->media_path)->toEndWith('.'.$extension);
    Storage::disk('local')->assertMissing($oldPath);
    Storage::disk('local')->assertExists($post->media_path);
})->with(['mkv', 'ts', 'wmv']);

test('admin ZIP imports accept recordings and classify every video MIME family', function () {
    $path = tempnam(sys_get_temp_dir(), 'preyson-video-');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    foreach (['mkv', 'flv', 'ts', 'wmv'] as $extension) {
        $zip->addFromString('OBS recording.'.$extension, recordingContainer($extension));
    }
    $zip->close();
    try {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_admin' => true]))
            ->post(route('posts.zip'), ['media' => new UploadedFile($path, 'recordings.zip', null, null, true)])
            ->assertSessionHasNoErrors();
    } finally {
        unlink($path);
    }
    expect(Post::where('media_type', 'video')->count())->toBe(4);
    foreach (Post::all() as $post) {
        Storage::disk('local')->assertExists($post->media_path);
    }
});

test('recording extensions and client MIME cannot disguise unsupported contents', function (string $filename, string $contents) {
    $path = tempnam(sys_get_temp_dir(), 'preyson-video-');
    file_put_contents($path, $contents);
    try {
        $this->actingAs(User::factory()->create())->post(route('posts.store'), [
            'media' => new UploadedFile($path, $filename, 'video/x-matroska', null, true),
        ])->assertSessionHasErrors('media');
    } finally {
        unlink($path);
    }
    expect(Post::count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
})->with([
    'script disguised as MKV' => ['fake.mkv', '<?php echo "not a recording";'],
    'HTML disguised as AVI' => ['fake.avi', '<html><script>alert(1)</script></html>'],
    'TypeScript' => ['source.ts', 'export const value: number = 123;'],
    'TypeScript module' => ['source.mts', 'export const value: number = 123;'],
    'arbitrary binary' => ['fake.ts', str_repeat("\0\x01\x02\xff", 500)],
    'invalid transport headers' => ['fake.ts', str_repeat("\x47\x1f\xff\x00".str_repeat("\xff", 184), 10)],
    'unsupported playlist' => ['recording.m3u8', "#EXTM3U\n#EXTINF:10,\nrecording.ts"],
]);

test('an image renamed MKV remains an image with an image storage extension', function () {
    $image = UploadedFile::fake()->image('image.png');
    $this->actingAs(User::factory()->create())->post(route('posts.store'), [
        'media' => recordingFile('image.mkv', file_get_contents($image->getRealPath())),
    ])->assertSessionHasNoErrors();
    $post = Post::sole();
    expect($post->media_type)->toBe('image');
    expect($post->media_path)->not->toEndWith('.mkv');
});

test('oversized recordings still respect the existing upload limit', function () {
    $this->actingAs(User::factory()->create())->post(route('posts.store'), [
        'media' => UploadedFile::fake()->createWithContent('large.mkv', recordingContainer('mkv'))->size(102401),
    ])->assertSessionHasErrors('media');
    expect(Post::count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

test('recordings appear in video filters with download fallback on every player', function () {
    $this->actingAs(User::factory()->create())->post(route('posts.store'), ['media' => recordingUpload('mkv')]);
    $post = Post::sole();
    foreach ([route('home', ['category' => 'video']), route('dashboard'), route('posts.edit', $post)] as $url) {
        $this->get($url)->assertOk()->assertSee('recording')->assertSee('Download original video')
            ->assertSee('data-video-error', false)->assertSee(route('posts.media', $post), false);
    }
    $this->get(route('dashboard'))->assertSee('.mkv,.flv,.avi', false);
});
