<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;
use InvalidArgumentException;

class UniqueAccountField implements ValidationRule
{
    public function __construct(private string $field, private ?int $ignoreUserId = null)
    {
        if (! in_array($field, ['username', 'email'], true)) {
            throw new InvalidArgumentException('Unsupported account field.');
        }
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $exists = User::query()
            ->whereRaw($this->field === 'email' ? 'LOWER(email) = ?' : 'LOWER(username) = ?', [Str::lower((string) $value)])
            ->when($this->ignoreUserId !== null, fn ($query) => $query->where('id', '!=', $this->ignoreUserId))
            ->exists();

        if ($exists) {
            $fail('This '.$this->field.' is already in use.');
        }
    }
}
