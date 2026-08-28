<?php
declare(strict_types=1);

final class Mailer
{
    public static function configured(): bool
    {
        return self::resendKey() !== '' || self::smtpConfigured();
    }

    /** @return array{configured:bool,provider:string,resend:bool,smtp:bool,mail_from:string,smtp_host:string,smtp_port:int} */
    public static function status(): array
    {
        $resend = self::resendKey() !== '';
        $smtp = self::smtpConfigured();
        $force = strtolower(trim(Env::get('MAIL_PROVIDER')));
        $provider = 'none';
        if ($force === 'smtp' && $smtp) {
            $provider = 'smtp';
        } elseif ($force === 'resend' && $resend) {
            $provider = 'resend';
        } elseif ($resend && $smtp) {
            $provider = 'resend_then_smtp';
        } elseif ($resend) {
            $provider = 'resend';
        } elseif ($smtp) {
            $provider = 'smtp';
        }
        return [
            'configured' => self::configured(),
            'provider' => $provider,
            'resend' => $resend,
            'smtp' => $smtp,
            'mail_from' => self::mailFrom(),
            'smtp_host' => $smtp ? trim(Env::get('SMTP_HOST')) : '',
            'smtp_port' => $smtp ? self::smtpPort() : 0,
        ];
    }

    public static function send(string $to, string $subject, string $body, ?string $fromName = null, array $attachments = []): array
    {
        $to = trim($to);
        if ($to === '' || !str_contains($to, '@')) {
            throw new RuntimeException('Client has no email address');
        }
        $mailFrom = self::mailFrom();
        if ($mailFrom === '') {
            throw new RuntimeException('MAIL_FROM is not set');
        }
        $display = trim($fromName ?: Env::get('MAIL_FROM_NAME', 'InPmnt'));
        $fromHeader = self::fromHeader($display, $mailFrom);

        $force = strtolower(trim(Env::get('MAIL_PROVIDER')));
        $resend = self::resendKey();
        $smtpOk = self::smtpConfigured();
        $tryResend = $resend !== '' && $force !== 'smtp';
        $trySmtp = $smtpOk && ($force === 'smtp' || !$tryResend);

        $errors = [];
        if ($tryResend) {
            try {
                self::resend($resend, $fromHeader, $to, $subject, $body, $attachments);
                return ['provider' => 'resend', 'id' => null];
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
                if (!$smtpOk || $force === 'resend') {
                    throw $e;
                }
                $trySmtp = true;
            }
        }
        if ($trySmtp) {
            $host = trim(Env::get('SMTP_HOST'));
            try {
                self::smtpSocket($host, $fromHeader, $mailFrom, $to, $subject, $body, $attachments);
                return ['provider' => 'smtp', 'id' => null];
            } catch (Throwable $e) {
                if ($errors) {
                    throw new RuntimeException($errors[0] . '; SMTP fallback: ' . $e->getMessage());
                }
                throw $e;
            }
        }
        throw new RuntimeException(
            'Email is not configured. Set RESEND_API_KEY or SMTP_HOST/SMTP_USER/MAIL_FROM in .env'
        );
    }

    private static function mailFrom(): string
    {
        return trim(Env::get('MAIL_FROM') ?: Env::get('SMTP_USER'));
    }

    private static function resendKey(): string
    {
        $key = trim(Env::get('RESEND_API_KEY'));
        if ($key === '' || str_starts_with($key, 're_...')) {
            return '';
        }
        $lower = strtolower($key);
        if ($lower === 're_your_api_key' || str_contains($lower, 'changeme')) {
            return '';
        }
        return $key;
    }

    private static function smtpConfigured(): bool
    {
        return trim(Env::get('SMTP_HOST')) !== ''
            && trim(Env::get('SMTP_USER')) !== ''
            && self::mailFrom() !== '';
    }

    private static function smtpPort(): int
    {
        return (int) (Env::get('SMTP_PORT', '587') ?: '587');
    }

    private static function fromHeader(string $display, string $mailFrom): string
    {
        $display = str_replace(["\r", "\n"], '', $display);
        if ($display === '') {
            return $mailFrom;
        }
        if (preg_match('/[^\x20-\x7E]/', $display) || strpbrk($display, '",<>') !== false) {
            $encoded = '=?UTF-8?B?' . base64_encode($display) . '?=';
            return "{$encoded} <{$mailFrom}>";
        }
        return "{$display} <{$mailFrom}>";
    }

    private static function headerSafe(string $value): string
    {
        $value = str_replace(["\r", "\n"], '', $value);
        if ($value !== '' && preg_match('/[^\x20-\x7E]/', $value)) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }
        return $value;
    }

    private static function resend(string $apiKey, string $from, string $to, string $subject, string $body, array $attachments = []): void
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('Resend error: PHP curl extension is not enabled');
        }
        $payloadArr = [
            'from' => $from,
            'to' => [$to],
            'subject' => $subject !== '' ? $subject : '(no subject)',
            'text' => $body,
        ];
        if ($attachments) {
            $payloadArr['attachments'] = [];
            foreach ($attachments as $att) {
                $payloadArr['attachments'][] = [
                    'filename' => (string) ($att['filename'] ?? 'invoice.pdf'),
                    'content' => base64_encode((string) ($att['content'] ?? '')),
                ];
            }
        }
        $payload = json_encode($payloadArr, JSON_UNESCAPED_SLASHES);
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

    private static function smtpSocket(
        string $host,
        string $fromHeader,
        string $mailFrom,
        string $to,
        string $subject,
        string $body,
        array $attachments = []
    ): void {
        $port = self::smtpPort();
        $user = trim(Env::get('SMTP_USER'));
        $password = Env::get('SMTP_PASSWORD');
        $useSsl = Env::truthy('SMTP_SSL') || $port === 465;
        $wantStartTls = !$useSsl && Env::get('SMTP_STARTTLS', '1') !== '0';

        $err = '';
        $fp = self::smtpConnect($host, $port, $useSsl, true, $err);
        if ($fp === false) {
            $fp = self::smtpConnect($host, $port, $useSsl, false, $err);
        }
        if ($fp === false) {
            throw new RuntimeException("SMTP connect failed: {$host}:{$port} {$err}");
        }
        stream_set_timeout($fp, 30);

        try {
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
            $expect = static function (string $resp, string $okPrefix, string $what): string {
                if (!str_starts_with(ltrim($resp), $okPrefix)) {
                    $trim = trim(str_replace(["\r", "\n"], ' ', $resp));
                    throw new RuntimeException("SMTP {$what} failed: {$trim}");
                }
                return $resp;
            };

            $greeting = $read();
            $expect($greeting, '220', 'connect');
            $ehlo = $cmd('EHLO inpmnt.local');
            if (!str_starts_with(ltrim($ehlo), '250')) {
                $ehlo = $cmd('HELO inpmnt.local');
                $expect($ehlo, '250', 'HELO');
            }
            if ($wantStartTls) {
                $tls = $cmd('STARTTLS');
                $expect($tls, '220', 'STARTTLS');
                $cryptoOk = @stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                if ($cryptoOk !== true) {
                    throw new RuntimeException('SMTP STARTTLS handshake failed');
                }
                $cmd('EHLO inpmnt.local');
            }
            if ($user !== '') {
                self::smtpAuth($cmd, $expect, $user, $password);
            }
            $expect($cmd('MAIL FROM:<' . $mailFrom . '>'), '250', 'MAIL FROM');
            $expect($cmd('RCPT TO:<' . $to . '>'), '25', 'RCPT TO');
            $expect($cmd('DATA'), '354', 'DATA');
            $safeSubject = self::headerSafe($subject !== '' ? $subject : '(no subject)');
            $payload = self::rfc822($fromHeader, $mailFrom, $to, $safeSubject, $body, $attachments);
            $expect($cmd($payload), '250', 'message');
            $cmd('QUIT');
        } finally {
            fclose($fp);
        }
    }

    /** @param list<array{filename?:string,content?:string,mime?:string}> $attachments */
    private static function rfc822(
        string $fromHeader,
        string $mailFrom,
        string $to,
        string $safeSubject,
        string $body,
        array $attachments
    ): string {
        $dot = static function (string $s): string {
            $s = str_replace(["\r\n", "\r"], "\n", $s);
            $s = preg_replace('/^\./m', '..', $s) ?? $s;
            return str_replace("\n", "\r\n", $s);
        };
        $headers = 'Subject: ' . $safeSubject . "\r\n"
            . 'From: ' . $fromHeader . "\r\n"
            . 'To: ' . $to . "\r\n"
            . 'Reply-To: ' . $mailFrom . "\r\n"
            . "MIME-Version: 1.0\r\n";
        if (!$attachments) {
            return $dot(
                $headers
                . "Content-Type: text/plain; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: 8bit\r\n\r\n"
                . $body
            ) . "\r\n.";
        }
        $bound = '----=_InPmnt_' . bin2hex(random_bytes(8));
        $parts = $headers
            . 'Content-Type: multipart/mixed; boundary="' . $bound . "\"\r\n\r\n"
            . '--' . $bound . "\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . $body . "\r\n";
        foreach ($attachments as $att) {
            $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) ($att['filename'] ?? 'invoice.pdf')) ?: 'invoice.pdf';
            $mime = (string) ($att['mime'] ?? 'application/pdf');
            $b64 = chunk_split(base64_encode((string) ($att['content'] ?? '')), 76, "\r\n");
            $parts .= '--' . $bound . "\r\n"
                . 'Content-Type: ' . $mime . '; name="' . $name . "\"\r\n"
                . "Content-Transfer-Encoding: base64\r\n"
                . 'Content-Disposition: attachment; filename="' . $name . "\"\r\n\r\n"
                . $b64;
        }
        $parts .= '--' . $bound . "--\r\n";
        return $dot($parts) . '.';
    }

    /** @param callable(string):string $cmd */
    private static function smtpAuth(callable $cmd, callable $expect, string $user, string $password): void
    {
        $login = $cmd('AUTH LOGIN');
        if (str_starts_with(ltrim($login), '334')) {
            $cmd(base64_encode($user));
            $resp = $cmd(base64_encode($password));
            if (str_starts_with(ltrim($resp), '235')) {
                return;
            }
            $plain = $cmd('AUTH PLAIN ' . base64_encode("\0{$user}\0{$password}"));
            if (str_starts_with(ltrim($plain), '235')) {
                return;
            }
            throw new RuntimeException('SMTP login failed: ' . trim(str_replace(["\r", "\n"], ' ', $resp)));
        }
        $plain = $cmd('AUTH PLAIN ' . base64_encode("\0{$user}\0{$password}"));
        $expect($plain, '235', 'AUTH PLAIN');
    }

    /** @param-out string $err @return resource|false */
    private static function smtpConnect(string $host, int $port, bool $ssl, bool $verify, string &$err)
    {
        $remote = ($ssl ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $ctx = stream_context_create([
            'ssl' => [
                'verify_peer' => $verify,
                'verify_peer_name' => $verify,
                'allow_self_signed' => !$verify,
                'SNI_enabled' => true,
                'peer_name' => $host,
            ],
        ]);
        $errno = 0;
        $errstr = '';
        $fp = @stream_socket_client(
            $remote,
            $errno,
            $errstr,
            30,
            STREAM_CLIENT_CONNECT,
            $ctx
        );
        if ($fp === false) {
            $err = trim($errstr . ($errno ? " ({$errno})" : ''));
        }
        return $fp;
    }
}
