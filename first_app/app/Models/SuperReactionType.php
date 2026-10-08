<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class SuperReactionType extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return array<string, string> */
    public function effect(): array
    {
        $url = fn (string $path) => str_starts_with($path, '@demo/') ? asset('super-reactions/'.substr($path, 1)) : Storage::disk('public')->url($path);

        return ['name' => $this->name, 'gif' => $url($this->gif_path), 'sound' => $url($this->sound_path)];
    }
}
