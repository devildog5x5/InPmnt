<?php
declare(strict_types=1);

/**
 * Document-root drop-in (Apache /var/www/html, Hostinger public_html):
 * src/ lives NEXT TO this file, so the root is __DIR__.
 *
 * dirname(__DIR__) is wrong for that layout — it looks for /var/www/src/Env.php
 * and Apache logs: Failed opening required '/var/www/src/Env.php'.
 *
 * Parent is only a fallback for a subdomain folder that still shares the
 * parent's src/ (sandbox.example.com → public_html/sandbox/).
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
        "Cannot find src/Env.php (this is the usual cause of a blank HTTP 500).\nLooked in:\n- " .
        implode("\n- ", $tried) .
        "\n\nUnzip InPmnt-PHP.zip so index.php, bootstrap.php, src/, views/, and data/ sit in the web root\n(/var/www/html or public_html). Then open /inpmnt-check.php"
    );
}

require $root . '/src/Env.php';
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
    inpmnt_fail(
        "InPmnt needs the PHP PDO SQLite extension.\n" .
        "Apache2 (Ubuntu): sudo apt install php-sqlite3 && sudo systemctl restart apache2\n" .
        "Hostinger: hPanel → Advanced → PHP Configuration → enable pdo_sqlite"
    );
}

try {
    $db = Db::connect($dbPath);
    Db::init($db);
} catch (Throwable $e) {
    inpmnt_fail(
        "Could not open the SQLite database at {$dbPath}\n" .
        $e->getMessage() .
        "\n\nOn Ubuntu Apache: sudo bash fix-ubuntu-perms.sh" .
        "\nPHP files should be 644 (not 777). data/ must be 775 and owned by www-data."
    );
}

return $db;
