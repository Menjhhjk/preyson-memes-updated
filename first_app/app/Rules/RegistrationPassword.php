<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class RegistrationPassword implements ValidationRule
{
    public const MIN_LENGTH = 8;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }
        if (! preg_match('/\p{Lu}/u', $value)) {
            $fail('Your password needs at least one capital letter.');
        }
        if (! preg_match('/\p{N}/u', $value)) {
            $fail('Your password needs at least one number.');
        }
        if (! preg_match('/[\p{P}\p{S}]/u', $value)) {
            $fail('Your password needs at least one special character, such as !, @, or #.');
        }
    }
}
