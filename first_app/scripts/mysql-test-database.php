<?php

// Dedicated local verification database; never resets the app database.
$projectRoot = dirname(__DIR__);
$pdo = new PDO('mysql:host=127.0.0.1;port=3308;charset=utf8mb4', 'root', trim(file_get_contents($projectRoot.'/storage/mysql/root-password.txt')), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if (in_array('--drop', $argv, true)) {
    $pdo->exec('DROP DATABASE IF EXISTS preyson_memes_test');
    $pdo->exec("REVOKE ALL PRIVILEGES ON preyson_memes_test.* FROM 'preyson'@'127.0.0.1'");
    $pdo->exec("REVOKE ALL PRIVILEGES ON preyson_memes_test.* FROM 'preyson'@'localhost'");
    echo "Temporary test database removed.\n";
    exit;
}
$pdo->exec('CREATE DATABASE IF NOT EXISTS preyson_memes_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$pdo->exec("GRANT ALL PRIVILEGES ON preyson_memes_test.* TO 'preyson'@'127.0.0.1'");
$pdo->exec("GRANT ALL PRIVILEGES ON preyson_memes_test.* TO 'preyson'@'localhost'");
echo "Dedicated MySQL test database ready.\n";
