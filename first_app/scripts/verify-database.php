<?php

use App\Models\Post;
use App\Models\Reaction;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$admin = User::query()->where('email', 'admin@gmail.com')->sole();
echo json_encode([
    'connection' => config('database.default'),
    'server' => DB::selectOne('SELECT VERSION() AS version')->version,
    'database' => DB::connection()->getDatabaseName(),
    'accounts' => User::query()->count(),
    'posts' => Post::query()->count(),
    'reactions' => Reaction::query()->count(),
    'admin_email' => $admin->email,
    'admin_role_verified' => $admin->isAdmin(),
    'admin_password_verified' => Hash::check('pass@123', $admin->password),
], JSON_PRETTY_PRINT)."\n";
