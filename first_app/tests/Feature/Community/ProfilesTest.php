<?php

use App\Models\Comment;
use App\Models\Reaction;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/CommunityHelpers.php';

beforeEach(fn () => Storage::fake('public'));

test('public profiles show biography stats and only their own posts with hidden email by default', function () {
    $member = User::factory()->create(['description' => 'My bio & <script>bad()</script>']);
    $other = User::factory()->create();
    $post = communityPost($member, ['title' => 'Profile original']);
    communityPost($other, ['title' => 'Another account post']);
    Reaction::create(['post_id' => $post->id, 'user_id' => $other->id, 'emoji' => '👍']);
    Comment::create(['post_id' => $post->id, 'user_id' => $other->id, 'body' => 'Nice']);
    $this->get(route('profiles.show', $member))->assertOk()->assertSee($member->description)->assertDontSee('<script>bad()</script>', false)
        ->assertSee('Profile original')->assertDontSee('Another account post')->assertDontSee($member->email)
        ->assertViewHas('stats', ['posts' => 1, 'reactions' => 1, 'comments' => 1]);
    $member->forceFill(['email_visible' => true])->save();
    $this->get(route('profiles.show', $member))->assertSee($member->email);
});

test('profile settings persist safely and cannot assign another account or role', function () {
    $member = User::factory()->create();
    $other = User::factory()->create();
    $this->actingAs($member)->patch(route('profile.update'), [
        'description' => 'New description', 'profile_visibility' => 'private', 'email_visible' => 1,
        'role' => 'admin', 'user_id' => $other->id,
    ])->assertSessionHasNoErrors();
    $member->refresh();
    expect($member->description)->toBe('New description')->and($member->profile_visibility)->toBe('private')
        ->and($member->email_visible)->toBeTrue()->and($member->role)->toBe('member');
    expect($other->fresh()->description)->toBeNull();
    $this->patch(route('profile.update'), ['description' => str_repeat('x', 1001), 'profile_visibility' => 'everyone', 'email_visible' => 'yes'])
        ->assertSessionHasErrors(['description', 'profile_visibility', 'email_visible']);
    $this->get(route('profile.edit'))->assertSee('Profile visibility')->assertSee('Email visibility')->assertSee('About you');
});

test('private profiles hide bio email stats posts files and interactions from visitors', function () {
    $member = User::factory()->create(['profile_visibility' => 'private', 'email_visible' => true, 'description' => 'Private biography']);
    $post = communityPost($member, ['title' => 'Private title']);
    $comment = $post->comments()->create(['user_id' => $member->id, 'body' => 'Private comment']);
    $this->get(route('profiles.show', $member))->assertOk()->assertSee('A little privacy')->assertDontSee('Private biography')
        ->assertDontSee('Private title')->assertDontSee($member->email)->assertViewHas('stats', []);
    $this->get(route('home'))->assertDontSee('Private title');
    $this->get(route('posts.show', $post))->assertNotFound();
    $this->get(route('posts.media', $post))->assertNotFound();
    $this->get(route('comments.show', $comment))->assertNotFound();
    $this->actingAs(User::factory()->create());
    $this->get(route('home'))->assertDontSee('Private title');
    $this->post(route('comments.store', $post), ['body' => 'Sneak in'])->assertNotFound();
    $this->post(route('posts.react', $post), ['emoji' => '👍'])->assertNotFound();
    foreach (['post' => $post, 'comment' => $comment] as $type => $target) {
        $this->get(route('reports.create', ['type' => $type, 'id' => $target->id]))->assertNotFound();
        $this->post(route('reports.store', ['type' => $type, 'id' => $target->id]), ['reason' => 'spam'])->assertNotFound();
    }
    $this->get(route('reports.create', ['type' => 'account', 'id' => $member->id]))->assertOk()->assertDontSee('Private biography');
    foreach ([$member, User::factory()->create(['role' => 'moderator']), User::factory()->create(['role' => 'admin'])] as $allowed) {
        $this->actingAs($allowed)->get(route('profiles.show', $member))->assertOk()->assertSee('Private biography')->assertSee('Private title');
        $this->get(route('posts.show', $post))->assertOk()->assertSee('Private comment');
        $this->get(route('posts.media', $post))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    }
});

test('making a profile private protects legacy uploads and preserves the bytes', function () {
    $member = User::factory()->create();
    $post = communityPost($member);
    $bytes = Storage::disk('local')->get($post->media_path);
    Storage::disk('public')->put($post->media_path, $bytes);
    Storage::disk('local')->delete($post->media_path);
    $this->actingAs($member)->patch(route('profile.update'), ['profile_visibility' => 'private'])->assertSessionHasNoErrors();
    Storage::disk('public')->assertMissing($post->media_path);
    expect(Storage::disk('local')->get($post->media_path))->toBe($bytes);
    $this->patch(route('profile.update'), ['profile_visibility' => 'public'])->assertSessionHasNoErrors();
    Storage::disk('public')->assertMissing($post->media_path);
    $this->get(route('posts.media', $post))->assertOk();
});

test('legacy media migration is repeatable and does not move avatars', function () {
    $post = communityPost(User::factory()->create());
    $bytes = Storage::disk('local')->get($post->media_path);
    Storage::disk('public')->put($post->media_path, $bytes);
    Storage::disk('public')->put('avatars/keep.png', 'avatar');
    Storage::disk('local')->delete($post->media_path);
    $this->artisan('preyson:protect-media')->assertSuccessful();
    $this->artisan('preyson:protect-media')->assertSuccessful();
    expect(Storage::disk('local')->get($post->media_path))->toBe($bytes);
    Storage::disk('public')->assertMissing($post->media_path);
    Storage::disk('public')->assertExists('avatars/keep.png');
});

test('a failed legacy media verification preserves the public file and refuses a private profile', function () {
    $member = User::factory()->create();
    $post = communityPost($member);
    Storage::disk('public')->put($post->media_path, 'original bytes');
    $this->actingAs($member)->patch(route('profile.update'), ['profile_visibility' => 'private'])->assertSessionHasErrors('profile_visibility');
    expect($member->fresh()->profile_visibility)->toBe('public');
    expect(Storage::disk('public')->get($post->media_path))->toBe('original bytes');
});

test('premium profile colors work for visitors expire gracefully and reject injected colors', function () {
    $member = User::factory()->create(['premium_expires_at' => now()->addDays(1)]);
    $this->actingAs($member)->patch(route('profile.update'), [
        'profile_background' => 'gradient', 'profile_color_one' => '#112233', 'profile_color_two' => '#ddeeff',
    ])->assertSessionHasNoErrors();
    $member->refresh();
    expect($member->profileBackground())->toBe('linear-gradient(135deg, #112233, #ddeeff)');
    $this->get(route('profiles.show', $member))->assertOk()->assertSee('--profile-background: linear-gradient(135deg, #112233, #ddeeff)', false);
    $this->patch(route('profile.update'), ['profile_background' => 'solid'])->assertSessionHasNoErrors();
    expect($member->fresh()->profileBackground())->toBe('#112233');
    $this->patch(route('profile.update'), ['profile_color_one' => '#123456; background: url(https://example.com)'])->assertSessionHasErrors('profile_color_one');
    $this->patch(route('profile.update'), ['profile_color_two' => '#fff'])->assertSessionHasErrors('profile_color_two');
    $member->forceFill(['premium_expires_at' => now()->subSecond()])->save();
    $this->get(route('profiles.show', $member))->assertDontSee('--profile-background:', false);
    $this->patch(route('profile.update'), ['profile_background' => 'gradient'])->assertSessionHasErrors('profile_background');
    $this->patch(route('profile.update'), ['description' => 'Still editable'])->assertSessionHasNoErrors();
    expect($member->fresh()->profile_color_one)->toBe('#112233');
});

test('only one owned post is pinned and explicit sorting overrides its position', function () {
    $member = User::factory()->create();
    $first = communityPost($member, ['title' => 'Pinned older post', 'created_at' => now()->subDays(3)]);
    $latest = communityPost($member, ['title' => 'Latest post', 'created_at' => now()]);
    $other = communityPost(User::factory()->create());
    $this->actingAs($member)->put(route('posts.pin', $other), ['pinned' => 1])->assertForbidden();
    $this->put(route('posts.pin', $first), ['pinned' => 1])->assertSessionHasNoErrors();
    expect($member->fresh()->pinned_post_id)->toBe($first->id);
    $this->get(route('profiles.show', $member))->assertOk()->assertViewHas('posts', fn ($posts) => $posts->modelKeys() === [$first->id, $latest->id]);
    $this->get(route('profiles.show', ['member' => $member, 'sort' => 'newest']))->assertViewHas('posts', fn ($posts) => $posts->modelKeys() === [$latest->id, $first->id]);
    $this->get(route('profiles.show', ['member' => $member, 'sort' => 'default']))->assertViewHas('posts', fn ($posts) => $posts->modelKeys() === [$first->id, $latest->id]);
    $this->put(route('posts.pin', $latest), ['pinned' => 1])->assertSessionHasNoErrors();
    $this->put(route('posts.pin', $first), ['pinned' => 0])->assertSessionHasNoErrors();
    expect($member->fresh()->pinned_post_id)->toBe($latest->id);
    $this->delete(route('posts.destroy', $latest))->assertSessionHasNoErrors();
    expect($member->fresh()->pinned_post_id)->toBeNull();
});

test('profile media filters keep pins within the filtered result and pagination has no duplicates', function () {
    $member = User::factory()->create(['premium_expires_at' => now()->addDays(30)]);
    foreach (range(1, 14) as $number) {
        $post = communityPost($member, ['title' => 'Post '.$number, 'media_type' => $number === 14 ? 'video' : 'gif']);
    }
    $member->forceFill(['pinned_post_id' => $post->id])->save();
    $page1 = $this->get(route('profiles.show', $member))->assertOk()->viewData('posts')->modelKeys();
    $page2 = $this->get(route('profiles.show', ['member' => $member, 'page' => 2]))->assertOk()->viewData('posts')->modelKeys();
    expect($page1[0])->toBe($post->id)->and(count(array_unique([...$page1, ...$page2])))->toBe(14);
    $this->get(route('profiles.show', ['member' => $member, 'category' => 'gif']))->assertViewHas('posts', fn ($posts) => $posts->total() === 13 && ! in_array($post->id, $posts->modelKeys(), true));
});

test('protected video delivery supports byte ranges and download responses', function () {
    $post = communityPost(User::factory()->create(), ['media_type' => 'video']);
    $this->withHeaders(['Range' => 'bytes=0-9'])->get(route('posts.media', $post))->assertStatus(206)->assertHeader('Content-Length', '10');
    $response = $this->withHeaders(['Range' => ''])->get(route('posts.media', ['post' => $post, 'download' => 1]))->assertOk();
    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment;');
});
