<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property-read string $name Username alias for framework integrations.
 * @property string $username
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property bool $is_admin
 * @property string $role
 * @property string|null $avatar_path
 * @property string|null $description
 * @property string $profile_visibility
 * @property bool $email_visible
 * @property string $profile_background
 * @property string $profile_color_one
 * @property string $profile_color_two
 * @property int|null $pinned_post_id
 * @property Carbon|null $premium_expires_at
 * @property Carbon|null $terms_accepted_at
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['username', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    // Fortify/passkeys and the retained security screens expect a display name.
    protected $appends = ['name'];

    public function getNameAttribute(): string
    {
        return $this->username ?? 'Member';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'email_visible' => 'boolean',
            'pinned_post_id' => 'integer',
            'premium_expires_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /** @return HasMany<Post, $this> */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function isAdmin(): bool
    {
        return $this->is_admin || $this->role === 'admin';
    }

    public function profileVisibleTo(?User $viewer): bool
    {
        return $this->profile_visibility !== 'private' || ($viewer && ($this->is($viewer) || $viewer->canModerate()));
    }

    public function profileBackground(): ?string
    {
        if (! $this->hasPremium() || $this->profile_background === 'default'
            || ! preg_match('/^#[0-9a-fA-F]{6}$/', $this->profile_color_one)
            || ! preg_match('/^#[0-9a-fA-F]{6}$/', $this->profile_color_two)) {
            return null;
        }

        return $this->profile_background === 'gradient'
            ? "linear-gradient(135deg, {$this->profile_color_one}, {$this->profile_color_two})"
            : $this->profile_color_one;
    }

    public function canModerate(): bool
    {
        return $this->isAdmin() || $this->role === 'moderator';
    }

    public function hasPremium(): bool
    {
        return $this->premium_expires_at !== null && $this->premium_expires_at->isFuture();
    }

    public function postLimit(): ?int
    {
        return $this->isAdmin() ? null : ($this->hasPremium() ? 30 : 6);
    }

    public function avatarUrl(): string
    {
        return $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : asset('avatar-default.svg');
    }
}
