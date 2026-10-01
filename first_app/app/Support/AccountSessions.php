<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccountSessions
{
    public static function revoke(User $user, ?string $exceptSessionId = null): void
    {
        DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->when($exceptSessionId !== null, fn ($query) => $query->where('id', '!=', $exceptSessionId))
            ->delete();

        self::clearPasswordResets($user->email);
        $user->forceFill(['remember_token' => Str::random(60)])->save();
    }

    public static function clearPasswordResets(string $email): void
    {
        DB::table('password_reset_tokens')->whereRaw('LOWER(email) = ?', [Str::lower($email)])->delete();
    }
}
