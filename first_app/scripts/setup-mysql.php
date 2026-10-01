<?php

use Illuminate\Contracts\Console\Kernel;

// Run from first_app: php scripts/setup-mysql.php [--reset]
// Credentials come only from this application's local .env configuration.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
$connection = config('database.connections.mysql');
$database = $connection['database'];
if (! preg_match('/\A[a-zA-Z0-9_]+\z/', $database)) {
    fwrite(STDERR, "Use letters, numbers, and underscores for DB_DATABASE.\n");
    exit(1);
}
try {
    $pdo = new PDO('mysql:host='.$connection['host'].';port='.$connection['port'].';charset=utf8mb4', $connection['username'], $connection['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $version = $pdo->query('SELECT VERSION()')->fetchColumn();
    if (str_contains($version, 'MariaDB')) {
        throw new RuntimeException('This connection is MariaDB. Set DB_PORT to your MySQL server.');
    }
    $pdo->exec('CREATE DATABASE IF NOT EXISTS `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    config(['database.default' => 'mysql']);
    if ($kernel->call('migrate', ['--force' => true]) !== 0) {
        throw new RuntimeException($kernel->output());
    }
    echo $kernel->output();
    if ($kernel->call('preyson:protect-media') !== 0) {
        throw new RuntimeException($kernel->output());
    }
    echo $kernel->output();
    $reset = in_array('--reset', $argv, true);
    $status = $kernel->call($reset ? 'preyson:reset-demo' : 'db:seed', ['--force' => true]);
    echo $kernel->output();
    echo "MySQL $version: $database is ready. Open it in phpMyAdmin using the same host, port, and login.\n";
    exit($status);
} catch (Throwable $error) {
    fwrite(STDERR, 'MySQL setup failed: '.$error->getMessage()."\nCheck DB_HOST, DB_PORT, DB_USERNAME, and DB_PASSWORD in .env.\n");
    exit(1);
}
