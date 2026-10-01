<?php

namespace App\Support;

class ReportReasons
{
    /** @return array<string, array<string, string>> */
    public static function groups(): array
    {
        return [
            'severe' => [
                'threats' => 'Credible threats or encouragement of violence',
                'exploitation' => 'Sexual exploitation or abuse',
                'private_information' => 'Exposing private information / doxxing',
                'other_severe' => 'Other severe concern',
            ],
            'moderate' => [
                'harassment' => 'Targeted harassment or hateful content',
                'graphic_content' => 'Graphic violence or explicit sexual content',
                'scam' => 'Scams, impersonation, or malicious links',
                'other_moderate' => 'Other moderate concern',
            ],
            'minor' => [
                'spam' => 'Spam or repeated content',
                'off_topic' => 'Off-topic or disruptive content',
                'misleading' => 'Misleading title or description',
                'other_minor' => 'Other minor concern',
            ],
        ];
    }

    /** @return array<string, string> */
    public static function all(): array
    {
        return array_merge(...array_values(self::groups()));
    }

    public static function severity(string $reason): string
    {
        foreach (self::groups() as $severity => $reasons) {
            if (isset($reasons[$reason])) {
                return $severity;
            }
        }
        throw new \InvalidArgumentException('Unknown report reason.');
    }
}
