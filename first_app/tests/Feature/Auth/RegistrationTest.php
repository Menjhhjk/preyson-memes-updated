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

    $response->assertOk()->assertSee('registration.js')->assertSee('registration.css')
        ->assertSee('data-count="username"', false)->assertSee('data-count="password"', false)
        ->assertSee('One capital letter')->assertSee('One number')->assertSee('One special character')
        ->assertSee('At least 8 characters');
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        ...solveColorCaptcha($this, 'register'),
        'terms' => '1',
        'username' => 'test_member',
        'email' => 'test@example.com',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
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
        'email' => 'demo@fictional.invalid', 'password' => 'Test-password1!', 'password_confirmation' => 'Test-password1!',
    ])->assertSessionHasErrors('username')->assertSessionDoesntHaveErrors(['name', 'surname', 'captcha_tiles']);
    expect(User::count())->toBe(0);
});

test('registration accepts six character usernames without personal name fields', function () {
    $this->post(route('register.store'), [
        ...solveColorCaptcha($this, 'register'), 'terms' => '1', 'username' => 'Abcdef',
        'email' => 'demo@fictional.invalid', 'password' => 'Test-password1!', 'password_confirmation' => 'Test-password1!',
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
        'password' => 'Test-password1!', 'password_confirmation' => 'Test-password1!',
    ])->assertSessionHasErrors(['username', 'email', 'terms', 'captcha_tiles']);
    expect(User::count())->toBe(1);
});

test('registration requires each password condition on the server', function (string $password, string $message) {
    $this->post(route('register.store'), [
        ...solveColorCaptcha($this, 'register'), 'terms' => '1',
        'username' => 'test_member', 'email' => 'test@fictional.invalid',
        'password' => $password, 'password_confirmation' => $password,
    ])->assertSessionHasErrors('password');
    expect(implode(' ', session('errors')->get('password')))->toContain($message);
    expect(User::count())->toBe(0);
    $this->assertGuest();
})->with([
    'too short' => ['Ab1!', 'at least 8 characters'],
    'no capital' => ['password1!', 'capital letter'],
    'no number' => ['Password!!', 'one number'],
    'no special character' => ['Password12', 'special character'],
    'whitespace is not special' => ['Password1 ', 'special character'],
]);

test('registration reports all missing password conditions together', function () {
    $this->post(route('register.store'), [
        ...solveColorCaptcha($this, 'register'), 'terms' => '1',
        'username' => 'test_member', 'email' => 'test@fictional.invalid',
        'password' => 'short', 'password_confirmation' => 'short',
    ])->assertSessionHasErrors('password');
    $messages = implode(' ', session('errors')->get('password'));
    expect($messages)->toContain('at least 8 characters', 'capital letter', 'one number', 'special character');
});

test('registration accepts passwords meeting the listed rules', function (string $password) {
    $this->post(route('register.store'), [
        ...solveColorCaptcha($this, 'register'), 'terms' => '1',
        'username' => 'test_member', 'email' => 'test@fictional.invalid',
        'password' => $password, 'password_confirmation' => $password,
    ])->assertSessionHasNoErrors()->assertRedirect('/dashboard');
    $this->assertAuthenticated();
})->with(['Password1!', 'PASSWORD1!', 'Ábcde1!😊']);
