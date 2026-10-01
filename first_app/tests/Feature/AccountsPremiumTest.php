<?php

use App\Models\Post;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

function managedAccountData(array $overrides = []): array
{
    return [...[
        'username' => 'meme_member',
        'email' => 'meme@example.test', 'role' => 'member',
        'password' => 'test-password', 'password_confirmation' => 'test-password',
    ], ...$overrides];
}

function accountAdministrator(): User
{
    return User::factory()->create(['role' => 'admin', 'is_admin' => true]);
}

test('account and profile forms use usernames without personal name fields', function () {
    $admin = accountAdministrator();
    $this->actingAs($admin);
    foreach ([route('accounts.create'), route('accounts.edit', $admin), route('profile.edit')] as $url) {
        $this->get($url)->assertOk()->assertDontSee('name="name"', false)->assertDontSee('name="surname"', false);
    }
});

test('admin creation and profile changes enforce six character usernames', function () {
    $admin = accountAdministrator();
    $this->actingAs($admin)->post(route('accounts.store'), managedAccountData(['username' => 'short']))
        ->assertSessionHasErrors('username');
    $this->post(route('accounts.store'), managedAccountData(['username' => 'sixsix']))->assertSessionHasNoErrors();
    $member = User::where('username', 'sixsix')->sole();
    $this->put(route('accounts.update', $member), [...managedAccountData(['username' => 'small']), 'password' => ''])
        ->assertSessionHasErrors('username');
    $this->actingAs($member)->patch(route('profile.update'), ['username' => 'short'])->assertSessionHasErrors('username');
    $this->patch(route('profile.update'), ['username' => 'new_six'])->assertSessionHasNoErrors();
    expect($member->fresh()->username)->toBe('new_six');
});

test('existing short usernames may be retained but cannot be changed to another short username', function () {
    $admin = User::factory()->create(['username' => 'admin', 'email' => 'admin@gmail.com', 'is_admin' => true, 'role' => 'admin']);
    $this->actingAs($admin)->patch(route('profile.update'), ['username' => 'admin'])->assertSessionHasNoErrors();
    $this->patch(route('profile.update'), ['username' => 'short'])->assertSessionHasErrors('username');
    expect($admin->fresh()->username)->toBe('admin');
});

test('account pages require login and every operation requires an administrator', function () {
    $account = User::factory()->create();
    $this->get(route('accounts.index'))->assertRedirect(route('login'));

    foreach (['member', 'moderator'] as $role) {
        $this->actingAs(User::factory()->create(['role' => $role]))
            ->get(route('accounts.index'))->assertForbidden();
        $this->get(route('accounts.create'))->assertForbidden();
        $this->get(route('accounts.edit', $account))->assertForbidden();
        $this->post(route('accounts.store'), managedAccountData())->assertForbidden();
        $this->put(route('accounts.update', $account), managedAccountData())->assertForbidden();
        $this->delete(route('accounts.destroy', $account))->assertForbidden();
    }
    expect($account->fresh())->not->toBeNull();
});

test('admins create simulated accounts with a hashed password and moderator role', function () {
    $this->actingAs(accountAdministrator())->get(route('accounts.create'))->assertOk();
    $this->post(route('accounts.store'), managedAccountData(['role' => 'moderator']))
        ->assertSessionHasNoErrors()->assertRedirect(route('accounts.index'));
    $account = User::where('username', 'meme_member')->sole();
    expect($account->email)->toBe('meme@example.test')
        ->and($account->role)->toBe('moderator')
        ->and($account->is_admin)->toBeFalse()
        ->and($account->canModerate())->toBeTrue()
        ->and(Hash::check('test-password', $account->password))->toBeTrue();
    $this->get(route('accounts.index'))->assertOk()->assertSee('meme@example.test');
    $this->get(route('accounts.edit', $account))->assertOk();
});

test('account uniqueness is case insensitive and password length is enforced', function () {
    User::factory()->create(['username' => 'MEME_MEMBER', 'email' => 'MEME@EXAMPLE.TEST']);
    $this->actingAs(accountAdministrator())->post(route('accounts.store'), managedAccountData([
        'password' => 'short', 'password_confirmation' => 'short',
    ]))->assertSessionHasErrors(['username', 'email', 'password']);
});

test('admin updates preserve blank passwords and revoke sessions after role changes', function () {
    $account = User::factory()->create(['username' => 'meme_member', 'email' => 'meme@example.test']);
    $oldPassword = $account->password;
    DB::table('sessions')->insert(['id' => 'member-session', 'user_id' => $account->id, 'payload' => '', 'last_activity' => time()]);
    DB::table('password_reset_tokens')->insert(['email' => $account->email, 'token' => 'old-token', 'created_at' => now()]);
    $this->actingAs(accountAdministrator())->put(route('accounts.update', $account), managedAccountData([
        'role' => 'moderator', 'password' => '', 'password_confirmation' => '',
    ]))->assertSessionHasNoErrors()->assertRedirect(route('accounts.index'));
    expect($account->fresh()->role)->toBe('moderator')->and($account->fresh()->password)->toBe($oldPassword);
    $this->assertDatabaseMissing('sessions', ['id' => 'member-session']);
    $this->assertDatabaseMissing('password_reset_tokens', ['email' => $account->email]);
});

test('administrators cannot delete or demote themselves and the main admin is protected', function () {
    $admin = accountAdministrator();
    $protected = User::factory()->create(['email' => 'admin@gmail.com', 'role' => 'admin', 'is_admin' => true]);
    $this->actingAs($admin)->delete(route('accounts.destroy', $admin))->assertSessionHasErrors('account');
    $this->put(route('accounts.update', $admin), managedAccountData())->assertSessionHasErrors('role');
    $this->delete(route('accounts.destroy', $protected))->assertSessionHasErrors('account');
    $this->put(route('accounts.update', $protected), managedAccountData(['role' => 'admin']))->assertSessionHasErrors('email');
    $this->put(route('accounts.update', $protected), managedAccountData(['email' => 'admin@gmail.com']))->assertSessionHasErrors('email');
    expect($admin->fresh()->isAdmin())->toBeTrue()->and($protected->fresh()->email)->toBe('admin@gmail.com');
});

test('deleting an account cleans up its posts media avatar sessions and reset tokens', function () {
    Storage::fake('public');
    $account = User::factory()->create(['avatar_path' => 'avatars/old.png']);
    Storage::disk('public')->put('avatars/old.png', 'avatar');
    Storage::disk('public')->put('memes/old.png', 'meme');
    $post = Post::create(['user_id' => $account->id, 'title' => 'Delete me', 'media_path' => 'memes/old.png', 'media_type' => 'image']);
    DB::table('sessions')->insert(['id' => 'deleted-session', 'user_id' => $account->id, 'payload' => '', 'last_activity' => time()]);
    DB::table('password_reset_tokens')->insert(['email' => $account->email, 'token' => 'old-token', 'created_at' => now()]);

    $this->actingAs(accountAdministrator())->delete(route('accounts.destroy', $account))->assertRedirect(route('accounts.index'));
    $this->assertModelMissing($account);
    $this->assertModelMissing($post);
    $this->assertDatabaseMissing('sessions', ['id' => 'deleted-session']);
    $this->assertDatabaseMissing('password_reset_tokens', ['email' => $account->email]);
    Storage::disk('public')->assertMissing(['avatars/old.png', 'memes/old.png']);
});

test('members can open their profile and replace or remove a profile picture', function () {
    Storage::fake('public');
    $member = User::factory()->create(['avatar_path' => 'avatars/old.png']);
    Storage::disk('public')->put('avatars/old.png', 'old avatar');
    $data = managedAccountData(['password' => '', 'password_confirmation' => '']);

    $this->actingAs($member)->get(route('profile.edit'))->assertOk()->assertSee('Your profile');
    $this->put(route('profile.update'), [...$data, 'avatar' => UploadedFile::fake()->image('picture.png')])
        ->assertSessionHasNoErrors()->assertRedirect(route('profile.edit'));
    $path = $member->fresh()->avatar_path;
    Storage::disk('public')->assertExists($path);
    Storage::disk('public')->assertMissing('avatars/old.png');

    $this->put(route('profile.update'), [...$data, 'remove_avatar' => 1])->assertSessionHasNoErrors();
    expect($member->fresh()->avatar_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

test('profile updates cannot change a role and reject unsafe or oversized avatars', function () {
    $member = User::factory()->create();
    $data = managedAccountData(['password' => '', 'password_confirmation' => '']);
    $this->actingAs($member)->put(route('profile.update'), [...$data, 'role' => 'admin', 'is_admin' => true])
        ->assertSessionHasNoErrors();
    expect($member->fresh()->isAdmin())->toBeFalse();
    $this->put(route('profile.update'), [...$data, 'avatar' => UploadedFile::fake()->create('script.svg', 1, 'image/svg+xml')])
        ->assertSessionHasErrors('avatar');
    $this->put(route('profile.update'), [...$data, 'avatar' => UploadedFile::fake()->image('large.png')->size(2049)])
        ->assertSessionHasErrors('avatar');
});

test('changing a profile password requires the current password and revokes other sessions', function () {
    $member = User::factory()->create();
    DB::table('sessions')->insert(['id' => 'other-session', 'user_id' => $member->id, 'payload' => '', 'last_activity' => time()]);
    DB::table('password_reset_tokens')->insert(['email' => $member->email, 'token' => 'old-token', 'created_at' => now()]);
    $this->actingAs($member)->put(route('profile.update'), managedAccountData())
        ->assertSessionHasErrors('current_password');
    $this->put(route('profile.update'), [...managedAccountData(), 'current_password' => 'incorrect'])
        ->assertSessionHasErrors('current_password');
    $this->put(route('profile.update'), [...managedAccountData(), 'current_password' => 'password'])
        ->assertSessionHasNoErrors()->assertRedirect(route('profile.edit'));
    expect(Hash::check('test-password', $member->fresh()->password))->toBeTrue();
    $this->assertDatabaseMissing('sessions', ['id' => 'other-session']);
    $this->assertDatabaseMissing('password_reset_tokens', ['email' => $member->email]);
    $this->assertAuthenticatedAs($member);
});

test('checkout accepts only a name and activates exactly thirty days without stacking', function () {
    $this->freezeSecond();
    $member = User::factory()->create();
    $this->actingAs($member)->get(route('premium.index'))->assertOk()
        ->assertSee('Oops! This isn’t an actual subscription, alright?')
        ->assertDontSee('name="card_number"', false);
    $this->post(route('premium.checkout'), ['name' => 'Meme fan', 'intent' => 'subscribe'])
        ->assertSessionHasNoErrors()->assertRedirect(route('premium.index'));
    $expiry = $member->fresh()->premium_expires_at;
    expect($expiry->equalTo(now()->addDays(30)))->toBeTrue()
        ->and($member->fresh()->postLimit())->toBe(30);
    $this->travel(5)->days();
    $this->post(route('premium.checkout'), ['name' => 'Meme fan', 'intent' => 'subscribe'])->assertSessionHasNoErrors();
    expect($member->fresh()->premium_expires_at->equalTo($expiry))->toBeTrue();
    $this->travel(26)->days();
    expect($member->fresh()->hasPremium())->toBeFalse()->and($member->fresh()->postLimit())->toBe(6);
    $this->post(route('premium.checkout'), ['name' => 'Meme fan', 'intent' => 'subscribe'])->assertSessionHasNoErrors();
    expect($member->fresh()->premium_expires_at->equalTo(now()->addDays(30)))->toBeTrue();
});

test('donations are simulated and invalid checkouts cannot activate premium', function () {
    $member = User::factory()->create();
    $this->actingAs($member)->post(route('premium.checkout'), ['intent' => 'subscribe'])->assertSessionHasErrors('name');
    $this->post(route('premium.checkout'), ['name' => 'Meme fan', 'intent' => 'charge'])->assertSessionHasErrors('intent');
    $this->post(route('premium.checkout'), ['name' => 'Meme fan', 'intent' => 'donate'])->assertSessionHasNoErrors();
    expect($member->fresh()->premium_expires_at)->toBeNull();
});
test('admin navigation cannot consume the premium checkout rate limit', function () {
    $admin = User::factory()->create(['is_admin' => true, 'role' => 'admin']);
    $this->actingAs($admin);
    for ($i = 0; $i < 8; $i++) {
        $this->get(route('accounts.index'))->assertOk();
    }
    $this->post(route('premium.checkout'), ['name' => 'Admin', 'intent' => 'subscribe'])->assertSessionHasNoErrors()->assertRedirect(route('premium.index'));
    expect($admin->fresh()->hasPremium())->toBeTrue();
});
