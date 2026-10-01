<?php

namespace App\Actions\Fortify;

use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Rules\RegistrationPassword;
use App\Services\ColorCaptcha;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        if (is_string($input['username'] ?? null)) {
            $input['username'] = strtolower(trim($input['username']));
        }
        if (is_string($input['email'] ?? null)) {
            $input['email'] = strtolower(trim($input['email']));
        }

        $validator = Validator::make($input, [
            ...$this->profileRules(),
            'password' => ['required', 'string', 'min:'.RegistrationPassword::MIN_LENGTH, new RegistrationPassword, 'confirmed'],
            'password_confirmation' => ['required', 'string'],
            'terms' => ['required', 'accepted'],
        ]);
        // CAPTCHA remains single-use, but its errors join the other form errors.
        $validator->after(function (ValidatorContract $validator): void {
            try {
                app(ColorCaptcha::class)->validate(request(), 'register');
            } catch (ValidationException $exception) {
                foreach ($exception->errors() as $field => $messages) {
                    foreach ($messages as $message) {
                        $validator->errors()->add($field, $message);
                    }
                }
            }
        });
        $validator->validate();

        $user = new User([
            'username' => $input['username'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);
        $user->forceFill(['terms_accepted_at' => now(), 'terms_version' => '2026-10-01'])->save();

        return $user;
    }
}
