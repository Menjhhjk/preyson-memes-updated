<?php

// One-time setup for the isolated, newly initialized project MySQL instance.
// Never points at or changes credentials on the existing system MySQL service.
$root = dirname(__DIR__);
$credentialFile = $root.'/storage/mysql/root-password.txt';
if (file_exists($credentialFile)) {
    fwrite(STDERR, "This instance is already configured. Run setup-mysql.php instead.\n");
    exit(1);
}
$pdo = new PDO('mysql:host=127.0.0.1;port=3308;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$dataDirectory = str_replace('\\', '/', (string) $pdo->query('SELECT @@datadir')->fetchColumn());
if (rtrim(strtolower($dataDirectory), '/') !== strtolower(str_replace('\\', '/', $root.'/storage/mysql/data'))) {
    throw new RuntimeException('Refusing to modify a MySQL instance outside this project.');
}
$rootPassword = bin2hex(random_bytes(24));
$appPassword = bin2hex(random_bytes(18));
$pdo->exec('CREATE DATABASE IF NOT EXISTS preyson_memes CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$pdo->exec("CREATE USER 'preyson'@'127.0.0.1' IDENTIFIED BY ".$pdo->quote($appPassword));
$pdo->exec("CREATE USER 'preyson'@'localhost' IDENTIFIED BY ".$pdo->quote($appPassword));
$pdo->exec("GRANT ALL PRIVILEGES ON preyson_memes.* TO 'preyson'@'127.0.0.1'");
$pdo->exec("GRANT ALL PRIVILEGES ON preyson_memes.* TO 'preyson'@'localhost'");
file_put_contents($credentialFile, $rootPassword);
$pdo->exec("ALTER USER 'root'@'localhost' IDENTIFIED BY ".$pdo->quote($rootPassword));
$environment = file_get_contents($root.'/.env');
foreach (['APP_NAME' => 'PreySON', 'DB_CONNECTION' => 'mysql', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3308', 'DB_DATABASE' => 'preyson_memes', 'DB_USERNAME' => 'preyson', 'DB_PASSWORD' => $appPassword] as $key => $value) {
    $environment = preg_replace('/^'.preg_quote($key, '/').'=.*$/m', $key.'='.$value, $environment);
}
file_put_contents($root.'/.env', $environment);

// Add a dedicated, database-scoped server entry; preserve the existing entries.
$pmaPath = 'C:/xampp/phpMyAdmin/config.inc.php';
$pmaConfig = file_get_contents($pmaPath);
if (! str_contains($pmaConfig, '// PreySON project MySQL')) {
    $entry = "\n// PreySON project MySQL\n";
    $settings = ['verbose' => 'PreySON MySQL (3308)', 'host' => '127.0.0.1', 'port' => '3308', 'auth_type' => 'config', 'user' => 'preyson', 'password' => $appPassword, 'only_db' => 'preyson_memes'];
    $entry .= "\$i++;\n";
    foreach ($settings as $key => $value) {
        $entry .= '$cfg[\'Servers\'][$i]['.var_export($key, true).'] = '.var_export($value, true).";\n";
    }
    $entry .= "\$cfg['Servers'][\$i]['AllowNoPassword'] = false;\n";
    $entry .= "\$cfg['ServerDefault'] = \$i;\n";
    $pmaConfig = preg_replace('/\?>\s*$/', '', $pmaConfig).$entry;
    file_put_contents($pmaPath, $pmaConfig);
}
echo "Project MySQL configured on 127.0.0.1:3308. Application credentials saved locally; phpMyAdmin entry added.\n";
