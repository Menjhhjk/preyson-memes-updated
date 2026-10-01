<?php

namespace App\Concerns;

use App\Rules\UniqueAccountField;
use Illuminate\Contracts\Validation\ValidationRule;

trait ProfileValidationRules
{
    /**
     * Get the validation rules used to validate user profiles.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function profileRules(?int $userId = null): array
    {
        return [
            'username' => $this->usernameRules($userId),
            'email' => $this->emailRules($userId),
        ];
    }

    /**
     * New or changed usernames need six characters; existing short logins can be retained.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function usernameRules(?int $userId = null, int $minimum = 6): array
    {
        return ['required', 'string', 'min:'.$minimum, 'max:50', 'regex:/\A[a-z0-9_.-]+\z/i', new UniqueAccountField('username', $userId)];
    }

    /**
     * Get the validation rules used to validate user emails.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function emailRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'email:rfc',
            'max:255',
            new UniqueAccountField('email', $userId),
        ];
    }
}
