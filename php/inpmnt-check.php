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
echo "1. Hostinger usually pre-installs WordPress in public_html. Visiting the domain\n";
echo "   runs WordPress's index.php, not InPmnt, until you replace it.\n";
echo "2. File Manager Extract often creates public_html/InPmnt-PHP/ (or similar).\n";
echo "   The domain root is still WordPress. Move EVERY file up into public_html.\n";
echo "3. Overwrite index.php and .htaccess. Then delete or rename wp-admin,\n";
echo "   wp-content, wp-includes, and wp-config.php if you do not need WordPress.\n";
echo "4. In hPanel → Websites, open the domain URL itself (not the WordPress button).\n";
echo "5. Purge LiteSpeed / Hostinger cache if the old homepage is stuck.\n";
