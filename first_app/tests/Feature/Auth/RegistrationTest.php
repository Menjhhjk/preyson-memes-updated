<?php

use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Laravel\Fortify\Features;

require_once __DIR__.'/CaptchaTestHelpers.php';

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        ...solveColorCaptcha($this, 'register'),
        'terms' => '1',
        'username' => 'test_member',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('registration reports every field error together with the captcha error', function () {
    $this->from(route('register'))->post(route('register.store'), [
        'username' => 'short', 'email' => 'not-an-email',
        'password' => 'tiny', 'password_confirmation' => 'different', 'terms' => '0',
    ])->assertRedirect(route('register'))
        ->assertSessionHasErrors(['username', 'email', 'password', 'terms', 'captcha_tiles']);
    $messages = session('errors')->all();
    $response = $this->withCookie(session()->getName(), session()->getId())->get(route('register'))->assertOk()->assertSee('novalidate', false);
    foreach ($messages as $message) {
        $response->assertSeeText($message);
    }
    expect(User::count())->toBe(0);
    $this->assertGuest();
});

test('registration rejects five character usernames', function () {
    $this->post(route('register.store'), [
        ...solveColorCaptcha($this, 'register'), 'terms' => '1', 'username' => 'abcde',
        'email' => 'demo@fictional.invalid', 'password' => 'test-password', 'password_confirmation' => 'test-password',
    ])->assertSessionHasErrors('username')->assertSessionDoesntHaveErrors(['name', 'surname', 'captcha_tiles']);
    expect(User::count())->toBe(0);
});

test('registration accepts six character usernames without personal name fields', function () {
    $this->post(route('register.store'), [
        ...solveColorCaptcha($this, 'register'), 'terms' => '1', 'username' => 'Abcdef',
        'email' => 'demo@fictional.invalid', 'password' => 'test-password', 'password_confirmation' => 'test-password',
    ])->assertSessionHasNoErrors()->assertRedirect('/dashboard');
    expect(User::sole()->username)->toBe('abcdef');
    expect(Schema::hasColumn('users', 'name'))->toBeFalse();
    expect(Schema::hasColumn('users', 'surname'))->toBeFalse();
});

test('registration does not expose personal name inputs and preserves all duplicate errors', function () {
    User::factory()->create(['username' => 'taken_user', 'email' => 'taken@fictional.invalid']);
    $this->get(route('register'))->assertDontSee('name="name"', false)->assertDontSee('name="surname"', false)->assertSee('minlength="6"', false);
    $this->post(route('register.store'), [
        'username' => 'TAKEN_USER', 'email' => 'TAKEN@FICTIONAL.INVALID',
        'password' => 'test-password', 'password_confirmation' => 'test-password',
    ])->assertSessionHasErrors(['username', 'email', 'terms', 'captcha_tiles']);
    expect(User::count())->toBe(1);
});
