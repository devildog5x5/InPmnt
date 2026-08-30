<?php
declare(strict_types=1);

/** Twilio SMS for Family Shield Pro circle invites, alerts, and inbound checks. Not InPmnt. */
final class Sms
{
    public static function configured(): bool
    {
        $sid = trim(Env::get('TWILIO_ACCOUNT_SID'));
        $token = trim(Env::get('TWILIO_AUTH_TOKEN'));
        $from = trim(Env::get('TWILIO_FROM'));
        if (str_contains($sid, '...') || str_contains($token, '...') || str_contains($from, '...')) {
            return false;
        }
        return str_starts_with($sid, 'AC') && strlen($token) >= 16 && str_starts_with($from, '+');
    }

    public static function normalizePhone(string $raw): string
    {
        $s = trim($raw);
        if ($s === '') {
            return '';
        }
        $digits = Analyze::digitsOnly($s);
        if (strlen($digits) === 10) {
            return '+1' . $digits;
        }
        $all = preg_replace('/\D+/', '', $s) ?? '';
        if (str_starts_with($s, '+') && strlen($all) >= 10 && strlen($all) <= 15) {
            return '+' . $all;
        }
        return '';
    }

    public static function inviteBody(string $join, string $inviter = ''): string
    {
        $who = trim($inviter) !== '' ? trim($inviter) : 'Your family';
        return 'Family Shield Pro: ' . $who . " invited you to their circle.\n"
            . $join . "\n"
            . 'Tap the link to join. ' . Analyze::CORE_RULE . ' Reply STOP to opt out.';
    }

    public static function alertBody(string $name, string $checkUrl): string
    {
        return 'PLEASE CALL ' . $name . " before they pay.\n"
            . $checkUrl . "\n"
            . 'Tap the link to open the check. ' . Analyze::CORE_RULE . ' Reply STOP to opt out.';
    }

    public static function checkBody(string $title, string $checkUrl): string
    {
        return 'OurCircle: ' . $title . "\n"
            . $checkUrl . "\n"
            . 'Tap the link. ' . Analyze::GUIDANCE . ' ' . Analyze::CORE_RULE;
    }

    public static function classifyInbound(string $body): string
    {
        $text = strtoupper(trim($body));
        if (in_array($text, ['STOP', 'STOPALL', 'UNSUBSCRIBE', 'CANCEL', 'END', 'QUIT'], true)) {
            return 'stop';
        }
        if (in_array($text, ['START', 'YES', 'UNSTOP'], true)) {
            return 'start';
        }
        if (in_array($text, ['HELP', 'INFO'], true)) {
            return 'help';
        }
        return 'check';
    }

    public static function inboundAutoReply(string $action): string
    {
        return match ($action) {
            'stop' => 'Family Shield Pro: you are opted out of SMS. Reply START to turn texts back on.',
            'start' => 'Family Shield Pro: SMS is on. Forward a sketchy text here to pause with your circle. Reply STOP to opt out.',
            'help' => 'Family Shield Pro (OurCircle): forward a suspicious text here to pause with your circle. ' . Analyze::CORE_RULE . ' Reply STOP to opt out. Email CustomerService@FamilyShieldPro.com',
            default => '',
        };
    }

    public static function twiml(string $message): string
    {
        $body = htmlspecialchars(trim($message) !== '' ? trim($message) : ' ', ENT_XML1 | ENT_QUOTES, 'UTF-8');
        return '<?xml version="1.0" encoding="UTF-8"?><Response><Message>' . $body . '</Message></Response>';
    }

    /** @param array<string,string> $params */
    public static function validSignature(string $url, array $params, string $header): bool
    {
        $token = trim(Env::get('TWILIO_AUTH_TOKEN'));
        if ($token === '' || str_contains($token, '...')) {
            return false;
        }
        ksort($params);
        $data = $url;
        foreach ($params as $k => $v) {
            $data .= $k . $v;
        }
        $expected = base64_encode(hash_hmac('sha1', $data, $token, true));
        return hash_equals($expected, trim($header));
    }

    public static function send(string $to, string $body): void
    {
        $dest = self::normalizePhone($to);
        if ($dest === '') {
            throw new RuntimeException('Need a mobile number');
        }
        if (!self::configured()) {
            throw new RuntimeException(
                'SMS is not configured. Set TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN, and TWILIO_FROM in .env'
            );
        }
        $sid = trim(Env::get('TWILIO_ACCOUNT_SID'));
        $token = trim(Env::get('TWILIO_AUTH_TOKEN'));
        $from = trim(Env::get('TWILIO_FROM'));
        $ch = curl_init('https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($sid) . '/Messages.json');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_USERPWD => $sid . ':' . $token,
            CURLOPT_POSTFIELDS => http_build_query([
                'To' => $dest,
                'From' => $from,
                'Body' => $body,
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            throw new RuntimeException('Twilio error: ' . $err);
        }
        if ($code >= 400) {
            throw new RuntimeException("Twilio error {$code}: {$raw}");
        }
    }
}
