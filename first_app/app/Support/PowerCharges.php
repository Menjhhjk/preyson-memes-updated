<?php

namespace App\Support;

use App\Models\PostPowerUse;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class PowerCharges
{
    /** @return array{allowance: int, bonus: int, total: int, limit: int, refresh: string, period: string} */
    public static function balance(User $member, string $kind): array
    {
        $premium = $member->hasPremium();
        $local = CarbonImmutable::now(config('engagement.timezone'));
        $start = $premium ? $local->startOfWeek(CarbonImmutable::MONDAY) : $local->startOfMonth();
        $refresh = $premium ? $start->addWeek() : $start->addMonth();
        $used = PostPowerUse::where('user_id', $member->id)->where('kind', $kind)->where('source', 'allowance')
            ->where('created_at', '>=', $start->utc())->count();
        $limit = $premium ? 5 : 3;
        $allowance = max(0, $limit - $used);
        $bonus = $kind === 'super' ? $member->bonus_super_reactions : $member->bonus_boosts;

        return ['allowance' => $allowance, 'bonus' => $bonus, 'total' => $allowance + $bonus, 'limit' => $limit,
            'refresh' => $refresh->toIso8601String(), 'period' => $premium ? 'weekly' : 'monthly'];
    }

    // Caller holds the user lock until the corresponding usage record is saved.
    public static function spend(User $member, string $kind): string
    {
        $balance = self::balance($member, $kind);
        if ($balance['allowance'] > 0) {
            return 'allowance';
        }
        if ($balance['bonus'] === 0) {
            throw ValidationException::withMessages(['charge' => 'No '.($kind === 'super' ? 'Super-reaction' : 'Boost').' charges left. Check Rewards for your next refresh or earn bonus charges by posting on different days.']);
        }
        $field = $kind === 'super' ? 'bonus_super_reactions' : 'bonus_boosts';
        $member->decrement($field);

        return 'bonus';
    }
}
