<?php

use App\Models\User;

require_once __DIR__.'/CaptchaTestHelpers.php';

test('live feedback accepts valid usernames and made up emails without creating accounts', function () {
    foreach (['username' => 'New_User', 'email' => 'demo@fictional.invalid'] as $field => $value) {
        $this->postJson(route('registration.check'), compact('field', 'value'))
            ->assertOk()->assertJson(['valid' => true, 'format_valid' => true, 'messages' => []])
            ->assertHeader('Cache-Control', 'no-store, private');
    }
    expect(User::count())->toBe(0);
});

test('live feedback detects duplicate account identifiers regardless of case', function () {
    User::factory()->create(['username' => 'taken_user', 'email' => 'taken@fictional.invalid']);
    foreach (['username' => ' TAKEN_USER ', 'email' => 'TAKEN@FICTIONAL.INVALID'] as $field => $value) {
        $this->postJson(route('registration.check'), compact('field', 'value'))
            ->assertOk()->assertJsonPath('valid', false)->assertJsonPath('format_valid', true)
            ->assertJsonPath('messages.0', 'This '.$field.' is already in use.');
    }
    expect(User::count())->toBe(1);
});

test('live feedback applies the signup format and length rules', function (string $field, string $value) {
    $this->postJson(route('registration.check'), compact('field', 'value'))
        ->assertOk()->assertJsonPath('valid', false)->assertJsonPath('format_valid', false);
})->with([
    ['username', 'short'],
    ['username', 'bad name'],
    ['username', str_repeat('a', 51)],
    ['email', 'not-an-email'],
    ['email', 'demo@bad..domain'],
]);

test('live feedback only accepts supported fields and bounded string values', function () {
    $this->postJson(route('registration.check'), ['field' => 'password', 'value' => 'not-sent-by-the-form'])
        ->assertUnprocessable()->assertJsonValidationErrors('field');
    $this->postJson(route('registration.check'), ['field' => 'email', 'value' => ['invalid']])
        ->assertUnprocessable()->assertJsonValidationErrors('value');
    $this->postJson(route('registration.check'), ['field' => 'email', 'value' => str_repeat('a', 256)])
        ->assertUnprocessable()->assertJsonValidationErrors('value');
});

test('live feedback requests do not consume the signup captcha', function () {
    $captcha = solveColorCaptcha($this, 'register');
    $this->postJson(route('registration.check'), ['field' => 'username', 'value' => 'fresh_user'])->assertOk();
    $this->post(route('register.store'), [
        ...$captcha, 'terms' => '1', 'username' => 'fresh_user', 'email' => 'fresh@fictional.invalid',
        'password' => 'Password1!', 'password_confirmation' => 'Password1!',
    ])->assertSessionHasNoErrors();
    $this->assertAuthenticated();
});
