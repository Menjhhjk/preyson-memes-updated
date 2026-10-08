<?php

namespace App\Support;

use App\Models\MemberNotification;

class MemberInbox
{
    public static function send(?int $userId, string $kind, string $title, string $message, ?string $targetType = null, ?int $targetId = null, ?string $dedupe = null): void
    {
        if ($userId === null) {
            return;
        }
        $attributes = ['user_id' => $userId, 'kind' => $kind, 'title' => $title, 'message' => $message,
            'target_type' => $targetType, 'target_id' => $targetId, 'dedupe_key' => $dedupe];
        if ($dedupe !== null) {
            MemberNotification::firstOrCreate(['user_id' => $userId, 'dedupe_key' => $dedupe], $attributes);
        } else {
            MemberNotification::create($attributes);
        }
    }
}
