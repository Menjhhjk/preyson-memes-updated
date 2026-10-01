<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ColorCaptcha
{
    public const SESSION_KEY = 'color_captchas';

    public const LIFETIME_SECONDS = 600;

    // Correct shades stay close; pastel mixtures and distant hues are deliberately distinct.
    private const PALETTES = [
        'red' => ['hue' => 0, 'shades' => ['#dc2626', '#e52c2c', '#cf2424'], 'mix' => ['rose pink', '#f3bfd0']],
        'orange' => ['hue' => 29, 'shades' => ['#ed7910', '#e8750b', '#f18219'], 'mix' => ['pale peach', '#f6dfc7']],
        'green' => ['hue' => 134, 'shades' => ['#169b3b', '#18943a', '#1c9f40'], 'mix' => ['pale mint', '#c8ead6']],
        'blue' => ['hue' => 224, 'shades' => ['#2456dc', '#285be0', '#204fd3'], 'mix' => ['pale sky blue', '#cbdff6']],
        'purple' => ['hue' => 278, 'shades' => ['#9325d4', '#8c22cd', '#9a2dda'], 'mix' => ['pale lavender', '#e4d0ef']],
    ];

    /**
     * Create independent challenges so opening another tab does not invalidate this form.
     *
     * @return array{id: string, purpose: string, target: string, target_color: string, tiles: list<array{color: string, name: string}>}
     */
    public function issue(Request $request, string $purpose): array
    {
        $pending = array_filter($request->session()->get(self::SESSION_KEY, []),
            fn (array $challenge) => $challenge['expires_at'] > now()->getTimestamp());

        while (count($pending) >= 12) {
            array_shift($pending);
        }

        $name = array_rand(self::PALETTES);
        $palette = self::PALETTES[$name];
        $correctCount = random_int(2, 6);
        $tiles = [];

        for ($i = 0; $i < $correctCount; $i++) {
            $tiles[] = ['color' => $palette['shades'][array_rand($palette['shades'])], 'name' => $name, 'correct' => true];
        }

        $tiles[] = ['color' => $palette['mix'][1], 'name' => $palette['mix'][0], 'correct' => false];
        $distant = array_filter(self::PALETTES, function (array $other) use ($palette) {
            $distance = abs($other['hue'] - $palette['hue']);

            return min($distance, 360 - $distance) >= 80;
        });

        while (count($tiles) < 9) {
            $otherName = array_rand($distant);
            $other = $distant[$otherName];
            $tiles[] = ['color' => $other['shades'][array_rand($other['shades'])], 'name' => $otherName, 'correct' => false];
        }

        shuffle($tiles);
        $id = (string) Str::uuid();
        $pending[$id] = [
            'purpose' => $purpose,
            'expires_at' => now()->getTimestamp() + self::LIFETIME_SECONDS,
            'answers' => array_keys(array_filter($tiles, fn (array $tile) => $tile['correct'])),
        ];
        $request->session()->put(self::SESSION_KEY, $pending);

        return [
            'id' => $id,
            'purpose' => $purpose,
            'target' => $name,
            'target_color' => $palette['shades'][0],
            'tiles' => array_map(fn (array $tile) => ['color' => $tile['color'], 'name' => $tile['name']], $tiles),
        ];
    }

    public function forget(Request $request, ?string $id): void
    {
        if ($id && Str::isUuid($id)) {
            $request->session()->forget(self::SESSION_KEY.'.'.$id);
        }
    }

    /** Consume on every attempt; nothing supplied by the browser is trusted as the answer. */
    public function validate(Request $request, string $purpose): void
    {
        $id = $request->input('captcha_id');
        $challenge = is_string($id) && Str::isUuid($id)
            ? $request->session()->pull(self::SESSION_KEY.'.'.$id)
            : null;

        $validator = Validator::make($request->only('captcha_tiles'), [
            'captcha_tiles' => ['required', 'array', 'min:2', 'max:6'],
            'captcha_tiles.*' => ['required', 'integer', 'between:0,8', 'distinct'],
        ]);

        if (! $challenge || $challenge['purpose'] !== $purpose || $challenge['expires_at'] <= now()->getTimestamp() || $validator->fails()) {
            $this->fail();
        }

        $selected = array_map('intval', $request->input('captcha_tiles'));
        sort($selected);
        $expected = $challenge['answers'];
        sort($expected);

        if ($selected !== $expected) {
            $this->fail();
        }
    }

    private function fail(): never
    {
        throw ValidationException::withMessages([
            'captcha_tiles' => 'The color check was incorrect or expired. Please select the matching squares in a fresh challenge.',
        ]);
    }
}
