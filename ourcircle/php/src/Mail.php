<?php
declare(strict_types=1);

/** Outbound mail for Family Shield Pro password reset and circle invites. Not InPmnt. */
final class Mailer
{
    public static function configured(): bool
    {
        if (trim(Env::get('RESEND_API_KEY')) !== '' && !str_contains(Env::get('RESEND_API_KEY'), '...')) {
            return true;
        }
        return trim(Env::get('SMTP_HOST')) !== ''
            && trim(Env::get('SMTP_USER')) !== ''
            && (trim(Env::get('MAIL_FROM')) !== '' || trim(Env::get('SMTP_USER')) !== '');
    }

    public static function send(string $to, string $subject, string $body, ?string $fromName = null): void
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

        $resend = trim(Env::get('RESEND_API_KEY'));
        if ($resend !== '' && !str_contains($resend, '...')) {
            self::resend($resend, $fromHeader, $to, $subject, $body);
            return;
        }
        $host = trim(Env::get('SMTP_HOST'));
        if ($host === '') {
            throw new RuntimeException(
                'Email is not configured. Set RESEND_API_KEY or SMTP_HOST/SMTP_USER/MAIL_FROM in .env'
            );
        }
        self::smtp($host, $fromHeader, $mailFrom, $to, $subject, $body);
    }

    private static function resend(string $apiKey, string $from, string $to, string $subject, string $body): void
    {
        $payload = json_encode([
            'from' => $from,
            'to' => [$to],
            'subject' => $subject !== '' ? $subject : '(no subject)',
            'text' => $body,
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
        string $body
    ): void {
        $port = (int) (Env::get('SMTP_PORT', '587') ?: '587');
        $user = trim(Env::get('SMTP_USER'));
        $password = Env::get('SMTP_PASSWORD');
        $useSsl = Env::truthy('SMTP_SSL') || $port === 465;
        // Hostinger's mail() often returns true without delivering. Use SMTP first.
        try {
            self::smtpSocket($host, $port, $user, $password, $fromHeader, $mailFrom, $to, $subject, $body, $useSsl);
            return;
        } catch (Throwable $smtpErr) {
            $headers = [
                'From: ' . $fromHeader,
                'Reply-To: ' . $mailFrom,
                'Content-Type: text/plain; charset=UTF-8',
            ];
            $ok = @mail($to, $subject !== '' ? $subject : '(no subject)', $body, implode("\r\n", $headers), '-f' . $mailFrom);
            if (!$ok) {
                throw $smtpErr;
            }
        }
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
        bool $ssl
    ): void {
        $remote = ($ssl ? 'ssl://' : '') . $host . ':' . $port;
        $fp = @stream_socket_client($remote, $errno, $errstr, 30);
        if (!$fp) {
            throw new RuntimeException("SMTP connect failed: {$errstr}");
        }
        $read = static function () use ($fp): string {
            $line = '';
            while (!feof($fp)) {
                $chunk = fgets($fp, 515);
                if ($chunk === false) {
                    break;
                }
                $line .= $chunk;
                if (isset($chunk[3]) && $chunk[3] === ' ') {
                    break;
                }
            }
            return $line;
        };
        $cmd = static function (string $c) use ($fp, $read): string {
            fwrite($fp, $c . "\r\n");
            return $read();
        };
        $read();
        $cmd('EHLO familyshieldpro');
        if (!$ssl && Env::get('SMTP_STARTTLS', '1') !== '0') {
            $cmd('STARTTLS');
            stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $cmd('EHLO familyshieldpro');
        }
        if ($user !== '') {
            $cmd('AUTH LOGIN');
            $cmd(base64_encode($user));
            $resp = $cmd(base64_encode($password));
            if (!str_starts_with($resp, '235')) {
                fclose($fp);
                throw new RuntimeException('SMTP login failed');
            }
        }
        $cmd('MAIL FROM:<' . $mailFrom . '>');
        $cmd('RCPT TO:<' . $to . '>');
        $cmd('DATA');
        $msg = 'Subject: ' . ($subject !== '' ? $subject : '(no subject)') . "\r\n"
            . 'From: ' . $fromHeader . "\r\n"
            . 'To: ' . $to . "\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n\r\n"
            . $body . "\r\n.";
        $cmd($msg);
        $cmd('QUIT');
        fclose($fp);
    }
}
