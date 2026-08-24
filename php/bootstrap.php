<?php
declare(strict_types=1);

// App root is the folder that contains this file (public_html, /var/www/html, etc.).
// Do not use dirname(__DIR__) — that walks out of the document root (e.g. /var/www/src).
$root = __DIR__;
$envPhp = $root . '/src/Env.php';
if (!is_file($envPhp)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "InPmnt cannot find src/Env.php next to bootstrap.php.\n";
    echo "Expected: {$envPhp}\n";
    echo "Unzip InPmnt-PHP.zip so index.php, bootstrap.php, src/, views/, and data/ are all in the web root (public_html or /var/www/html).\n";
    exit(1);
}
require $envPhp;
require $root . '/src/Http.php';
require $root . '/src/Db.php';
require $root . '/src/Mail.php';
require $root . '/src/Billing.php';
require $root . '/src/Workspace.php';
require $root . '/src/App.php';

Env::load($root . '/.env');

$secret = Env::get('APP_SECRET', Env::get('FLASK_SECRET_KEY', 'inpmnt-dev-change-me'));
if ($secret === '' || $secret === 'change-me-to-a-long-random-string') {
    $secret = 'inpmnt-dev-change-me';
}
$_ENV['APP_SECRET'] = $secret;

$secure = str_starts_with(strtolower(Env::get('BASE_URL')), 'https://');
session_name('inpmnt');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

$dbPath = Env::get('DATABASE_PATH');
if ($dbPath === '') {
    $dbPath = $root . '/data/inpmnt.db';
} elseif (!str_contains($dbPath, '/') && !str_contains($dbPath, '\\') && !preg_match('#^[A-Za-z]:\\\\#', $dbPath)) {
    $dbPath = $root . '/' . ltrim($dbPath, '/');
}

if (!extension_loaded('pdo_sqlite')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "InPmnt needs the PHP PDO SQLite extension. Enable it in hPanel → PHP Configuration, or on Apache2 run: apt install php-sqlite3 && systemctl restart apache2.";
    exit(1);
}

$db = Db::connect($dbPath);
Db::init($db);

return $db;
