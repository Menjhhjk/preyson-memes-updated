<?php

use App\Services\ColorCaptcha;
use Tests\TestCase;

// Tests solve actual server-created challenges; application validation remains enabled.
function solveColorCaptcha(TestCase $testCase, string $purpose = 'login'): array
{
    $response = $testCase->get(route($purpose))->assertOk();
    $challenge = $response->viewData('captcha');

    return [
        'captcha_id' => $challenge['id'],
        'captcha_tiles' => session(ColorCaptcha::SESSION_KEY.'.'.$challenge['id'].'.answers'),
    ];
}
