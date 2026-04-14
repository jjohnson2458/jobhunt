<?php
/**
 * One-shot installer.
 *   php scripts/install.php
 *
 * - Creates the database (footraffic) if missing
 * - Runs migrations + seeds
 * - Sets the real admin password hash
 */
define('BASE_PATH', dirname(__DIR__));
spl_autoload_register(function ($c) {
    foreach ([BASE_PATH . "/core/$c.php", BASE_PATH . "/app/Models/$c.php"] as $p) {
        if (file_exists($p)) { require_once $p; return; }
    }
});
Env::load(BASE_PATH);

$host = env('DB_HOST', 'localhost');
$user = env('DB_USERNAME', 'root');
$pass = env('DB_PASSWORD', '');
$db   = env('DB_DATABASE', 'footraffic');

echo "Connecting to MySQL @ $host as $user...\n";
$pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `$db`");

echo "Running migrations...\n";
$pdo->exec(file_get_contents(BASE_PATH . '/database/migrations/001_init.sql'));

echo "Seeding...\n";
$pdo->exec(file_get_contents(BASE_PATH . '/database/seeds/001_seed.sql'));

$hash = password_hash('24AdaPlace', PASSWORD_BCRYPT);
$pdo->prepare("UPDATE users SET password_hash = ? WHERE id = 1")->execute([$hash]);

echo "Done. Login: email4johnson@gmail.com / 24AdaPlace\n";
echo "Or visit from 98.10.144.135 / 127.0.0.1 to bypass auth.\n";
