<?php
declare(strict_types=1);

$root = __DIR__;

foreach (['data', 'data/uploads'] as $dir) {
    $path = $root . '/' . $dir;
    if (!is_dir($path)) {
        mkdir($path, 0775, true);
    }
    @chmod($path, 0775);
}

$envPath = $root . '/.env';
if (!is_file($envPath) && is_file($root . '/.env.example')) {
    $secret = bin2hex(random_bytes(24));
    $body = file_get_contents($root . '/.env.example') ?: '';
    $body = preg_replace('/^APP_SECRET=.*$/m', 'APP_SECRET=' . $secret, $body) ?? $body;
    file_put_contents($envPath, $body);
    @chmod($envPath, 0640);
}

require $root . '/src/Env.php';
require $root . '/src/Http.php';
require $root . '/src/Db.php';
require $root . '/src/Analyze.php';
require $root . '/src/View.php';
require $root . '/src/Product.php';
require $root . '/src/Billing.php';
require $root . '/src/Auth.php';
require $root . '/src/Mail.php';
require $root . '/src/App.php';

Env::load($envPath);

$secret = Env::get('APP_SECRET', 'ourcircle-dev');
if ($secret === '' || $secret === 'change-me-to-a-long-random-string') {
    $secret = 'ourcircle-dev';
}
$_ENV['APP_SECRET'] = $secret;

$secure = str_starts_with(strtolower(Env::get('BASE_URL', Env::get('OURCIRCLE_SITE_URL', 'https://familyshieldpro.com'))), 'https://');
session_name('ourcircle');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

if (!extension_loaded('pdo_sqlite')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Family Shield Pro needs PHP PDO SQLite. In hPanel → Advanced → PHP Configuration, enable pdo_sqlite (PHP 8.2+).";
    exit;
}

$dbPath = Env::get('DATABASE_PATH');
if ($dbPath === '') {
    $dbPath = $root . '/data/ourcircle.db';
} elseif (!str_contains($dbPath, '/') && !str_contains($dbPath, '\\')) {
    $dbPath = $root . '/' . ltrim($dbPath, '/');
}

$db = Db::connect($dbPath);
Db::init($db);

return $db;
