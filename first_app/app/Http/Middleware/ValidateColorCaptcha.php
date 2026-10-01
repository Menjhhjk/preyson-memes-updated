<?php

namespace App\Http\Middleware;

use App\Services\ColorCaptcha;
use Closure;
use Illuminate\Http\Request;

class ValidateColorCaptcha
{
    public function __construct(private ColorCaptcha $captcha) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $this->captcha->validate($request, 'login');

        return $next($request);
    }
}
