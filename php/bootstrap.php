<?php
declare(strict_types=1);

/**
 * Hostinger layout:
 * - Main domain public_html/index.php → src/ is in THIS folder (__DIR__).
 * - Subdomain folders (sandbox/) can sit inside public_html; the released zip
 *   used dirname(__DIR__) which accidentally worked for the subdomain and
 *   500'd the main domain. Prefer this folder, then the parent.
 */
function inpmnt_fail(string $msg): never
{
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        header('X-InPmnt: php');
        header('Cache-Control: no-store');
    }
    echo "InPmnt PHP error\n\n{$msg}\n";
    exit;
}

function inpmnt_normalize_perms(string $root): void
{
    $data = $root . '/data';
    if (!is_dir($data)) {
        @mkdir($data, 0755, true);
    }
    if (is_dir($data)) {
        @chmod($data, 0755);
    }
    $groups = [
        glob($root . '/*.php') ?: [],
        glob($root . '/src/*.php') ?: [],
        glob($root . '/views/*.php') ?: [],
    ];
    foreach ($groups as $files) {
        foreach ($files as $path) {
            if (!is_file($path)) {
                continue;
            }
            if ((fileperms($path) & 0002) === 0002) {
                @chmod($path, 0644);
            }
        }
    }
    $env = $root . '/.env';
    if (is_file($env)) {
        @chmod($env, 0600);
    }
}

$root = null;
$tried = [];
foreach ([__DIR__, dirname(__DIR__)] as $dir) {
    $tried[] = $dir . '/src/Env.php';
    if (is_file($dir . '/src/Env.php')) {
        $root = $dir;
        break;
    }
}
if ($root === null) {
    inpmnt_fail(
        "Cannot find src/Env.php (this is the usual cause of a blank 500 on the main domain).\nLooked in:\n- " .
        implode("\n- ", $tried) .
        "\n\nUpload the full PHP zip so index.php, bootstrap.php, src/, views/, and data/ are in the same folder as this file."
    );
}

inpmnt_normalize_perms($root);

require $root . '/src/Env.php';
require $root . '/src/Http.php';
require $root . '/src/Seo.php';
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
try {
    session_start();
} catch (Throwable $e) {
    inpmnt_fail('Could not start a PHP session: ' . $e->getMessage());
}

$dbPath = Env::get('DATABASE_PATH');
if ($dbPath === '') {
    $dbPath = $root . '/data/inpmnt.db';
} elseif (!str_contains($dbPath, '/') && !str_contains($dbPath, '\\') && !preg_match('#^[A-Za-z]:\\\\#', $dbPath)) {
    $dbPath = $root . '/' . ltrim($dbPath, '/');
}

if (!extension_loaded('pdo_sqlite')) {
    inpmnt_fail('InPmnt needs the PHP PDO SQLite extension. Enable it in hPanel → PHP Configuration, or ask Hostinger support to turn on pdo_sqlite.');
}

try {
    $db = Db::connect($dbPath);
    Db::init($db);
} catch (Throwable $e) {
    inpmnt_fail(
        "Could not open the SQLite database at {$dbPath}\n" .
        $e->getMessage() .
        "\n\nOn Hostinger, the data/ folder next to index.php must be writable."
    );
}

return $db;
