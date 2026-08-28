<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/php/src/Analyze.php';
require $root . '/php/src/Db.php';

$fail = 0;
function check(bool $ok, string $msg): void
{
    global $fail;
    if (!$ok) {
        fwrite(STDERR, "FAIL $msg\n");
        $fail++;
    }
}

$out = Analyze::analyze('Your grandson is in jail. Buy $500 in Apple gift cards and keep this secret.');
check($out['level'] === Analyze::PAUSE, 'gift card is pause');
check($out['never_safe'] === true, 'never_safe');
check(str_contains($out['core_rule'], 'Never send money'), 'core rule');
check(!str_contains(strtolower($out['title']), 'safe'), 'title not safe');

$hits = Analyze::lookalikeHits('paypa1.com', []);
check((bool) array_filter($hits, fn ($h) => str_contains($h, 'paypal.com')), 'paypal lookalike');
$rep = Analyze::analyze('', '', 'https://paypa1.com/help');
check($rep['level'] === Analyze::LOOKALIKE, 'lookalike level');

$trusted = [['phone' => '800-555-0100', 'website' => '', 'name' => 'Credit union']];
$phone = Analyze::analyze('', '8005550100', '', $trusted);
check((bool) array_filter($phone['matches'], fn ($m) => str_contains(strtolower($m), 'trusted list')), 'trusted phone');

$empty = Analyze::analyze('');
check(Analyze::analyze('')['level'] === Analyze::UNKNOWN, 'empty unknown');

require $root . '/php/src/Product.php';
check(Product::version() === '1.0.0', 'product version 1.0.0');
check(Product::NAME === 'Family Shield Pro', 'product name');
check(Product::APP === 'OurCircle', 'app name');

$dir = sys_get_temp_dir() . '/ocphp-' . bin2hex(random_bytes(4));
mkdir($dir, 0775, true);
$db = Db::connect($dir . '/t.db');
Db::init($db);
$user = Db::authenticate($db, 'family@ourcircle.app', 'password123');
check($user !== null && $user['email'] === 'family@ourcircle.app', 'demo login');

$robots = file_get_contents($root . '/php/robots.txt') ?: '';
check(str_contains($robots, 'familyshieldpro.com'), 'robots host');
check(str_contains($robots, 'Disallow: /home'), 'robots disallow home');
$sitemap = file_get_contents($root . '/php/sitemap.xml') ?: '';
check(str_contains($sitemap, 'https://familyshieldpro.com/signup'), 'sitemap signup');
check(!str_contains($sitemap, '/home'), 'sitemap no /home');

if ($fail > 0) {
    fwrite(STDERR, "$fail checks failed\n");
    exit(1);
}
echo "PHP checks OK\n";
