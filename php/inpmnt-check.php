<?php
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex');

$here = __DIR__;
$wpHere = is_file($here . '/wp-config.php') || is_dir($here . '/wp-admin') || is_dir($here . '/wp-includes');
$inpmnt = is_file($here . '/index.php') && is_file($here . '/bootstrap.php') && is_dir($here . '/src');
$indexHead = '';
if (is_file($here . '/index.php')) {
    $indexHead = (string) file_get_contents($here . '/index.php');
}
$indexIsInpmnt = str_contains($indexHead, 'bootstrap.php') && (str_contains($indexHead, 'new App') || str_contains($indexHead, 'InPmnt'));
$indexIsWordpress = str_contains($indexHead, 'wp-blog-header.php') || str_contains($indexHead, 'wp-config.php');

echo "InPmnt PHP files ARE on this server.\n";
echo "This check file: " . ($here) . "\n\n";

echo "index.php in this folder is: ";
if ($indexIsInpmnt) {
    echo "InPmnt (good)\n";
} elseif ($indexIsWordpress) {
    echo "WORDPRESS (this is why the homepage is WordPress)\n";
} else {
    echo "unknown / missing\n";
}

echo "WordPress files in this same folder: " . ($wpHere ? "YES (wp-admin / wp-config.php still here)" : "no") . "\n";
echo "InPmnt app files present: " . ($inpmnt ? "yes" : "NO") . "\n\n";

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
    if (is_readable($path)) {
        $bits[] = 'readable';
    } else {
        $bits[] = 'NOT readable';
    }
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
    echo "  Fix: File Manager → data → Permissions → 755. Or hPanel → Advanced → Fix File Ownership.\n";
}

echo "\n";

echo "If https://yourdomain.com still shows WordPress:\n";
echo "A. If this folder's index.php is already InPmnt: Hostinger Cache Manager is\n";
echo "   serving a cached WordPress homepage. hPanel → Advanced → Cache Manager\n";
echo "   → Purge all, turn Automatic cache off, then use a private window.\n";
echo "B. If index.php is still WordPress, or files are in a subfolder:\n";
echo "   1. Hostinger usually pre-installs WordPress in public_html.\n";
echo "   2. File Manager Extract often creates public_html/InPmnt-PHP/.\n";
echo "      Move EVERY file up into public_html and overwrite index.php / .htaccess.\n";
echo "   3. Delete wp-admin, wp-content, wp-includes, wp-config.php if unused.\n";
echo "C. In hPanel open the domain URL itself (not the WordPress button).\n";
