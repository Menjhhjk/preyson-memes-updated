<?php

use App\Models\Post;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

require_once __DIR__.'/Auth/CaptchaTestHelpers.php';

beforeEach(function () {
    Storage::fake('public');
});

function memberPost(?User $owner, string $title = 'Existing meme'): Post
{
    $path = 'memes/'.uniqid().'.png';
    Storage::disk('local')->put($path, 'existing media');

    return Post::create(['user_id' => $owner?->id, 'title' => $title, 'media_path' => $path, 'media_type' => 'image']);
}

function memberSignupData(TestCase $testCase): array
{
    return [...solveColorCaptcha($testCase, 'register'), 'terms' => '1', 'username' => 'Jane_Doe', 'email' => 'jane@example.com',
        'password' => 'Test-password1!', 'password_confirmation' => 'Test-password1!'];
}

test('registration saves the member profile and never grants admin access', function () {
    $this->post(route('register.store'), [...memberSignupData($this), 'is_admin' => true])
        ->assertSessionHasNoErrors()->assertRedirect('/dashboard');

    $member = User::sole();
    expect($member->username)->toBe('jane_doe');
    expect($member->is_admin)->toBeFalse();
    expect(Hash::check('Test-password1!', $member->password))->toBeTrue();
    $this->assertAuthenticatedAs($member);
});

test('registration rejects missing details and mismatched passwords', function () {
    $this->post(route('register.store'), [...memberSignupData($this), 'username' => '', 'password_confirmation' => 'wrong'])
        ->assertSessionHasErrors(['username', 'password']);
    expect(User::count())->toBe(0);
});

test('registration rejects a duplicate username regardless of letter case', function () {
    User::factory()->create(['username' => 'jane_doe']);
    $this->post(route('register.store'), memberSignupData($this))->assertSessionHasErrors('username');
    expect(User::count())->toBe(1);
});

test('members can sign in with their username', function () {
    $member = User::factory()->create(['username' => 'jane_doe']);
    $this->post(route('login.store'), [...solveColorCaptcha($this), 'email' => 'Jane_Doe', 'password' => 'password'])->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($member);
});

test('members can upload six files and each post belongs to them', function () {
    $member = User::factory()->create();
    $files = array_map(fn ($n) => UploadedFile::fake()->image("meme-$n.png"), range(1, 6));
    $this->actingAs($member)->post(route('posts.store'), ['media' => $files])
        ->assertSessionHasNoErrors()->assertRedirect('/dashboard');
    expect(Post::where('user_id', $member->id)->count())->toBe(6);
    foreach (Post::all() as $post) {
        Storage::disk('local')->assertExists($post->media_path);
    }
});

test('more than six files are rejected before any are saved', function () {
    $files = array_map(fn ($n) => UploadedFile::fake()->image("meme-$n.png"), range(1, 7));
    $this->actingAs(User::factory()->create())->post(route('posts.store'), ['media' => $files])
        ->assertSessionHasErrors('media');
    expect(Post::count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

test('members cannot use ZIP imports to bypass the six file limit', function () {
    $this->actingAs(User::factory()->create())->post(route('posts.zip'), [
        'media' => UploadedFile::fake()->create('memes.zip', 1, 'application/zip'),
    ])->assertForbidden();
    expect(Post::count())->toBe(0);
});

test('member dashboards show only their own posts', function () {
    $member = User::factory()->create();
    $own = memberPost($member, 'My own meme');
    memberPost(User::factory()->create(), 'Someone else meme');
    memberPost(null, 'Legacy admin meme');
    $this->actingAs($member)->get('/dashboard')->assertOk()->assertViewHas('posts', fn ($posts) => $posts->modelKeys() === [$own->id]);
});

test('members cannot edit update or delete another member or legacy post', function (?string $ownerType) {
    $member = User::factory()->create();
    $post = memberPost($ownerType ? User::factory()->create() : null);
    $this->actingAs($member)->get(route('posts.edit', $post))->assertForbidden();
    $this->put(route('posts.update', $post), ['title' => 'Unauthorized change'])->assertForbidden();
    $this->delete(route('posts.batchDelete'), ['post_ids' => [$post->id]])->assertForbidden();
    expect($post->fresh()->title)->toBe('Existing meme');
    Storage::disk('local')->assertExists($post->media_path);
})->with(['another member' => 'member', 'legacy' => null]);

test('mixed ownership batch deletion rejects everything', function () {
    $member = User::factory()->create();
    $own = memberPost($member);
    $other = memberPost(null);
    $this->actingAs($member)->delete(route('posts.batchDelete'), ['post_ids' => [$own->id, $other->id]])->assertForbidden();
    expect(Post::count())->toBe(2);
    Storage::disk('local')->assertExists([$own->media_path, $other->media_path]);
});

test('members can edit replace and delete their own posts', function () {
    $member = User::factory()->create();
    $post = memberPost($member);
    $oldPath = $post->media_path;
    $this->actingAs($member)->get(route('posts.edit', $post))->assertOk()->assertSee('Edit Meme Post');
    $this->put(route('posts.update', $post), ['title' => 'Updated meme', 'media' => UploadedFile::fake()->image('replacement.png')])
        ->assertSessionHasNoErrors()->assertRedirect('/dashboard');
    $post->refresh();
    expect($post->title)->toBe('Updated meme');
    Storage::disk('local')->assertMissing($oldPath);
    Storage::disk('local')->assertExists($post->media_path);
    $this->delete(route('posts.batchDelete'), ['post_ids' => [$post->id]])->assertRedirect('/dashboard');
    expect(Post::count())->toBe(0);
    Storage::disk('local')->assertMissing($post->media_path);
});

test('an invalid replacement keeps the original media', function () {
    $member = User::factory()->create();
    $post = memberPost($member);
    $this->actingAs($member)->put(route('posts.update', $post), [
        'title' => 'Changed', 'media' => UploadedFile::fake()->create('script.php', 1, 'text/plain'),
    ])->assertSessionHasErrors('media');
    expect($post->fresh()->title)->toBe('Existing meme');
    Storage::disk('local')->assertExists($post->media_path);
});

test('admins retain access to member posts and legacy posts', function () {
    $admin = User::factory()->create();
    $admin->forceFill(['is_admin' => true])->save();
    $legacy = memberPost(null);
    $member = memberPost(User::factory()->create());
    $this->actingAs($admin)->get('/dashboard')->assertOk()->assertViewHas('posts', fn ($posts) => $posts->count() === 2);
    $this->get(route('posts.edit', $legacy))->assertOk();
    $this->put(route('posts.update', $member), ['title' => 'Moderated'])->assertRedirect('/dashboard');
    $this->delete(route('posts.batchDelete'), ['post_ids' => [$legacy->id, $member->id]])->assertRedirect('/dashboard');
    expect(Post::count())->toBe(0);
});
