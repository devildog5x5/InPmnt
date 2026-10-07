<?php
declare(strict_types=1);

final class Http
{
    public const VERSION = '1.6.1';

    public static function theme(): string
    {
        $t = strtolower(trim((string) ($_SESSION['ui_theme'] ?? 'light')));
        return ($t === 'dark' || $t === 'light') ? $t : 'light';
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(16));
        }
        return (string) $_SESSION['csrf'];
    }

    public static function verifyCsrfHeader(): bool
    {
        $token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $need = self::csrfToken();
        return $token !== '' && strlen($token) === strlen($need) && hash_equals($need, $token);
    }

    public static function setTheme(string $name): string
    {
        $t = strtolower(trim($name));
        if ($t !== 'dark' && $t !== 'light') {
            $t = 'light';
        }
        $_SESSION['ui_theme'] = $t;
        return $t;
    }

    public static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function redirect(string $url, int $status = 302): never
    {
        http_response_code($status);
        header('Location: ' . $url);
        exit;
    }

    public static function bodyJson(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        if ($raw === '') {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    public static function method(): string
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        // Shared hosts often pass HEAD through. Treat it as a body-less GET
        // so public pages, robots.txt, and the sitemap do not 404.
        return $method === 'HEAD' ? 'GET' : $method;
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

    public static function publicBase(): string
    {
        return self::canonicalBase();
    }

    /**
     * One origin for canonical tags, the sitemap, and redirects.
     * invcpay.com and www.invcpay.com always collapse to https://invcpay.com.
     */
    public static function canonicalBase(): string
    {
        $host = self::hostOnly();
        if ($host === 'invcpay.com' || $host === 'www.invcpay.com') {
            return 'https://invcpay.com';
        }
        if ($host === '127.0.0.1' || $host === 'localhost') {
            return self::requestOrigin();
        }
        $configured = rtrim(Env::get('BASE_URL'), '/');
        if ($configured !== '') {
            return $configured;
        }
        return self::requestOrigin();
    }

    public static function hostOnly(): string
    {
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        return (string) preg_replace('/:\d+$/', '', $host);
    }

    public static function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }
        if ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443') {
            return true;
        }
        $fwd = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        return $fwd === 'https';
    }

    public static function requestOrigin(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
        return (self::isHttps() ? 'https' : 'http') . '://' . $host;
    }

    public static function rawPath(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = '/';
        }
        return '/' . ltrim($path, '/');
    }

    /**
     * 301 http/www/trailing-slash/index.php variants onto one URL so Search
     * Console is not left with duplicates and no chosen canonical.
     */
    public static function enforceCanonicalUrl(): void
    {
        $verb = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($verb !== 'GET' && $verb !== 'HEAD') {
            return;
        }
        $raw = self::rawPath();
        $path = $raw;
        if (preg_match('#/index\.php$#', $path)) {
            $path = substr($path, 0, -strlen('index.php'));
            $path = rtrim($path, '/');
            if ($path === '') {
                $path = '/';
            }
        }
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/') ?: '/';
        }
        $query = (string) ($_SERVER['QUERY_STRING'] ?? '');
        $qs = $query !== '' ? '?' . $query : '';
        $host = self::hostOnly();
        $local = $host === '127.0.0.1' || $host === 'localhost';
        if ($local) {
            if ($path !== $raw) {
                self::redirect($path . $qs, 301);
            }
            return;
        }
        $targetHost = strtolower((string) (parse_url(self::canonicalBase(), PHP_URL_HOST) ?: ''));
        $hostMismatch = $targetHost !== '' && $host !== $targetHost;
        $fwd = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        // Only upgrade scheme when a proxy says the visitor used http.
        // A bare HTTPS=off behind Hostinger's CDN would otherwise redirect forever.
        $schemeMismatch = $fwd === 'http';
        if ($hostMismatch || $schemeMismatch || $path !== $raw) {
            $target = self::canonicalBase() . ($path === '/' ? '/' : $path) . $qs;
            self::redirect($target, 301);
        }
    }
}
