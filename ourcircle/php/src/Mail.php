<?php
declare(strict_types=1);

/** Outbound mail for Family Shield Pro password reset and circle invites. Not InPmnt. */
final class Mailer
{
    public static function configured(): bool
    {
        $resend = trim(Env::get('RESEND_API_KEY'));
        if ($resend !== '' && !str_contains($resend, '...')) {
            return true;
        }
        return trim(Env::get('SMTP_HOST')) !== ''
            && trim(Env::get('SMTP_USER')) !== ''
            && trim(Env::get('SMTP_PASSWORD')) !== ''
            && (trim(Env::get('MAIL_FROM')) !== '' || trim(Env::get('SMTP_USER')) !== '');
    }

    public static function notSetupMessage(): string
    {
        return 'Reset email is not set up on this site yet. In .env set SMTP_HOST=smtp.hostinger.com, '
            . 'SMTP_PORT=465, SMTP_SSL=1, SMTP_USER and MAIL_FROM to the Hostinger mailbox, and SMTP_PASSWORD '
            . 'to that mailbox password (no quotes). Recovery codes on this page still work if you turned on 2FA.';
    }

    public static function sendFailedMessage(string $detail = ''): string
    {
        $base = 'We could not send the reset email. Check SMTP in .env: Hostinger smtp.hostinger.com, port 465, SSL, '
            . 'and the mailbox password. Recovery codes on this page still work.';
        $detail = self::safeDetail($detail);
        return $detail !== '' ? $base . ' Last error: ' . $detail : $base;
    }

    public static function lastStatus(): string
    {
        $path = self::statusPath();
        if (!is_file($path)) {
            return '';
        }
        $raw = trim((string) file_get_contents($path));
        return self::safeDetail($raw);
    }

    public static function send(string $to, string $subject, string $body, ?string $fromName = null, ?string $html = null): void
    {
        try {
            self::deliver($to, $subject, $body, $fromName, $html);
            self::rememberStatus('ok');
        } catch (Throwable $e) {
            self::rememberStatus($e->getMessage());
            throw $e;
        }
    }

    public static function htmlFromText(string $text): string
    {
        $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $linked = preg_replace('#(https?://[^\s<]+)#', '<a href="$1">$1</a>', $escaped) ?? $escaped;
        return '<!DOCTYPE html><html><body style="font-family:system-ui,sans-serif;line-height:1.5;color:#1d1e20">'
            . '<div style="white-space:pre-wrap">' . $linked . '</div></body></html>';
    }

    private static function deliver(string $to, string $subject, string $body, ?string $fromName, ?string $html): void
    {
        $to = trim($to);
        if ($to === '' || !str_contains($to, '@')) {
            throw new RuntimeException('Need an email address');
        }
        $mailFrom = trim(Env::get('MAIL_FROM') ?: Env::get('SMTP_USER'));
        if ($mailFrom === '') {
            throw new RuntimeException('MAIL_FROM is not set');
        }
        $display = trim($fromName ?: Env::get('MAIL_FROM_NAME', 'Family Shield Pro'));
        $fromHeader = "{$display} <{$mailFrom}>";
        $html = $html !== null && trim($html) !== '' ? $html : self::htmlFromText($body);

        $resend = trim(Env::get('RESEND_API_KEY'));
        if ($resend !== '' && !str_contains($resend, '...')) {
            self::resend($resend, $fromHeader, $to, $subject, $body, $html);
            return;
        }
        $host = trim(Env::get('SMTP_HOST'));
        if ($host === '') {
            throw new RuntimeException(
                'Email is not configured. Set SMTP_HOST, SMTP_USER, SMTP_PASSWORD, and MAIL_FROM in .env (or RESEND_API_KEY).'
            );
        }
        self::smtp($host, $fromHeader, $mailFrom, $to, $subject, $body, $html);
    }

    private static function resend(string $apiKey, string $from, string $to, string $subject, string $body, string $html): void
    {
        $payload = json_encode([
            'from' => $from,
            'to' => [$to],
            'subject' => $subject !== '' ? $subject : '(no subject)',
            'text' => $body,
            'html' => $html,
        ], JSON_UNESCAPED_SLASHES);
        $ch = curl_init('https://api.resend.com/emails');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            throw new RuntimeException('Resend error: ' . $err);
        }
        if ($code >= 400) {
            throw new RuntimeException("Resend error {$code}: {$raw}");
        }
    }

    private static function smtp(
        string $host,
        string $fromHeader,
        string $mailFrom,
        string $to,
        string $subject,
        string $body,
        string $html
    ): void {
        $port = (int) (Env::get('SMTP_PORT', '587') ?: '587');
        $user = trim(Env::get('SMTP_USER'));
        $password = Env::get('SMTP_PASSWORD');
        $useSsl = Env::truthy('SMTP_SSL') || $port === 465;
        if ($user !== '' && $password === '') {
            throw new RuntimeException(
                'SMTP_PASSWORD is not set. Use the Hostinger mailbox password in .env (no quotes).'
            );
        }
        if ($useSsl && !extension_loaded('openssl')) {
            throw new RuntimeException(
                'PHP openssl is off. In hPanel → Advanced → PHP Configuration, enable openssl. Hostinger SMTP on port 465 needs it.'
            );
        }
        // Do not fall back to PHP mail() — Hostinger often returns true without delivering.
        self::smtpSocket($host, $port, $user, $password, $fromHeader, $mailFrom, $to, $subject, $body, $html, $useSsl);
    }

    /** @return array{configured:bool,openssl:bool,host:string,port:string,ssl:bool,user:string,from:string,last:string} */
    public static function publicInfo(): array
    {
        $port = trim(Env::get('SMTP_PORT', '587') ?: '587');
        return [
            'configured' => self::configured(),
            'openssl' => extension_loaded('openssl'),
            'host' => trim(Env::get('SMTP_HOST')),
            'port' => $port,
            'ssl' => Env::truthy('SMTP_SSL') || $port === '465',
            'user' => trim(Env::get('SMTP_USER')),
            'from' => trim(Env::get('MAIL_FROM') ?: Env::get('SMTP_USER')),
            'last' => self::lastStatus(),
        ];
    }

    public static function testEmailBody(): string
    {
        return "If you received this, Family Shield Pro SMTP is working.\n\n"
            . 'Product: ' . Product::label() . "\n"
            . "Not InPmnt.\n\n"
            . "Invites, password-reset links, and “Please call me before I pay” emails all use this mailbox.\n";
    }

    private static function smtpSocket(
        string $host,
        int $port,
        string $user,
        string $password,
        string $fromHeader,
        string $mailFrom,
        string $to,
        string $subject,
        string $body,
        string $html,
        bool $ssl
    ): void {
        $ctx = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
                'SNI_enabled' => true,
                'peer_name' => $host,
            ],
        ]);
        $remote = ($ssl ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $fp = @stream_socket_client($remote, $errno, $errstr, 25, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) {
            throw new RuntimeException("SMTP connect failed ({$host}:{$port}): {$errstr}");
        }
        stream_set_timeout($fp, 25);
        try {
            $ehlo = self::ehloName();
            self::smtpExpect($fp, [220]);
            self::smtpExpect($fp, [250], 'EHLO ' . $ehlo);
            if (!$ssl && Env::get('SMTP_STARTTLS', '1') !== '0') {
                self::smtpExpect($fp, [220], 'STARTTLS');
                $ok = @stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                if ($ok !== true) {
                    throw new RuntimeException('SMTP STARTTLS failed');
                }
                self::smtpExpect($fp, [250], 'EHLO ' . $ehlo);
            }
            if ($user !== '') {
                self::smtpAuth($fp, $user, $password);
            }
            self::smtpExpect($fp, [250], 'MAIL FROM:<' . $mailFrom . '>');
            self::smtpExpect($fp, [250, 251], 'RCPT TO:<' . $to . '>');
            self::smtpExpect($fp, [354], 'DATA');
            $msgid = bin2hex(random_bytes(12)) . '@' . $ehlo;
            $boundary = '=_fsp_' . bin2hex(random_bytes(8));
            $msg = 'Date: ' . gmdate('D, d M Y H:i:s') . " +0000\r\n"
                . 'Message-ID: <' . $msgid . ">\r\n"
                . 'Subject: ' . self::headerSafe($subject !== '' ? $subject : '(no subject)') . "\r\n"
                . 'From: ' . $fromHeader . "\r\n"
                . 'Reply-To: ' . $mailFrom . "\r\n"
                . 'To: ' . $to . "\r\n"
                . "MIME-Version: 1.0\r\n"
                . 'Content-Type: multipart/alternative; boundary="' . $boundary . "\"\r\n\r\n"
                . '--' . $boundary . "\r\n"
                . "Content-Type: text/plain; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: 8bit\r\n\r\n"
                . self::dotStuff($body) . "\r\n"
                . '--' . $boundary . "\r\n"
                . "Content-Type: text/html; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: 8bit\r\n\r\n"
                . self::dotStuff($html) . "\r\n"
                . '--' . $boundary . "--";
            fwrite($fp, $msg . "\r\n.\r\n");
            self::smtpExpect($fp, [250]);
            try {
                self::smtpExpect($fp, [221], 'QUIT');
            } catch (Throwable) {
                // Some servers close after 250 without a 221.
            }
        } finally {
            fclose($fp);
        }
    }

    /** @param resource $fp */
    private static function smtpAuth($fp, string $user, string $password): void
    {
        $offer = self::smtpTry($fp, 'AUTH LOGIN');
        if (str_starts_with($offer, '334')) {
            self::smtpExpect($fp, [334], base64_encode($user));
            $auth = self::smtpTry($fp, base64_encode($password));
            if (str_starts_with($auth, '235')) {
                return;
            }
        }
        $plain = base64_encode("\0{$user}\0{$password}");
        $auth = self::smtpTry($fp, 'AUTH PLAIN ' . $plain);
        if (!str_starts_with($auth, '235')) {
            throw new RuntimeException(
                'SMTP login failed. SMTP_USER and SMTP_PASSWORD must match the Hostinger mailbox exactly (no quotes).'
            );
        }
    }

    private static function ehloName(): string
    {
        $site = Env::get('OURCIRCLE_SITE_URL', Env::get('BASE_URL', 'https://familyshieldpro.com'));
        $host = parse_url($site, PHP_URL_HOST);
        if (is_string($host) && $host !== '') {
            return $host;
        }
        return 'familyshieldpro.com';
    }

    private static function headerSafe(string $value): string
    {
        return str_replace(["\r", "\n"], ' ', $value);
    }

    private static function dotStuff(string $body): string
    {
        $body = str_replace(["\r\n", "\r"], "\n", $body);
        $lines = explode("\n", $body);
        foreach ($lines as $i => $line) {
            if (str_starts_with($line, '.')) {
                $lines[$i] = '.' . $line;
            }
        }
        return implode("\r\n", $lines);
    }

    /** @param resource $fp */
    private static function smtpRead($fp): string
    {
        $line = '';
        while (!feof($fp)) {
            $chunk = fgets($fp, 515);
            if ($chunk === false) {
                break;
            }
            $line .= $chunk;
            if (strlen($chunk) >= 4 && $chunk[3] === ' ') {
                break;
            }
        }
        return $line;
    }

    /**
     * @param resource $fp
     * @param list<int> $ok
     */
    private static function smtpExpect($fp, array $ok, string $cmd = ''): string
    {
        $line = self::smtpTry($fp, $cmd);
        $code = (int) substr($line, 0, 3);
        if (!in_array($code, $ok, true)) {
            $verb = 'SMTP';
            if ($cmd === '') {
                $verb = 'banner';
            } else {
                $first = strtoupper((string) (preg_split('/\s+/', $cmd)[0] ?? ''));
                if (str_starts_with($first, 'AUTH')) {
                    $verb = 'AUTH';
                } elseif (preg_match('/^[A-Z]{3,12}$/', $first)) {
                    $verb = $first;
                }
            }
            throw new RuntimeException('SMTP ' . ($code > 0 ? (string) $code : 'no-reply') . ' on ' . $verb . ': ' . trim($line));
        }
        return $line;
    }

    /** @param resource $fp */
    private static function smtpTry($fp, string $cmd = ''): string
    {
        if ($cmd !== '') {
            fwrite($fp, $cmd . "\r\n");
        }
        return self::smtpRead($fp);
    }

    private static function rememberStatus(string $message): void
    {
        $dir = dirname(self::statusPath());
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $stamp = gmdate('Y-m-d\TH:i:s\Z');
        $line = $stamp . ' ' . self::safeDetail($message) . "\n";
        @file_put_contents(self::statusPath(), $line);
    }

    private static function statusPath(): string
    {
        return dirname(__DIR__) . '/data/mail_last_error.txt';
    }

    private static function safeDetail(string $message): string
    {
        $message = preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f]/', '', $message) ?? $message;
        $message = preg_replace('/SMTP_PASSWORD\s*=\s*\S+/i', 'SMTP_PASSWORD=***', $message) ?? $message;
        $message = trim($message);
        if (strlen($message) > 500) {
            $message = substr($message, 0, 500) . '…';
        }
        return $message;
    }
}
