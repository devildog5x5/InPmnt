<?php
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex');
header('Cache-Control: no-store');

$here = __DIR__;
$wpHere = is_file($here . '/wp-config.php') || is_dir($here . '/wp-admin') || is_dir($here . '/wp-includes');
$inpmnt = is_file($here . '/index.php') && is_file($here . '/bootstrap.php') && is_dir($here . '/src');
$indexHead = is_file($here . '/index.php') ? (string) file_get_contents($here . '/index.php') : '';
$indexIsInpmnt = str_contains($indexHead, 'bootstrap.php');
$indexIsWordpress = str_contains($indexHead, 'wp-blog-header.php') || str_contains($indexHead, 'wp-config.php');
$envHere = is_file($here . '/src/Env.php');
$parentEnv = is_file(dirname($here) . '/src/Env.php');

echo "InPmnt PHP diagnostic\n";
echo "Folder: {$here}\n\n";

echo "index.php in this folder is: ";
if ($indexIsInpmnt) {
    echo "InPmnt (good)\n";
} elseif ($indexIsWordpress) {
    echo "WORDPRESS (this is why the homepage is WordPress)\n";
} else {
    echo "unknown / missing\n";
}

echo "WordPress files in this same folder: " . ($wpHere ? "YES (wp-admin / wp-config.php still here)" : "no") . "\n";
echo "InPmnt app files present: " . ($inpmnt ? "yes" : "NO") . "\n";
echo "src/Env.php next to this file: " . ($envHere ? "yes (Apache document-root layout)" : "NO") . "\n";
echo "src/Env.php in parent folder: " . ($parentEnv ? "yes (subdomain/shared-parent layout)" : "no") . "\n";
if (!$envHere && !$parentEnv) {
    echo "  THIS is the Apache 500: Failed opening required '.../src/Env.php'\n";
    echo "  Unzip so bootstrap.php and src/Env.php are in the same folder.\n";
}
echo "\n";

echo "Permissions (Hostinger default: files 644, folders 755)\n";
$permTargets = [
    'index.php' => $here . '/index.php',
    'bootstrap.php' => $here . '/bootstrap.php',
    'src/' => $here . '/src',
    'src/Env.php' => $here . '/src/Env.php',
    'data/' => $here . '/data',
    'data/inpmnt.db' => $here . '/data/inpmnt.db',
    '.htaccess' => $here . '/.htaccess',
];
foreach ($permTargets as $label => $path) {
    if (!file_exists($path)) {
        echo "  {$label}: missing\n";
        continue;
    }
    $mode = substr(sprintf('%o', fileperms($path)), -4);
    $bits = [];
    $bits[] = is_readable($path) ? 'readable' : 'NOT readable';
    if (is_dir($path)) {
        $bits[] = is_writable($path) ? 'writable' : 'NOT writable';
    } elseif (is_writable($path)) {
        $bits[] = 'writable';
    }
    if (!is_dir($path) && str_ends_with($label, '.php') && (fileperms($path) & 0x0002)) {
        $bits[] = 'WORLD-WRITABLE (Hostinger can 500 on 777 PHP files — set 644)';
    }
    echo "  {$label}: {$mode} (" . implode(', ', $bits) . ")\n";
}
echo '  pdo_sqlite: ' . (extension_loaded('pdo_sqlite') ? "enabled\n" : "MISSING — this 500s the app\n");
$sessionPath = session_save_path();
if ($sessionPath === '') {
    $sessionPath = sys_get_temp_dir();
}
echo '  session path: ' . $sessionPath . (is_writable($sessionPath) ? " (writable)\n" : " (NOT writable — can 500)\n");

$dataDir = $here . '/data';
if (!is_dir($dataDir)) {
    echo "  data/ missing — the app must be able to create it (parent folder writable).\n";
} elseif (!is_writable($dataDir)) {
    echo "  data/ is not writable — SQLite cannot create inpmnt.db (common 500).\n";
}

echo "\nIf the homepage is still WordPress after this page loads:\n";
echo "  Purge Hostinger Cache Manager, turn Automatic cache off, use a private window.\n";
echo "  Overwrite public_html/index.php with InPmnt's index.php (not a subfolder).\n";
echo "\nDelete this file after the site is working.\n";
