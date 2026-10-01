<?php

use App\Models\Comment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/CommunityHelpers.php';

beforeEach(fn () => Storage::fake('public'));

test('members comment while guests can read and are invited to sign in', function () {
    $post = communityPost(User::factory()->create());
    $member = User::factory()->create();
    $this->post(route('comments.store', $post), ['body' => 'Hello'])->assertRedirect(route('login'));
    $this->get(route('posts.show', $post))->assertOk()->assertSee('Sign in')->assertSee('No comments yet.');
    $this->actingAs($member)->post(route('comments.store', $post), ['body' => "First line\nSecond line"])
        ->assertSessionHasNoErrors()->assertRedirect(route('posts.show', ['post' => $post, 'page' => 1]).'#comments');
    $this->assertDatabaseHas('comments', ['post_id' => $post->id, 'user_id' => $member->id, 'body' => "First line\nSecond line"]);
    $this->get(route('posts.show', $post))->assertOk()->assertSee('First line')->assertSee('Second line');
});

test('comments validate all text and safely escape markup', function () {
    $post = communityPost(User::factory()->create());
    $this->actingAs(User::factory()->create());
    foreach (['', '   ', str_repeat('a', 2001)] as $body) {
        $this->post(route('comments.store', $post), compact('body'))->assertSessionHasErrors('body');
    }
    $body = '<script>alert("test")</script> & hello';
    $this->post(route('comments.store', $post), compact('body'))->assertSessionHasNoErrors();
    $this->get(route('posts.show', $post))->assertSee($body)->assertDontSee($body, false);
});

test('authors edit and authors post owners or staff delete comments', function () {
    $owner = User::factory()->create();
    $author = User::factory()->create();
    $stranger = User::factory()->create();
    $moderator = User::factory()->create(['role' => 'moderator']);
    $post = communityPost($owner);
    $comment = $post->comments()->create(['user_id' => $author->id, 'body' => 'Original']);
    $this->actingAs($stranger)->patch(route('comments.update', $comment), ['body' => 'Stolen'])->assertForbidden();
    $this->delete(route('comments.destroy', $comment))->assertForbidden();
    $this->actingAs($owner)->patch(route('comments.update', $comment), ['body' => 'Owner rewrite'])->assertForbidden();
    $this->actingAs($author)->patch(route('comments.update', $comment), ['body' => 'Edited'])->assertSessionHasNoErrors();
    expect($comment->fresh()->body)->toBe('Edited')->and($comment->fresh()->edited_at)->not->toBeNull();
    $this->actingAs($owner)->delete(route('comments.destroy', $comment))->assertSessionHasNoErrors();
    foreach ([$author, $moderator] as $deleter) {
        $comment = $post->comments()->create(['user_id' => $author->id, 'body' => 'Delete me']);
        $this->actingAs($deleter)->delete(route('comments.destroy', $comment))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
    }
});

test('comment controls apply to uploads and edits and preserve closed discussions', function () {
    $owner = User::factory()->create();
    $author = User::factory()->create();
    $this->actingAs($owner)->post(route('posts.store'), [
        'media' => [UploadedFile::fake()->image('a.png'), UploadedFile::fake()->image('b.png')], 'comments_enabled' => 0,
    ])->assertSessionHasNoErrors();
    expect($owner->posts()->where('comments_enabled', false)->count())->toBe(2);
    $post = $owner->posts()->first();
    $this->get(route('dashboard'))->assertSee('Allow comments');
    $this->get(route('posts.edit', $post))->assertSee('Allow comments');
    $this->put(route('posts.update', $post), ['title' => 'Closed unchanged'])->assertSessionHasNoErrors();
    expect($post->fresh()->comments_enabled)->toBeFalse();
    $this->put(route('posts.update', $post), ['title' => 'Open', 'comments_enabled' => 1])->assertSessionHasNoErrors();
    $this->actingAs($author)->post(route('comments.store', $post), ['body' => 'Retain this conversation'])->assertSessionHasNoErrors();
    $comment = Comment::sole();
    $this->actingAs($owner)->put(route('posts.update', $post), ['title' => 'Closed', 'comments_enabled' => 0])->assertSessionHasNoErrors();
    $this->actingAs($author)->post(route('comments.store', $post), ['body' => 'Nope'])->assertForbidden();
    $this->patch(route('comments.update', $comment), ['body' => 'Nope'])->assertForbidden();
    $this->get(route('posts.show', $post))->assertSee('turned comments off')->assertDontSee('Retain this conversation');
    $this->get(route('comments.show', $comment))->assertNotFound();
    $this->actingAs($owner)->get(route('posts.show', $post))->assertSee('Retain this conversation');
    $this->put(route('posts.update', $post), ['title' => 'Reopened', 'comments_enabled' => 1]);
    $this->actingAs($author)->get(route('posts.show', $post))->assertSee('Retain this conversation');
    expect(Comment::count())->toBe(1);
});

test('premium comment badges use only the star and link to the member profile', function () {
    $member = User::factory()->create(['premium_expires_at' => now()->addDays(2)]);
    $post = communityPost(User::factory()->create());
    $post->comments()->create(['user_id' => $member->id, 'body' => 'A star in the comments']);
    $html = $this->get(route('posts.show', $post))->assertOk()->getContent();
    expect($html)->toContain('class="badge premium comment-premium" role="img" aria-label="Premium member" title="Premium member">✦</span>')
        ->toContain(route('profiles.show', $member));
    $member->forceFill(['premium_expires_at' => now()->subSecond()])->save();
    $this->get(route('posts.show', $post))->assertDontSee('comment-premium', false);
});

test('administrator ZIP imports apply the comment setting to every imported post', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $path = tempnam(sys_get_temp_dir(), 'community-zip-');
    try {
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $bytes = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        $zip->addFromString('one.gif', $bytes);
        $zip->addFromString('two.gif', $bytes);
        $zip->close();
        $upload = UploadedFile::fake()->createWithContent('comments.zip', file_get_contents($path));
    } finally {
        unlink($path);
    }
    $this->actingAs($admin)->post(route('posts.zip'), [
        'media' => $upload, 'comments_enabled' => 0,
    ])->assertSessionHasNoErrors();
    expect($admin->posts()->count())->toBe(2)->and($admin->posts()->where('comments_enabled', false)->count())->toBe(2);
});

test('comment permalinks locate paginated discussions and rate limits stop floods', function () {
    $author = User::factory()->create();
    $post = communityPost($author);
    foreach (range(1, 21) as $number) {
        $comment = $post->comments()->create(['user_id' => $author->id, 'body' => 'Comment '.$number]);
    }
    $this->get(route('comments.show', $comment))->assertRedirect(route('posts.show', ['post' => $post, 'page' => 2]).'#comments');
    $this->get(route('posts.show', ['post' => $post, 'page' => 2]))->assertOk()->assertSee('Comment 21');
    $this->actingAs($author);
    foreach (range(1, 12) as $number) {
        $this->post(route('comments.store', $post), ['body' => 'Flood '.$number])->assertRedirect();
    }
    $this->post(route('comments.store', $post), ['body' => 'Too many'])->assertStatus(429);
});
