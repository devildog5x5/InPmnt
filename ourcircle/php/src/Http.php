<?php
declare(strict_types=1);

final class Http
{
    public static function redirect(string $url, int $status = 302): never
    {
        http_response_code($status);
        header('Location: ' . $url);
        exit;
    }

    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public static function path(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = '/' . ltrim($path, '/');
        if (strlen($path) > 1) {
            $path = rtrim($path, '/');
        }
        return $path;
    }

    public static function e(?string $s): string
    {
        return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Escape text, then turn http(s) URLs and email addresses into tap-to-open links. */
    public static function linkify(?string $raw): string
    {
        $escaped = self::e($raw);
        $escaped = preg_replace('#(https?://[^\s<]+)#', '<a href="$1">$1</a>', $escaped) ?? $escaped;
        $escaped = preg_replace(
            '#(?<!mailto:)([A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,})#',
            '<a href="mailto:$1">$1</a>',
            $escaped
        ) ?? $escaped;
        return $escaped;
    }

    public static function mailto(?string $email): string
    {
        $email = trim((string) $email);
        if ($email === '') {
            return '';
        }
        return '<a href="mailto:' . self::e($email) . '">' . self::e($email) . '</a>';
    }

    public static function tel(?string $phone): string
    {
        $raw = trim((string) $phone);
        if ($raw === '') {
            return '—';
        }
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        $href = '';
        if (str_starts_with($raw, '+') && strlen($digits) >= 10) {
            $href = '+' . $digits;
        } elseif (strlen($digits) === 10) {
            $href = '+1' . $digits;
        } elseif (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            $href = '+' . $digits;
        }
        if ($href === '') {
            return self::e($raw);
        }
        return '<a href="tel:' . self::e($href) . '">' . self::e($raw) . '</a>';
    }

    public static function website(?string $raw): string
    {
        $text = trim((string) $raw);
        if ($text === '') {
            return '';
        }
        $href = $text;
        if (!preg_match('#^https?://#i', $href)) {
            $href = 'https://' . ltrim($href, '/');
        }
        return '<a href="' . self::e($href) . '" rel="noopener">' . self::e($text) . '</a>';
    }

    public static function safeNext(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $next = trim($raw);
        if (str_starts_with($next, '/') && !str_starts_with($next, '//')) {
            return $next;
        }
        return null;
    }

    public static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function xml(string $body, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: text/xml; charset=utf-8');
        echo $body;
        exit;
    }

    public static function isHttps(): bool
    {
        $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
        if ($https !== '' && $https !== 'off') {
            return true;
        }
        if ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443') {
            return true;
        }
        $fwd = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        return $fwd === 'https' || str_starts_with($fwd, 'https,');
    }

    public static function isLocalHost(?string $host = null): bool
    {
        $h = strtolower((string) ($host ?? ($_SERVER['HTTP_HOST'] ?? '')));
        $h = explode(':', $h)[0];
        return in_array($h, ['127.0.0.1', 'localhost', '::1', '[::1]'], true);
    }

    /** Secure cookies on the live HTTPS site; plain HTTP on localhost so login sticks. */
    public static function sessionCookieSecure(): bool
    {
        if (self::isHttps()) {
            return true;
        }
        return !self::isLocalHost();
    }
}
