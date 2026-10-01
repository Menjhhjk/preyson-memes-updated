<?php

use App\Services\ColorCaptcha;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

Route::view('/policies', 'policies')->name('policies');

Route::post('/color-captcha', function (Request $request, ColorCaptcha $captcha) {
    $input = $request->validate([
        'purpose' => ['required', Rule::in(['login', 'register'])],
        'previous_id' => ['nullable', 'uuid'],
    ]);
    $captcha->forget($request, $input['previous_id'] ?? null);

    return response()->json($captcha->issue($request, $input['purpose']))->header('Cache-Control', 'no-store');
})->middleware(['guest', 'throttle:30,1,color-captcha'])->name('captcha.refresh');
