<?php

use App\Models\Post;
use App\Models\Reaction;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

function featurePost(User $owner, array $attributes = []): Post
{
    return Post::create([
        'user_id' => $owner->id,
        'title' => 'Sample meme',
        'media_path' => 'memes/'.uniqid().'.gif',
        'media_type' => 'image',
        ...$attributes,
    ]);
}

function featureGif(string $filename = 'sample.gif'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($filename, featureGifBytes())->mimeType('image/gif');
}

function featureGifBytes(): string
{
    return base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
}

function featureZip(array $entries): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'preyson-test-');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    foreach ($entries as $name => $content) {
        $zip->addFromString($name, $content);
    }
    $zip->close();
    $file = UploadedFile::fake()->createWithContent('archive.zip', file_get_contents($path));
    unlink($path);

    return $file;
}

test('GIF categorization uses actual MIME and preserves filename punctuation', function () {
    $this->actingAs(User::factory()->create())->post(route('posts.store'), [
        'media' => featureGif('I’m + 100%_happy & café!.jpg'),
    ])->assertSessionHasNoErrors();

    $post = Post::sole();
    expect($post->media_type)->toBe('gif');
    expect($post->title)->toBe('I’m + 100%_happy & café!');
    Storage::disk('local')->assertExists($post->media_path);
});

test('explicit titles preserve Unicode punctuation and literal markup safely', function () {
    $owner = User::factory()->create();
    $title = '<script>alert("hi")</script> + café & 日本語 #1';
    $this->actingAs($owner)->post(route('posts.store'), ['media' => featureGif(), 'title' => $title])
        ->assertSessionHasNoErrors();
    expect(Post::sole()->title)->toBe($title);
    $this->get(route('home'))->assertSee($title)->assertDontSee($title, false);
});

test('free quota rejects an entire batch when it would exceed six stored posts', function () {
    $owner = User::factory()->create();
    foreach (range(1, 5) as $unused) {
        featurePost($owner);
    }
    $this->actingAs($owner)->post(route('posts.store'), ['media' => [featureGif(), featureGif()]])
        ->assertSessionHasErrors('media');
    expect($owner->posts()->count())->toBe(5);
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

test('premium allows thirty posts and rejects the thirty first', function () {
    $owner = User::factory()->create(['premium_expires_at' => now()->addDays(30)]);
    foreach (range(1, 29) as $unused) {
        featurePost($owner);
    }
    $this->actingAs($owner)->post(route('posts.store'), ['media' => featureGif()])->assertSessionHasNoErrors();
    $this->post(route('posts.store'), ['media' => featureGif()])->assertSessionHasErrors('media');
    expect($owner->posts()->count())->toBe(30);
});

test('expired premium keeps existing posts but stops uploads above the free quota', function () {
    $owner = User::factory()->create(['premium_expires_at' => now()->subSecond()]);
    foreach (range(1, 8) as $unused) {
        featurePost($owner);
    }
    $this->actingAs($owner)->post(route('posts.store'), ['media' => featureGif()])->assertSessionHasErrors('media');
    expect($owner->posts()->count())->toBe(8);
});

test('administrator stored post count is unlimited', function () {
    $owner = User::factory()->create(['is_admin' => true, 'role' => 'admin']);
    foreach (range(1, 30) as $unused) {
        featurePost($owner);
    }
    $this->actingAs($owner)->post(route('posts.store'), ['media' => featureGif()])->assertSessionHasNoErrors();
    expect($owner->posts()->count())->toBe(31);
});

test('moderators can edit and remove posts and feed redirects are allowlisted', function () {
    $moderator = User::factory()->create(['role' => 'moderator']);
    $post = featurePost(User::factory()->create());
    $this->actingAs($moderator)->put(route('posts.update', $post), ['title' => 'Reviewed + approved!', 'return_to' => 'feed'])
        ->assertRedirect(route('home'));
    expect($post->fresh()->title)->toBe('Reviewed + approved!');
    $this->put(route('posts.update', $post), ['title' => 'Still here', 'return_to' => 'https://example.org'])
        ->assertRedirect(route('dashboard'));
    $this->delete(route('posts.destroy', $post), ['return_to' => 'feed'])->assertRedirect(route('home'));
    expect(Post::count())->toBe(0);
});

test('single post deletion enforces ownership and cascades its reactions', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $post = featurePost($owner);
    Reaction::create(['post_id' => $post->id, 'user_id' => $other->id, 'emoji' => '👍']);
    $this->actingAs($other)->delete(route('posts.destroy', $post))->assertForbidden();
    $this->actingAs($owner)->delete(route('posts.destroy', $post))->assertRedirect(route('dashboard'));
    expect(Reaction::count())->toBe(0);
});

test('reactions switch toggle and provide current JSON counts', function () {
    $viewer = User::factory()->create();
    $post = featurePost(User::factory()->create());
    $url = route('posts.react', $post);
    $this->actingAs($viewer)->postJson($url, ['emoji' => '👍'])->assertOk()
        ->assertJsonPath('selected', '👍')->assertJsonPath('counts.👍', 1)->assertJsonPath('total', 1);
    $this->postJson($url, ['emoji' => '❤️'])->assertOk()
        ->assertJsonPath('selected', '❤️')->assertJsonPath('total', 1);
    expect(Reaction::count())->toBe(1);
    $this->postJson($url, ['emoji' => '❤️'])->assertOk()
        ->assertJsonPath('selected', null)->assertJsonPath('total', 0);
    expect(Reaction::count())->toBe(0);
});

test('extra reactions are premium gated including immediately after expiry', function () {
    $viewer = User::factory()->create();
    $post = featurePost(User::factory()->create());
    $url = route('posts.react', $post);
    $this->actingAs($viewer)->postJson($url, ['emoji' => '🔥'])->assertUnprocessable()->assertJsonValidationErrors('emoji');
    $viewer->forceFill(['premium_expires_at' => now()->addDay()])->save();
    $this->postJson($url, ['emoji' => '🔥'])->assertOk();
    $this->travel(2)->days();
    $this->postJson($url, ['emoji' => '🎉'])->assertUnprocessable();
    expect(Reaction::sole()->emoji)->toBe('🔥');
    // A lapsed member can still remove an earlier premium reaction.
    $this->postJson($url, ['emoji' => '🔥'])->assertOk()->assertJsonPath('selected', null);
});

test('reaction requests require sign in and a supported emoji', function () {
    $owner = User::factory()->create();
    $post = featurePost($owner);
    $this->postJson(route('posts.react', $post), ['emoji' => '👍'])->assertUnauthorized();
    $this->actingAs($owner)->postJson(route('posts.react', $post), ['emoji' => 'arbitrary'])
        ->assertUnprocessable();
    expect(Reaction::count())->toBe(0);
});

test('feed filters GIFs searches literal punctuation and sorts by date or reactions', function () {
    $owner = User::factory()->create();
    $old = featurePost($owner, ['title' => '100%_fun GIF', 'media_type' => 'gif']);
    $old->forceFill(['created_at' => now()->subDay()])->save();
    $new = featurePost($owner, ['title' => '100 percent fun video', 'media_type' => 'video']);
    Reaction::create(['post_id' => $old->id, 'user_id' => $owner->id, 'emoji' => '😂']);

    $this->get(route('home', ['category' => 'gif', 'q' => '%_']))->assertOk()
        ->assertViewHas('posts', fn ($posts) => $posts->modelKeys() === [$old->id]);
    $this->get(route('home', ['sort' => 'newest']))->assertOk()
        ->assertViewHas('posts', fn ($posts) => $posts->modelKeys() === [$new->id, $old->id]);
    $this->get(route('home', ['sort' => 'oldest']))->assertOk()
        ->assertViewHas('posts', fn ($posts) => $posts->modelKeys() === [$old->id, $new->id]);
    $this->get(route('home', ['sort' => 'reactions']))->assertOk()
        ->assertViewHas('posts', fn ($posts) => $posts->modelKeys() === [$old->id, $new->id])
        ->assertViewHas('reactionCounts', fn ($counts) => $counts[$old->id]['😂'] === 1);
});

test('random feed uses a stable seed across pagination while preserving filters', function () {
    $owner = User::factory()->create();
    foreach (range(1, 25) as $i) {
        featurePost($owner, ['title' => 'Seeded meme '.$i, 'media_type' => 'gif']);
    }
    $query = ['seed' => 42, 'category' => 'gif', 'q' => 'Seeded'];
    $first = $this->get(route('home', $query))->assertOk()->viewData('posts');
    $repeat = $this->get(route('home', $query))->assertOk()->viewData('posts');
    $second = $this->get($first->nextPageUrl())->assertOk()->viewData('posts');
    $different = $this->get(route('home', [...$query, 'seed' => 12345]))->assertOk()->viewData('posts');
    expect($first->modelKeys())->toBe($repeat->modelKeys())->not->toBe($different->modelKeys());
    expect(array_intersect($first->modelKeys(), $second->modelKeys()))->toBe([]);
    expect($first->nextPageUrl())->toContain('seed=42', 'category=gif', 'q=Seeded', 'sort=random');
});

test('ZIP import verifies content and rolls back every earlier archive entry on failure', function () {
    $admin = User::factory()->create(['is_admin' => true, 'role' => 'admin']);
    $gif = featureGifBytes();
    $this->actingAs($admin)->post(route('posts.zip'), ['media' => featureZip([
        'good.gif' => $gif,
        'fake.jpg' => '<?php echo "not an image"; ?>',
    ])])->assertSessionHasErrors('media');
    expect(Post::count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

test('ZIP imports use MIME for category and never extract archive paths', function () {
    $admin = User::factory()->create(['is_admin' => true, 'role' => 'admin']);
    $this->actingAs($admin)->post(route('posts.zip'), ['media' => featureZip([
        '../Punctuation + café!.jpg' => featureGifBytes(),
    ])])->assertSessionHasNoErrors();
    $post = Post::sole();
    expect($post->title)->toBe('Punctuation + café!');
    expect($post->media_type)->toBe('gif');
    expect($post->media_path)->toStartWith('memes/')->not->toContain('..');
    Storage::disk('local')->assertExists($post->media_path);
});

test('ZIP import limits decompressed size before saving files', function () {
    $admin = User::factory()->create(['is_admin' => true, 'role' => 'admin']);
    $archive = featureZip(['oversized.gif' => featureGifBytes()]);
    $contents = file_get_contents($archive->getRealPath());
    // Central-directory uncompressed size: reject oversized declarations without
    // allocating a 100 MB fixture or ever opening the archive entry stream.
    $offset = strpos($contents, "PK\x01\x02") + 24;
    $contents = substr_replace($contents, pack('V', 100 * 1024 * 1024 + 1), $offset, 4);
    $this->actingAs($admin)->post(route('posts.zip'), ['media' => UploadedFile::fake()->createWithContent('large.zip', $contents)])
        ->assertSessionHasErrors('media');
    expect(Post::count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
});
