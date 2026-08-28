<?php
declare(strict_types=1);

require dirname(__DIR__) . '/php/src/Env.php';
require dirname(__DIR__) . '/php/src/Mail.php';

function clear_mail_env(): void
{
    foreach (['RESEND_API_KEY', 'SMTP_HOST', 'SMTP_PORT', 'SMTP_USER', 'SMTP_PASSWORD', 'SMTP_SSL', 'SMTP_STARTTLS', 'MAIL_FROM', 'MAIL_FROM_NAME', 'MAIL_PROVIDER'] as $k) {
        putenv($k);
        unset($_ENV[$k]);
    }
}

function assert_true(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
}

clear_mail_env();
assert_true(!Mailer::configured(), 'empty env is not configured');
assert_true(Mailer::status()['provider'] === 'none', 'provider none');

putenv('RESEND_API_KEY=re_...');
$_ENV['RESEND_API_KEY'] = 're_...';
assert_true(!Mailer::configured(), 'placeholder resend is ignored');

clear_mail_env();
putenv('SMTP_HOST=smtp.hostinger.com');
putenv('SMTP_PORT=465');
putenv('SMTP_USER=billing@example.com');
putenv('SMTP_PASSWORD=secret');
putenv('SMTP_SSL=1');
putenv('MAIL_FROM=billing@example.com');
$_ENV['SMTP_HOST'] = 'smtp.hostinger.com';
$_ENV['SMTP_USER'] = 'billing@example.com';
$_ENV['MAIL_FROM'] = 'billing@example.com';
assert_true(Mailer::configured(), 'smtp is configured');
$st = Mailer::status();
assert_true($st['provider'] === 'smtp', 'provider smtp, got ' . $st['provider']);
assert_true($st['smtp_host'] === 'smtp.hostinger.com', 'smtp host');
assert_true($st['smtp_port'] === 465, 'smtp port');

putenv('RESEND_API_KEY=re_not_a_placeholder_key_value');
$_ENV['RESEND_API_KEY'] = 're_not_a_placeholder_key_value';
$st = Mailer::status();
assert_true($st['provider'] === 'resend_then_smtp', 'both providers, got ' . $st['provider']);

putenv('MAIL_PROVIDER=smtp');
$_ENV['MAIL_PROVIDER'] = 'smtp';
$st = Mailer::status();
assert_true($st['provider'] === 'smtp', 'MAIL_PROVIDER=smtp forces smtp, got ' . $st['provider']);

fwrite(STDOUT, "ok\n");
