<?php
/**
 * Front controller for claude_jobhunt
 * Camouflaged as "Foot Traffic Analytics"
 */
define('BASE_PATH', dirname(__DIR__));

// Composer autoload (optional — only if vendor exists)
if (file_exists(BASE_PATH . '/vendor/autoload.php')) {
    require BASE_PATH . '/vendor/autoload.php';
}

spl_autoload_register(function ($class) {
    $paths = [
        BASE_PATH . "/core/{$class}.php",
        BASE_PATH . "/app/Controllers/{$class}.php",
        BASE_PATH . "/app/Models/{$class}.php",
        BASE_PATH . "/app/Services/{$class}.php",
    ];
    foreach ($paths as $p) {
        if (file_exists($p)) { require_once $p; return; }
    }
});

Env::load(BASE_PATH);

// Hidden-site headers — tell every compliant crawler to go away
header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; font-src 'self' https://cdn.jsdelivr.net data:; img-src 'self' data:;");

ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Lax');
session_start();

define('BASE_URL', env('APP_PATH', ''));

ini_set('display_errors', env('DISPLAY_ERRORS', '0'));
error_reporting(E_ALL);

// CSRF on POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify()) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid security token.'];
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? BASE_URL . '/'));
        exit;
    }
}

// IP-based auto-login bypass
$appConfig = require BASE_PATH . '/config/app.php';
$clientIp  = $_SERVER['REMOTE_ADDR'] ?? '';
if (in_array($clientIp, $appConfig['auth_bypass_ips'], true) && !isset($_SESSION['user_id'])) {
    // Synthetic admin session — no DB hit needed
    $_SESSION['user_id']    = 1;
    $_SESSION['user_email'] = $appConfig['admin_email'];
    $_SESSION['user_role']  = 'admin';
    $_SESSION['user_name']  = 'J.J. Johnson';
    $_SESSION['ip_bypass']  = true;
}

$router = new Router();
require BASE_PATH . '/config/routes.php';

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$basePath = env('APP_PATH', '');
if ($basePath && str_starts_with($uri, $basePath)) {
    $uri = substr($uri, strlen($basePath));
}
$router->dispatch($uri ?: '/', $_SERVER['REQUEST_METHOD']);
