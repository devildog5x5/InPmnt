<?php
declare(strict_types=1);

$root = is_dir(__DIR__ . '/src') ? __DIR__ : dirname(__DIR__);
require_once $root . '/src/Env.php';
require_once $root . '/src/Http.php';
require_once $root . '/src/Db.php';
require_once $root . '/src/Mail.php';
require_once $root . '/src/Billing.php';
require_once $root . '/src/Workspace.php';
require_once $root . '/src/Admin.php';
require_once $root . '/src/HelpChat.php';
require_once $root . '/src/App.php';

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
    echo "InPmnt needs the PHP PDO SQLite extension. Enable it in hPanel → PHP Configuration, or ask Hostinger support to turn on pdo_sqlite.";
    exit;
}

$db = Db::connect($dbPath);
Db::init($db);

return $db;
