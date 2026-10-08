<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Models\User;
use App\Support\PostFiles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ResetDemo extends Command
{
    protected $signature = 'preyson:reset-demo {--force : Confirm deletion of all posts and non-admin accounts}';

    protected $description = 'Reset the local demo, preserving the main admin with the documented demo login';

    public function handle(): int
    {
        $connection = config('database.default');
        $host = config('database.connections.'.$connection.'.host');
        if (app()->isProduction() || ($connection !== 'sqlite' && ! in_array($host, ['127.0.0.1', 'localhost', '::1'], true))) {
            $this->error('This command is only available for the local demo.');

            return self::FAILURE;
        }
        if (! $this->option('force') && ! $this->confirm('Delete all posts and accounts except the main administrator?')) {
            return self::FAILURE;
        }
        $paths = DB::transaction(function () {
            $admin = User::query()->whereRaw('LOWER(email) = ?', ['admin@gmail.com'])->first()
                ?? User::query()->where('is_admin', true)->orderBy('id')->first()
                ?? new User;
            $paths = Post::query()->pluck('media_path')->all();
            $paths = array_merge($paths, User::query()->whereNotNull('avatar_path')->pluck('avatar_path')->all());
            Post::query()->delete();
            User::query()->when($admin->exists, fn ($query) => $query->where('id', '!=', $admin->id))->delete();
            foreach (['reports', 'member_notifications', 'member_warnings', 'posting_days', 'post_power_uses', 'corner_requests', 'sessions', 'password_reset_tokens', 'cache', 'cache_locks'] as $table) {
                DB::table($table)->delete();
            }
            $admin->forceFill([
                'username' => $admin->username ?: 'preyson_admin', 'email' => 'admin@gmail.com',
                'password' => 'pass@123', 'is_admin' => true, 'role' => 'admin', 'avatar_path' => null,
                'premium_expires_at' => null, 'remember_token' => null, 'two_factor_secret' => null,
                'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null,
                'description' => null, 'profile_visibility' => 'public', 'email_visible' => false,
                'profile_background' => 'default', 'profile_color_one' => '#f8f6fa', 'profile_color_two' => '#e9dcfa',
                'pinned_post_id' => null,
                'bonus_super_reactions' => 0, 'bonus_boosts' => 0, 'corner_unlocked_at' => null,
            ])->save();
            $admin->passkeys()->delete();

            return $paths;
        });
        PostFiles::delete($paths);
        $admin = User::query()->where('email', 'admin@gmail.com')->sole();
        if (User::query()->count() !== 1 || Post::query()->exists() || ! Hash::check('pass@123', $admin->password) || ! $admin->isAdmin()) {
            $this->error('Demo reset verification failed.');

            return self::FAILURE;
        }
        $this->info('Verified: zero posts, one administrator, and the requested admin login.');

        return self::SUCCESS;
    }
}
