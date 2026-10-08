<?php
declare(strict_types=1);

$root = is_dir(__DIR__ . '/src') ? __DIR__ : dirname(__DIR__);
require $root . '/src/Env.php';
require $root . '/src/Http.php';
require $root . '/src/Db.php';
require $root . '/src/Mail.php';
require $root . '/src/Billing.php';
require $root . '/src/Workspace.php';
require $root . '/src/Admin.php';
require $root . '/src/HelpChat.php';
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
    echo "InvoicePay needs the PHP PDO SQLite extension. Enable it in hPanel → PHP Configuration, or ask Hostinger support to turn on pdo_sqlite.";
    exit;
}

$attempts = 0;
while (true) {
    try {
        $db = Db::connect($dbPath);
        Db::init($db);
        break;
    } catch (PDOException $e) {
        $attempts++;
        $locked = str_contains($e->getMessage(), 'locked') || str_contains($e->getMessage(), 'busy');
        if (!$locked || $attempts >= 4) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            header_remove('X-Powered-By');
            echo "InvoicePay could not open its database. Reload the page in a moment.";
            exit;
        }
        usleep(150000 * $attempts);
    }
}

return $db;
