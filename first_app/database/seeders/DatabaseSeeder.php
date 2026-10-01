<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seeding is idempotent and does not reset an existing administrator's password.
        $admin = User::firstOrNew(['email' => 'admin@gmail.com']);
        if (! $admin->exists) {
            $admin->forceFill([
                'username' => 'preyson_admin',
                'password' => 'pass@123', 'is_admin' => true, 'role' => 'admin',
            ])->save();
        }
    }
}
