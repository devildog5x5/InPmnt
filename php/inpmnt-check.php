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
