<?php
declare(strict_types=1);

/**
 * Copyright (c) 2026 Robert Foster
 *
 * Help tab: simple keyword answers always; Grok when XAI_API_KEY / GROK_API_KEY is set.
 */
final class HelpChat
{
    private const MAX_MSG = 800;
    private const MAX_HISTORY = 8;
    private const MAX_REPLY = 900;
    private const RATE_WINDOW = 600;
    private const RATE_MAX_SESSION = 16;
    private const RATE_MAX_IP = 40;

    public static function supportEmail(): string
    {
        $em = trim(Env::get('SUPPORT_EMAIL'));
        return $em !== '' ? $em : 'support@invcpay.com';
    }

    public static function configured(): bool
    {
        return self::apiKey() !== '' && extension_loaded('curl');
    }

    public static function apiKey(): string
    {
        $k = trim(Env::get('XAI_API_KEY'));
        if ($k === '') {
            $k = trim(Env::get('GROK_API_KEY'));
        }
        return $k;
    }

    public static function model(): string
    {
        $m = trim(Env::get('GROK_MODEL', 'grok-4.6'));
        return $m !== '' ? $m : 'grok-4.6';
    }

    /**
     * @param list<array{role?:string,content?:string}> $history
     */
    public static function reply(string $msg, array $history = []): string
    {
        $msg = trim($msg);
        if (strlen($msg) > self::MAX_MSG) {
            $msg = substr($msg, 0, self::MAX_MSG);
        }
        if ($msg === '') {
            return self::fallback('');
        }
        if (!self::allow()) {
            return 'Please wait a minute, then try Help again — or email ' . self::supportEmail() . '.';
        }
        if (!self::configured()) {
            return self::fallback($msg);
        }
        try {
            $text = self::grok($msg, $history);
            $text = trim($text);
            if ($text === '') {
                return self::fallback($msg);
            }
            if (strlen($text) > self::MAX_REPLY) {
                $text = rtrim(substr($text, 0, self::MAX_REPLY)) . '…';
            }
            return $text;
        } catch (Throwable $e) {
            self::log('error', $e->getMessage());
            return self::fallback($msg);
        }
    }

    public static function fallback(string $msg): string
    {
        $em = self::supportEmail();
        if ($msg === '') {
            return 'Ask me about plans, invoices, reminders, or login. For a person, email ' . $em . '.';
        }
        $low = strtolower($msg);
        if (preg_match('/price|cost|plan|month|year|annual|19|39|99|billing|subscribe|stripe/', $low)) {
            return 'InPmnt by InvcPay: Starter $19/mo, Pro $39/mo, or Starter Annual $99/yr (save $129, over 55% off). The 14-day trial does not need a card. You can change plans later from Billing. Email ' . $em . '.';
        }
        if (preg_match('/login|password|sign in|forgot|reset/', $low)) {
            return 'Sign in at /login. Forgot password is at /forgot-password — it emails a reset link when mail is configured. For a person, email ' . $em . '.';
        }
        if (preg_match('/invoice|remind|chase|client|unpaid|schedule/', $low)) {
            return 'In the app, add unpaid invoices and set polite reminder schedules. InPmnt emails clients so you spend less time chasing late payers.';
        }
        if (preg_match('/trial|free|signup|sign up/', $low)) {
            return 'Start a free trial at /signup. You can subscribe anytime from Billing or the landing page plans.';
        }
        if (preg_match('/admin|owner/', $low)) {
            return 'Workspace admins can open /admin after signing in. That console is for owners only.';
        }
        return 'InPmnt helps you chase unpaid invoices with scheduled reminders. Ask about plans, login, or reminders — or email ' . $em . '.';
    }

    public static function logPath(): string
    {
        $data = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data';
        if (!is_dir($data)) {
            @mkdir($data, 0775, true);
        }
        return $data . DIRECTORY_SEPARATOR . 'help-chat.txt';
    }

    /** @param list<array{role?:string,content?:string}> $history */
    private static function grok(string $msg, array $history): string
    {
        $messages = [['role' => 'system', 'content' => self::systemPrompt()]];
        $n = 0;
        foreach ($history as $row) {
            if ($n >= self::MAX_HISTORY) {
                break;
            }
            if (!is_array($row)) {
                continue;
            }
            $role = strtolower(trim((string) ($row['role'] ?? '')));
            $content = trim((string) ($row['content'] ?? ''));
            if ($content === '' || ($role !== 'user' && $role !== 'assistant')) {
                continue;
            }
            if (strlen($content) > self::MAX_MSG) {
                $content = substr($content, 0, self::MAX_MSG);
            }
            $messages[] = ['role' => $role, 'content' => $content];
            $n++;
        }
        $last = $messages[count($messages) - 1] ?? null;
        if (!is_array($last) || ($last['role'] ?? '') !== 'user' || ($last['content'] ?? '') !== $msg) {
            $messages[] = ['role' => 'user', 'content' => $msg];
        }
        $payload = json_encode([
            'model' => self::model(),
            'messages' => $messages,
            'temperature' => 0.2,
            'max_tokens' => 400,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($payload)) {
            throw new RuntimeException('Could not build Grok request');
        }
        $ch = curl_init('https://api.x.ai/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . self::apiKey(),
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 18,
        ]);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            throw new RuntimeException('Grok request failed' . ($err !== '' ? ': connection' : ''));
        }
        $data = json_decode($raw, true);
        if ($code >= 400) {
            $hint = '';
            if (is_array($data)) {
                $errObj = $data['error'] ?? null;
                if (is_array($errObj)) {
                    $hint = (string) ($errObj['message'] ?? '');
                } elseif (is_string($errObj)) {
                    $hint = $errObj;
                }
            }
            throw new RuntimeException('Grok HTTP ' . $code . ($hint !== '' ? ': ' . substr($hint, 0, 180) : ''));
        }
        return trim((string) (is_array($data) ? ($data['choices'][0]['message']['content'] ?? '') : ''));
    }

    private static function systemPrompt(): string
    {
        $em = self::supportEmail();
        return <<<TXT
You are the Help assistant for InPmnt, an invoice chase / payment reminder app.
Speak in short, plain sentences. Do not invent features or prices.

Product facts:
- Brand: InPmnt by InvcPay. Plans: Starter \$19/mo, Pro \$39/mo, Starter Annual \$99/yr (Starter features; save \$129, over 55% off — not two months free).
- 14-day trial, no credit card required to start. A card is required only when subscribing. Plans can be changed later from Billing. Cancel anytime.
- Stripe processes checkout. InPmnt does not store customers' card details.
- Sign up at /signup. Sign in at /login. Forgot password at /forgot-password.
- In the app, paste unpaid invoices and schedule polite reminder emails to clients.
- Workspace admin console at /admin for admin-role users.
- For a person, email {$em}.

If you do not know, say so and give the email. Do not invent phone numbers.
TXT;
    }

    private static function allow(): bool
    {
        $now = time();
        if (!isset($_SESSION['help_chat_hits']) || !is_array($_SESSION['help_chat_hits'])) {
            $_SESSION['help_chat_hits'] = [];
        }
        $hits = array_values(array_filter(
            $_SESSION['help_chat_hits'],
            static fn ($t): bool => is_int($t) && $t > $now - self::RATE_WINDOW
        ));
        if (count($hits) >= self::RATE_MAX_SESSION) {
            $_SESSION['help_chat_hits'] = $hits;
            return false;
        }
        $hits[] = $now;
        $_SESSION['help_chat_hits'] = $hits;
        return self::allowIp($now);
    }

    private static function allowIp(int $now): bool
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        $secret = Env::get('APP_SECRET', 'inpmnt-help-rate');
        if ($secret === '') {
            $secret = 'inpmnt-help-rate';
        }
        $key = hash_hmac('sha256', $ip, $secret);
        $path = dirname(self::logPath()) . DIRECTORY_SEPARATOR . 'help-rate.json';
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $fh = fopen($path, 'c+');
        if ($fh === false) {
            return true;
        }
        flock($fh, LOCK_EX);
        $raw = stream_get_contents($fh);
        $map = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
        if (!is_array($map)) {
            $map = [];
        }
        $list = [];
        if (isset($map[$key]) && is_array($map[$key])) {
            foreach ($map[$key] as $t) {
                if (is_int($t) && $t > $now - self::RATE_WINDOW) {
                    $list[] = $t;
                }
            }
        }
        $ok = count($list) < self::RATE_MAX_IP;
        if ($ok) {
            $list[] = $now;
        }
        $map[$key] = $list;
        if (count($map) > 400) {
            $map = array_slice($map, -300, null, true);
        }
        rewind($fh);
        ftruncate($fh, 0);
        fwrite($fh, json_encode($map, JSON_UNESCAPED_SLASHES) ?: '{}');
        flock($fh, LOCK_UN);
        fclose($fh);
        return $ok;
    }

    private static function log(string $step, string $message): void
    {
        $path = self::logPath();
        $safe = trim($message);
        $safe = preg_replace('/xai-[A-Za-z0-9_-]+/', 'xai_***', $safe) ?? $safe;
        if (strlen($safe) > 240) {
            $safe = substr($safe, 0, 240) . '…';
        }
        $line = date('c') . ' ' . $step . ': ' . $safe . "\n";
        $prev = is_file($path) ? (string) file_get_contents($path) : '';
        $lines = preg_split("/\r\n|\n|\r/", trim($line . $prev)) ?: [];
        file_put_contents($path, implode("\n", array_slice($lines, 0, 30)) . "\n");
    }
}
