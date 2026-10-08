<?php

use App\Models\Comment;
use App\Models\CornerRequest;
use App\Models\MemberNotification;
use App\Models\MemberWarning;
use App\Models\Post;
use App\Models\PostingDay;
use App\Models\PostPowerUse;
use App\Models\Reaction;
use App\Models\SuperReactionType;
use App\Models\User;
use App\Support\PostingRewards;
use App\Support\PowerCharges;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

require_once __DIR__.'/Community/CommunityHelpers.php';

beforeEach(function () {
    Storage::fake('public');
    $this->travelTo(now()->setDate(2026, 10, 8)->setTime(10, 0));
});

function powerPayload(array $extra = []): array
{
    return ['request_key' => (string) Str::uuid(), 'confirmed' => 1, ...$extra];
}

test('power charges require confirmation enforce independent limits and retry exactly once', function () {
    $member = User::factory()->create(['bonus_boosts' => 1]);
    $owner = User::factory()->create();
    $post = communityPost($owner);
    $this->actingAs($member)->postJson(route('posts.boost', $post), ['request_key' => (string) Str::uuid()])->assertUnprocessable();
    $payload = powerPayload();
    $this->postJson(route('posts.boost', $post), $payload)->assertOk()->assertJsonPath('balance.total', 3);
    $this->postJson(route('posts.boost', $post), $payload)->assertOk()->assertJsonPath('balance.total', 3);
    expect(PostPowerUse::count())->toBe(1)->and(MemberNotification::where('user_id', $owner->id)->count())->toBe(1);
    $this->postJson(route('posts.boost', communityPost($owner)), $payload)->assertUnprocessable();
    for ($i = 0; $i < 3; $i++) {
        $this->postJson(route('posts.boost', $post), powerPayload())->assertOk();
    }
    $this->postJson(route('posts.boost', $post), powerPayload())->assertUnprocessable()->assertJsonValidationErrors('charge');
    expect(PostPowerUse::where('source', 'bonus')->count())->toBe(1)->and($member->fresh()->bonus_boosts)->toBe(0);
    $this->postJson(route('posts.super-react', $post), powerPayload(['super_reaction_type_id' => SuperReactionType::first()->id]))
        ->assertOk()->assertJsonPath('balance.total', 2)->assertJsonPath('effect.name', 'Absolutely Stellar');
    $post->delete();
    expect(PowerCharges::balance($member->fresh(), 'boost')['total'])->toBe(0);
});

test('calendar allowances refresh at Philippine midnight and membership changes do not refill on demand', function () {
    $free = User::factory()->create();
    $premium = User::factory()->create(['premium_expires_at' => now()->addMonths(3)]);
    $post = communityPost($free);
    $this->travelTo(now()->setDate(2026, 10, 31)->setTime(15, 59, 59));
    $this->actingAs($free)->postJson(route('posts.boost', $post), powerPayload())->assertOk()->assertJsonPath('balance.total', 2);
    $this->travel(1)->seconds();
    expect(PowerCharges::balance($free, 'boost')['allowance'])->toBe(3);
    $this->travelTo(now()->setDate(2026, 11, 1)->setTime(15, 59, 59)); // Sunday 23:59:59 PHT
    $this->actingAs($premium)->postJson(route('posts.boost', $post), powerPayload())->assertOk()->assertJsonPath('balance.total', 4);
    $premium->forceFill(['premium_expires_at' => null])->save();
    expect(PowerCharges::balance($premium, 'boost')['allowance'])->toBe(2);
    $premium->forceFill(['premium_expires_at' => now()->addMonth()])->save();
    expect(PowerCharges::balance($premium, 'boost')['allowance'])->toBe(4);
    $this->travel(1)->seconds();
    expect(PowerCharges::balance($premium, 'boost')['allowance'])->toBe(5);
});

test('premium has five charges and invalid unavailable or private targets spend none', function () {
    $premium = User::factory()->create(['premium_expires_at' => now()->addMonth()]);
    $private = communityPost(User::factory()->create(['profile_visibility' => 'private']));
    $post = communityPost(User::factory()->create());
    $type = SuperReactionType::first();
    $this->actingAs($premium)->postJson(route('posts.boost', $private), powerPayload())->assertNotFound();
    $type->update(['is_active' => false]);
    $this->postJson(route('posts.super-react', $post), powerPayload(['super_reaction_type_id' => $type->id]))->assertUnprocessable();
    expect(PostPowerUse::count())->toBe(0);
    for ($i = 0; $i < 5; $i++) {
        $this->postJson(route('posts.boost', $post), powerPayload())->assertOk()->assertJsonPath('balance.total', 4 - $i);
    }
    $this->postJson(route('posts.boost', $post), powerPayload())->assertUnprocessable();
});

test('boosts create one extra default feed appearance each for exactly 24 hours without leaking private profiles', function () {
    $owner = User::factory()->create();
    $post = communityPost($owner);
    $this->actingAs($owner);
    for ($i = 0; $i < 2; $i++) {
        $this->postJson(route('posts.boost', $post), powerPayload())->assertOk();
    }
    $feed = $this->get(route('home'))->assertOk();
    expect($feed->viewData('posts')->total())->toBe(3);
    expect($feed->viewData('posts')->pluck('feed_occurrence')->unique())->toHaveCount(3);
    expect($this->get(route('home', ['sort' => 'newest']))->assertOk()->viewData('posts')->total())->toBe(1);
    expect($this->get(route('profiles.show', $owner))->assertOk()->viewData('posts')->total())->toBe(1);
    $owner->forceFill(['profile_visibility' => 'private'])->save();
    expect($this->actingAs(User::factory()->create())->get(route('home'))->assertOk()->viewData('posts')->total())->toBe(0);
    $owner->forceFill(['profile_visibility' => 'public'])->save();
    $this->travel(24)->hours();
    expect($this->get(route('home'))->assertOk()->viewData('posts')->total())->toBe(1);
});

test('publishing a batch rewards a day once and failed uploads or edits give no rewards', function () {
    $member = User::factory()->create();
    $this->actingAs($member)->post(route('posts.store'), [
        'media' => [UploadedFile::fake()->image('one.png'), UploadedFile::fake()->image('two.png')],
    ])->assertSessionHasNoErrors();
    expect(PostingDay::count())->toBe(1)->and($member->fresh()->bonus_super_reactions)->toBe(1);
    $this->put(route('posts.update', Post::first()), ['title' => 'Edited'])->assertSessionHasNoErrors();
    $this->travel(3)->days();
    $this->post(route('posts.store'), ['media' => UploadedFile::fake()->create('bad.exe')])->assertSessionHasErrors();
    expect(PostingDay::count())->toBe(1);
    $this->post(route('posts.store'), ['media' => UploadedFile::fake()->image('three.png')])->assertSessionHasNoErrors();
    expect(PostingDay::count())->toBe(2)->and($member->fresh()->bonus_boosts)->toBe(1);
    Post::query()->delete();
    expect(PostingDay::count())->toBe(2);
});

test('fourteen nonconsecutive posting days grant rewards once extend premium and unlock a reviewed Corner request', function () {
    $member = User::factory()->create(['premium_expires_at' => now()->addDays(60)]);
    $originalExpiry = $member->premium_expires_at->copy();
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($member)->post(route('corners.store'), ['name' => 'My Corner', 'description' => 'Memes with cats'])->assertForbidden();
    for ($i = 0; $i < 16; $i++) {
        DB::transaction(function () use ($member) {
            $locked = User::lockForUpdate()->findOrFail($member->id);
            PostingRewards::record($locked, communityPost($locked));
            PostingRewards::record($locked, communityPost($locked));
        });
        $this->travel(2)->days();
    }
    expect(PostingDay::count())->toBe(14)->and(MemberNotification::where('kind', 'reward')->count())->toBe(14);
    expect($member->fresh()->premium_expires_at->equalTo($originalExpiry->addDays(17)))->toBeTrue();
    expect($member->fresh()->corner_unlocked_at)->not->toBeNull();
    $this->get(route('rewards.index'))->assertOk()->assertSee('14 / 14');
    $this->post(route('corners.store'), ['name' => 'My Corner', 'description' => 'Memes with cats'])->assertSessionHasNoErrors();
    $this->post(route('corners.store'), ['name' => 'Duplicate', 'description' => 'No second pending request'])->assertSessionHasErrors('corner');
    $corner = CornerRequest::sole();
    $this->get(route('corners.review'))->assertForbidden();
    $this->actingAs($admin)->get(route('corners.review'))->assertOk()->assertSee('My Corner');
    $this->patch(route('corners.update', $corner), ['status' => 'approved', 'review_note' => 'Saved for the Corner launch.'])->assertSessionHasNoErrors();
    expect($corner->fresh()->status)->toBe('approved');
    expect(MemberNotification::where('user_id', $member->id)->where('kind', 'corner_review')->count())->toBe(1);
    $this->patch(route('corners.update', $corner), ['status' => 'declined', 'review_note' => 'A second decision'])->assertSessionHasErrors('corner');
});

test('rewards respect midnight in the Philippines and roll back with publishing', function () {
    $member = User::factory()->create();
    $this->actingAs($member);
    $this->travelTo(now()->setTime(15, 59, 59));
    $this->post(route('posts.store'), ['media' => UploadedFile::fake()->image('before.png')])->assertSessionHasNoErrors();
    $this->travel(1)->seconds();
    $this->post(route('posts.store'), ['media' => UploadedFile::fake()->image('after.png')])->assertSessionHasNoErrors();
    expect(PostingDay::count())->toBe(2);
    $this->travel(1)->days();
    DB::beginTransaction();
    PostingRewards::record($member->fresh(), communityPost($member));
    DB::rollBack();
    expect(PostingDay::count())->toBe(2)->and(MemberNotification::where('kind', 'reward')->count())->toBe(2);
});

test('first ten regular reactions notify once even after dropping below ten', function () {
    $owner = User::factory()->create();
    $post = communityPost($owner);
    $actors = User::factory()->count(10)->create();
    foreach ($actors->take(9) as $actor) {
        Reaction::create(['user_id' => $actor->id, 'post_id' => $post->id, 'emoji' => Reaction::DEFAULT_EMOJIS[0]]);
    }
    $this->actingAs($actors->last());
    for ($i = 0; $i < 3; $i++) {
        $this->postJson(route('posts.react', $post), ['emoji' => Reaction::DEFAULT_EMOJIS[0]])->assertOk();
    }
    expect(MemberNotification::where('kind', 'reaction_milestone')->count())->toBe(1);
    expect($post->fresh()->ten_reactions_notified_at)->not->toBeNull();
});

test('replies stay within a post and notify the owner and replied-to member without self notifications', function () {
    $owner = User::factory()->create();
    $author = User::factory()->create();
    $responder = User::factory()->create();
    $post = communityPost($owner);
    $this->actingAs($author)->post(route('comments.store', $post), ['body' => 'The root'])->assertSessionHasNoErrors();
    $root = Comment::sole();
    $this->actingAs($responder)->post(route('comments.store', $post), ['body' => 'A reply', 'reply_to_id' => $root->id])->assertSessionHasNoErrors();
    $reply = Comment::latest('id')->first();
    expect($reply->parent_id)->toBe($root->id);
    expect(MemberNotification::where('user_id', $owner->id)->count())->toBe(2)
        ->and(MemberNotification::where('user_id', $author->id)->count())->toBe(1)
        ->and(MemberNotification::where('user_id', $responder->id)->count())->toBe(0);
    $this->post(route('comments.store', $post), ['body' => 'Nested reply', 'reply_to_id' => $reply->id])->assertSessionHasNoErrors();
    expect(Comment::latest('id')->first()->parent_id)->toBe($root->id);
    $this->get(route('posts.show', $post))->assertOk()->assertSee('A reply')->assertSee('View all 2 replies');
    $this->get(route('comments.replies', $root))->assertOk()->assertSee('Nested reply');
    $this->post(route('comments.store', communityPost($owner)), ['body' => 'Wrong post', 'reply_to_id' => $root->id])->assertSessionHasErrors('reply_to_id');
    $post->update(['comments_enabled' => false]);
    $this->post(route('comments.store', $post), ['body' => 'Closed', 'reply_to_id' => $root->id])->assertForbidden();
    $this->get(route('comments.replies', $root))->assertNotFound();
    $this->actingAs($owner)->get(route('comments.replies', $root))->assertOk();
});

test('only post owners heart and pin one comment and deleting a thread cleans its replies and pin', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $post = communityPost($owner);
    $root = $post->comments()->create(['user_id' => $member->id, 'body' => 'Root']);
    $reply = $post->comments()->create(['user_id' => $owner->id, 'body' => 'Reply', 'parent_id' => $root->id, 'reply_to_id' => $root->id]);
    $this->actingAs($member)->put(route('comments.pin', $root), ['pinned' => 1])->assertForbidden();
    $this->put(route('comments.heart', $root), ['hearted' => 1])->assertForbidden();
    $this->actingAs($owner)->put(route('comments.pin', $root), ['pinned' => 1])->assertSessionHasNoErrors();
    $this->put(route('comments.pin', $reply), ['pinned' => 1])->assertSessionHasNoErrors();
    expect($post->fresh()->pinned_comment_id)->toBe($reply->id);
    foreach ([1, 0, 1] as $hearted) {
        $this->put(route('comments.heart', $root), compact('hearted'))->assertSessionHasNoErrors();
    }
    expect($root->fresh()->hearted_by_owner)->toBeTrue();
    expect(MemberNotification::where('kind', 'comment_heart')->count())->toBe(1);
    $this->get(route('posts.show', $post))->assertOk()->assertSee('Loved by the post owner');
    $this->delete(route('comments.destroy', $root))->assertSessionHasNoErrors();
    expect(Comment::count())->toBe(0)->and($post->fresh()->pinned_comment_id)->toBeNull();
});

test('notification inbox is private and opening unavailable content marks only your notification read', function () {
    $member = User::factory()->create();
    $owner = User::factory()->create();
    $post = communityPost($owner);
    $notification = MemberNotification::create(['user_id' => $member->id, 'kind' => 'boost', 'title' => 'Only for me', 'message' => 'Private inbox', 'target_type' => 'post', 'target_id' => $post->id]);
    $this->actingAs($owner)->get(route('notifications.index'))->assertOk()->assertDontSee('Only for me');
    $this->post(route('notifications.open', $notification))->assertForbidden();
    $this->post(route('notifications.read-all'));
    expect($notification->fresh()->read_at)->toBeNull();
    $this->actingAs($member)->getJson(route('notifications.count'))->assertOk()->assertJsonPath('unread', 1);
    $owner->forceFill(['profile_visibility' => 'private'])->save();
    $this->post(route('notifications.open', $notification))->assertRedirect(route('notifications.index'));
    $this->getJson(route('notifications.count'))->assertJsonPath('unread', 0);
});

test('moderator warnings have role boundaries and are delivered privately', function () {
    $member = User::factory()->create();
    $moderator = User::factory()->create(['role' => 'moderator']);
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($member)->post(route('warnings.store', $moderator), ['message' => 'Not authorized'])->assertForbidden();
    $this->actingAs($moderator)->post(route('warnings.store', $admin), ['message' => 'Not authorized'])->assertForbidden();
    $this->post(route('warnings.store', $member), ['message' => 'Please stop posting duplicates.'])->assertSessionHasNoErrors();
    expect(MemberWarning::count())->toBe(1)->and(MemberNotification::where('kind', 'warning')->count())->toBe(1);
    $this->get(route('warnings.create', $member))->assertOk()->assertSee('Please stop');
    $this->actingAs($member)->get(route('warnings.mine'))->assertOk()->assertSee('Please stop');
    $this->get(route('warnings.create', $moderator))->assertForbidden();
});

test('administrators manage replaceable GIFs and sounds while members cannot change the catalog', function () {
    $type = SuperReactionType::first();
    $this->actingAs(User::factory()->create())->get(route('super-catalog.index'))->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('super-catalog.index'))->assertOk()->assertSee('Absolutely Stellar');
    $this->post(route('super-catalog.store'), ['name' => 'Missing assets', 'is_active' => 1])->assertSessionHasErrors(['gif', 'sound']);
    $this->post(route('super-catalog.store'), [
        'name' => 'Custom reaction', 'is_active' => 1,
        'gif' => new UploadedFile(public_path('super-reactions/demo/love.gif'), 'love.gif', 'image/gif', null, true),
        'sound' => new UploadedFile(public_path('super-reactions/demo/love.wav'), 'love.wav', 'audio/wav', null, true),
    ])->assertSessionHasNoErrors();
    $custom = SuperReactionType::latest('id')->first();
    Storage::disk('public')->assertExists($custom->gif_path);
    Storage::disk('public')->assertExists($custom->sound_path);
    $this->put(route('super-catalog.update', $type), ['name' => 'Retired demo', 'is_active' => 0])->assertSessionHasNoErrors();
    expect($type->fresh()->is_active)->toBeFalse();
});

test('demo reset refuses a remote host even in a local application environment', function () {
    $member = User::factory()->create();
    $post = communityPost($member);
    $previous = ['database.default' => config('database.default'), 'database.connections.mysql.host' => config('database.connections.mysql.host')];
    config(['database.default' => 'mysql', 'database.connections.mysql.host' => 'shared-database.example.invalid']);
    try {
        $this->artisan('preyson:reset-demo', ['--force' => true])->assertFailed();
    } finally {
        config($previous);
    }
    expect(User::whereKey($member->id)->exists())->toBeTrue()->and(Post::whereKey($post->id)->exists())->toBeTrue();
});
