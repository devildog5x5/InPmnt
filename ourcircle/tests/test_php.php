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
check(Product::version() === '1.2.5', 'product version 1.2.5');
check(Product::NAME === 'Family Shield Pro', 'product name');
check(Product::APP === 'OurCircle', 'app name');

$landing = file_get_contents($root . '/php/views/landing.php') ?: '';
check(str_contains($landing, '$14.99'), 'landing monthly price');
check(str_contains($landing, '$119.99'), 'landing yearly price');
check(str_contains($landing, 'too good to be true'), 'landing too-good rule');
check(str_contains($landing, 'Really! Really! Really!'), 'landing really');
check(str_contains($landing, 'Why we built this'), 'landing why');
check(str_contains($landing, 'CustomerService@FamilyShieldPro.com'), 'landing support email');
check(str_contains($landing, 'guidance, not a guarantee'), 'landing guidance disclaimer');
check(!str_contains($landing, 'href="/offers"'), 'landing no offers link');
check(!is_file($root . '/php/views/offers.php'), 'php offers view gone');
check(!str_contains($landing, 'not InPmnt'), 'landing no product-identity footer');
check(!str_contains($landing, 'Hostinger PHP'), 'landing no hostinger footer');
check(!str_contains($landing, 'robots.txt'), 'landing no robots footer');
check(is_file($root . '/SUPPORT.md'), 'support setup doc');
check(is_file($root . '/php/src/SupportChat.php'), 'php support chat class');
check(is_file($root . '/php/views/support_chat.php'), 'php chat widget');
check(!str_contains($landing, '$7.99'), 'landing no old monthly');
check(!str_contains($landing, 'Founding year'), 'landing no founding sku');
$plans = file_get_contents($root . '/php/src/App.php') ?: '';
check(str_contains($plans, "'id' => 'monthly'"), 'php monthly plan');
check(str_contains($plans, "'id' => 'yearly'"), 'php yearly plan');
check(str_contains($plans, '$14.99/month'), 'php monthly price');
check(str_contains($plans, '$119.99/year'), 'php yearly price');
check(is_file($root . '/php/src/Billing.php'), 'php billing class');

require $root . '/php/src/Env.php';
require $root . '/php/src/Billing.php';
require $root . '/php/src/Auth.php';
require $root . '/php/src/SupportChat.php';
$otp = Auth::otpauthUri('family@ourcircle.app', 'JBSWY3DPEHPK3PXP');
check(str_starts_with($otp, 'otpauth://totp/'), 'otpauth uri scheme');
check(str_contains($otp, 'secret=JBSWY3DPEHPK3PXP'), 'otpauth uri secret');
$setup = file_get_contents($root . '/php/views/account_2fa_setup.php') ?: '';
check(str_contains($setup, 'otp-qr'), '2fa setup qr box');
check(str_contains($setup, 'qrcode.min.js'), '2fa setup qr script');
check(is_file($root . '/static/js/qrcode.min.js'), 'qrcode js vendored');
$priceChat = SupportChat::faqReply('how much does a family plan cost?');
check(str_contains($priceChat, '14.99'), 'chat price faq');
$safeChat = strtolower(SupportChat::faqReply('is this paypal email safe to pay?'));
check(!str_contains($safeChat, 'this is safe'), 'chat never this is safe');
check(str_contains($safeChat, 'never'), 'chat says never');
$moneyChat = SupportChat::faqReply('if someone asks me to send them money, should i do it?');
check(str_contains($moneyChat, 'NO!!!'), 'chat send-money no');
check(str_contains(strtolower($moneyChat), 'without a doubt'), 'chat send-money doubt');
check(str_contains(strtolower($moneyChat), 'family member'), 'chat send-money family');
check(Billing::config()['enabled'] === false, 'stripe off without keys');
$payload = '{"id":"evt_test","object":"event","type":"checkout.session.completed","data":{"object":{}}}';
$secret = 'whsec_testsecret_abcdefghijklmnopqrstuvwxyz';
$ts = (string) time();
$sig = hash_hmac('sha256', $ts . '.' . $payload, $secret);
$event = Billing::constructEvent($payload, "t={$ts},v1={$sig}", $secret);
check(($event['type'] ?? '') === 'checkout.session.completed', 'stripe signed webhook');
try {
    Billing::constructEvent($payload, "t={$ts},v1=deadbeef", $secret);
    check(false, 'bad stripe sig should throw');
} catch (Throwable $e) {
    check(str_contains($e->getMessage(), 'Invalid Stripe signature'), 'bad stripe sig rejected');
}

$dir = sys_get_temp_dir() . '/ocphp-' . bin2hex(random_bytes(4));
mkdir($dir, 0775, true);
$db = Db::connect($dir . '/t.db');
Db::init($db);
$user = Db::authenticate($db, 'family@ourcircle.app', 'password123');
check($user !== null && $user['email'] === 'family@ourcircle.app', 'demo login');
$cols = array_map(fn ($c) => $c['name'], $db->query('PRAGMA table_info(households)')->fetchAll());
check(in_array('stripe_customer_id', $cols, true), 'household stripe_customer_id');
check(in_array('stripe_subscription_id', $cols, true), 'household stripe_subscription_id');
$ucols = array_map(fn ($c) => $c['name'], $db->query('PRAGMA table_info(users)')->fetchAll());
check(in_array('totp_secret', $ucols, true), 'user totp_secret');
check(in_array('recovery_codes', $ucols, true), 'user recovery_codes');
$secret = Auth::newSecret();
$code = Auth::totpAt($secret, 1_111_111_111);
check(Auth::verifyTotp($secret, $code, 1_111_111_111), 'totp verifies at timestamp');
check(!Auth::verifyTotp($secret, '000000', 1_111_111_111), 'totp rejects wrong code');
$codes = Auth::newRecoveryCodes(3);
$stored = Auth::hashList($codes);
$after = Auth::consumeRecovery($stored, $codes[0]);
check(is_string($after), 'recovery consume once');
check(Auth::consumeRecovery($after, $codes[0]) === null, 'recovery code not reusable');

$robots = file_get_contents($root . '/php/robots.txt') ?: '';
check(str_contains($robots, 'Allow: /forgot'), 'robots allow forgot');
check(!str_contains($robots, 'Allow: /offers'), 'robots no offers');
check(str_contains($robots, 'Disallow: /support'), 'robots disallow support');
check(str_contains($robots, 'familyshieldpro.com'), 'robots host');
check(str_contains($robots, 'Disallow: /home'), 'robots disallow home');
$sitemap = file_get_contents($root . '/php/sitemap.xml') ?: '';
check(str_contains($sitemap, 'https://familyshieldpro.com/signup'), 'sitemap signup');
check(!str_contains($sitemap, '/offers'), 'sitemap no offers');
check(!str_contains($sitemap, '/home'), 'sitemap no /home');

if ($fail > 0) {
    fwrite(STDERR, "$fail checks failed\n");
    exit(1);
}
echo "PHP checks OK\n";
