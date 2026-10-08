<?php

namespace App\Support;

use App\Models\Post;
use App\Models\PostingDay;
use App\Models\User;
use Carbon\CarbonImmutable;

class PostingRewards
{
    // Called inside the publishing transaction while the user row is locked.
    public static function record(User $member, Post $post): void
    {
        $date = CarbonImmutable::now(config('engagement.timezone'))->toDateString();
        $days = PostingDay::where('user_id', $member->id);
        if ((clone $days)->whereDate('posted_on', $date)->exists() || ($number = $days->count() + 1) > 14) {
            return;
        }
        $reward = config('engagement.rewards.'.$number);
        PostingDay::create(['user_id' => $member->id, 'post_id' => $post->id, 'posted_on' => $date, 'day_number' => $number, 'reward' => $reward]);
        $member->bonus_super_reactions += $reward['super'] ?? 0;
        $member->bonus_boosts += $reward['boost'] ?? 0;
        if (isset($reward['premium_days'])) {
            $start = $member->hasPremium() ? CarbonImmutable::instance($member->premium_expires_at) : CarbonImmutable::now();
            $member->forceFill(['premium_expires_at' => $start->addDays($reward['premium_days'])]);
        }
        if ($reward['corner'] ?? false) {
            $member->forceFill(['corner_unlocked_at' => now()]);
        }
        $member->save();
        MemberInbox::send($member->id, 'reward', 'Day '.$number.' reward earned!', self::label($reward), 'rewards', null, 'reward:'.$number);
    }

    /** @param array<string, int|bool> $reward */
    public static function label(array $reward): string
    {
        $parts = [];
        foreach (['super' => 'bonus Super-reaction', 'boost' => 'bonus Boost', 'premium_days' => 'day of Premium'] as $key => $label) {
            if ($reward[$key] ?? 0) {
                $parts[] = $reward[$key].' '.($key === 'premium_days' ? 'days of Premium' : $label.($reward[$key] === 1 ? '' : 's'));
            }
        }
        if ($reward['corner'] ?? false) {
            $parts[] = 'Permission to request your own Corner';
        }

        return implode(' + ', $parts);
    }
}
