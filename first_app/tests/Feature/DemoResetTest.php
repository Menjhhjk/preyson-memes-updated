<?php

use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

test('demo reset keeps the original administrator and removes member data', function () {
    Storage::fake('public');
    $admin = User::factory()->create(['email' => 'admin@gmail.com', 'is_admin' => true, 'role' => 'admin']);
    $member = User::factory()->create();
    Storage::disk('public')->put('memes/old.png', 'old');
    Post::create(['user_id' => $member->id, 'title' => 'Old post', 'media_path' => 'memes/old.png', 'media_type' => 'image']);
    $this->artisan('preyson:reset-demo', ['--force' => true])->assertSuccessful();
    expect(User::sole()->id)->toBe($admin->id)->and(Post::count())->toBe(0);
    expect(Hash::check('pass@123', User::sole()->password))->toBeTrue();
    Storage::disk('public')->assertMissing('memes/old.png');
});

test('administrators cannot delete themselves through the member profile route', function () {
    $admin = User::factory()->create(['is_admin' => true, 'role' => 'admin']);
    $this->actingAs($admin)->delete(route('profile.destroy'), ['password' => 'password'])->assertForbidden();
    expect($admin->fresh())->not->toBeNull();
});

test('self deletion also removes posts and media', function () {
    Storage::fake('public');
    $member = User::factory()->create(['avatar_path' => 'avatars/old.png']);
    Storage::disk('public')->put('avatars/old.png', 'old');
    Storage::disk('public')->put('memes/old.png', 'old');
    Post::create(['user_id' => $member->id, 'title' => 'Old post', 'media_path' => 'memes/old.png', 'media_type' => 'image']);
    $this->actingAs($member)->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect(route('home'));
    $this->assertGuest();
    expect(User::count())->toBe(0)->and(Post::count())->toBe(0);
    Storage::disk('public')->assertMissing(['avatars/old.png', 'memes/old.png']);
});
