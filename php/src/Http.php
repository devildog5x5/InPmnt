<?php
declare(strict_types=1);

final class Http
{
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
        header('Cache-Control: no-store, no-cache, must-revalidate');
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            $url = self::url($url);
        }
        header('Location: ' . $url);
        exit;
    }

    /** Front controller path so /login works even when mod_rewrite is off. */
    public static function front(): string
    {
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
        if ($script === '' || $script === '/') {
            $script = '/index.php';
        }
        if (!str_ends_with(strtolower($script), 'index.php')) {
            $script = rtrim($script, '/') . '/index.php';
        }
        return $script;
    }

    /** App URL that works without Apache rewrite: /index.php/login, /index.php/app#/settings. */
    public static function url(string $path): string
    {
        $hash = '';
        $query = '';
        if (str_contains($path, '#')) {
            [$path, $frag] = explode('#', $path, 2);
            $hash = '#' . $frag;
        }
        if (str_contains($path, '?')) {
            [$path, $q] = explode('?', $path, 2);
            $query = '?' . $q;
        }
        $path = '/' . ltrim($path, '/');
        $front = self::front();
        $frontLen = strlen($front);
        if (strncasecmp($path, $front, $frontLen) === 0) {
            return $path . $query . $hash;
        }
        if ($path === '/') {
            return $front . $query . $hash;
        }
        return $front . $path . $query . $hash;
    }

    /** Host the visitor is actually on, so a sandbox subdomain is not bounced to a broken main domain. */
    public static function publicOrigin(): string
    {
        $host = preg_replace('/[^A-Za-z0-9.:\[\]-]/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')) ?? '';
        $fwd = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        $https = $fwd === 'https'
            || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
        if ($host !== '') {
            return ($https ? 'https://' : 'http://') . $host;
        }
        return rtrim(Env::get('BASE_URL', 'http://127.0.0.1:5055'), '/');
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
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public static function path(): string
    {
        $info = $_SERVER['PATH_INFO'] ?? '';
        if (is_string($info) && $info !== '' && $info !== '/') {
            $path = $info;
        } else {
            $uri = (string) ($_SERVER['REDIRECT_URL'] ?? $_SERVER['REQUEST_URI'] ?? '/');
            $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        }
        $path = '/' . ltrim($path, '/');
        $front = self::front();
        if (strcasecmp($path, $front) === 0 || strcasecmp($path, '/index.php') === 0) {
            $path = '/';
        } elseif (str_starts_with(strtolower($path), strtolower($front) . '/')) {
            $path = substr($path, strlen($front));
        } elseif (str_starts_with(strtolower($path), '/index.php/')) {
            $path = substr($path, strlen('/index.php'));
        }
        if (strlen($path) > 1) {
            $path = rtrim($path, '/');
        }
        return $path === '' ? '/' : $path;
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
}
