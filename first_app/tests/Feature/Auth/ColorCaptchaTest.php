<?php

use App\Models\User;
use App\Services\ColorCaptcha;
use Illuminate\Support\Facades\Notification;

require_once __DIR__.'/CaptchaTestHelpers.php';

function colorCheckRegistration(): array
{
    return [
        'username' => 'demo_member',
        'email' => 'demo@this-domain-does-not-exist.invalid',
        'password' => 'Demo-password1!', 'password_confirmation' => 'Demo-password1!', 'terms' => '1',
    ];
}

test('color grids contain nine labeled tiles and between two and six close matches', function () {
    for ($i = 0; $i < 20; $i++) {
        $challenge = $this->get(route('login'))->assertOk()->viewData('captcha');
        $matches = array_filter($challenge['tiles'], fn ($tile) => $tile['name'] === $challenge['target']);

        expect($challenge['tiles'])->toHaveCount(9);
        expect(count($matches))->toBeGreaterThanOrEqual(2)->toBeLessThanOrEqual(6);
        expect($challenge)->not->toHaveKey('answers');
        expect($challenge['tiles'][0])->not->toHaveKey('correct');
    }

    expect(count(session(ColorCaptcha::SESSION_KEY)))->toBe(12);
});

test('login rejects a missing captcha even when the password is correct', function () {
    $user = User::factory()->create();
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('captcha_tiles');
    $this->assertGuest();
});

test('wrong selections consume a captcha so the answer cannot be replayed', function () {
    $user = User::factory()->create();
    $answer = solveColorCaptcha($this);
    $this->post(route('login.store'), [...$answer, 'captcha_tiles' => range(0, 8), 'email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('captcha_tiles');
    $this->post(route('login.store'), [...$answer, 'email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('captcha_tiles');
    $this->assertGuest();
});

test('successful captcha answers cannot be reused after an incorrect password', function () {
    $user = User::factory()->create();
    $answer = solveColorCaptcha($this);
    $this->post(route('login.store'), [...$answer, 'email' => $user->email, 'password' => 'incorrect'])
        ->assertSessionHasErrors('email');
    $this->post(route('login.store'), [...$answer, 'email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('captcha_tiles');
    $this->assertGuest();
});

test('captcha expires after ten minutes', function () {
    $user = User::factory()->create();
    $answer = solveColorCaptcha($this);
    $this->travel(10)->minutes();
    $this->post(route('login.store'), [...$answer, 'email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('captcha_tiles');
    $this->assertGuest();
});

test('captcha from another session or another form purpose is rejected', function () {
    $answer = solveColorCaptcha($this);
    $this->post(route('register.store'), [...colorCheckRegistration(), ...$answer])
        ->assertSessionHasErrors('captcha_tiles');
    $answer = solveColorCaptcha($this, 'register');
    session()->forget(ColorCaptcha::SESSION_KEY);
    $this->post(route('register.store'), [...colorCheckRegistration(), ...$answer])
        ->assertSessionHasErrors('captcha_tiles');
    expect(User::count())->toBe(0);
});

test('opening a second tab does not invalidate the first challenge', function () {
    $user = User::factory()->create();
    $firstTab = solveColorCaptcha($this);
    $secondTab = solveColorCaptcha($this);
    expect($firstTab['captcha_id'])->not->toBe($secondTab['captcha_id']);
    $this->post(route('login.store'), [...$firstTab, 'email' => $user->email, 'password' => 'password'])
        ->assertSessionHasNoErrors()->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($user);
});

test('refresh replaces just the requested challenge without disclosing the answer', function () {
    $old = solveColorCaptcha($this);
    $otherTab = solveColorCaptcha($this);
    $response = $this->postJson(route('captcha.refresh'), ['purpose' => 'login', 'previous_id' => $old['captcha_id']])
        ->assertOk()->assertJsonCount(9, 'tiles')->assertJsonMissingPath('answers')->assertJsonMissingPath('tiles.0.correct');
    expect($response->json('id'))->not->toBe($old['captcha_id']);
    expect(session(ColorCaptcha::SESSION_KEY.'.'.$old['captcha_id']))->toBeNull();
    expect(session(ColorCaptcha::SESSION_KEY.'.'.$otherTab['captcha_id']))->not->toBeNull();
});

test('registration requires explicit policy agreement', function () {
    $this->post(route('register.store'), [...colorCheckRegistration(), ...solveColorCaptcha($this, 'register'), 'terms' => '0'])
        ->assertSessionHasErrors('terms');
    expect(User::count())->toBe(0);
});

test('registration accepts a fictional email and records consent without sending verification', function () {
    Notification::fake();
    $this->post(route('register.store'), [...colorCheckRegistration(), ...solveColorCaptcha($this, 'register')])
        ->assertSessionHasNoErrors()->assertRedirect('/dashboard');
    $user = User::sole();
    expect($user->terms_accepted_at)->not->toBeNull();
    expect($user->terms_version)->toBe('2026-10-01');
    $this->assertAuthenticatedAs($user);
    Notification::assertNothingSent();
});

test('demo policies are readable without signing in', function () {
    $this->get(route('policies'))->assertOk()->assertSee('No payment is collected')->assertSee('Privacy notice');
});
