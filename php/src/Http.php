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

    public static function pdf(string $bytes, string $filename): never
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', $filename) ?: 'invoice.pdf';
        http_response_code(200);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $safe . '"');
        header('Content-Length: ' . (string) strlen($bytes));
        echo $bytes;
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

    /** Cache-busted public asset URL so Hostinger/LiteSpeed does not keep an old app.js. */
    public static function assetUrl(string $rel): string
    {
        $rel = ltrim($rel, '/');
        if (!str_starts_with($rel, 'static/')) {
            $rel = 'static/' . $rel;
        }
        $phpDir = dirname(__DIR__);
        $mtime = time();
        foreach ([$phpDir . '/' . $rel, dirname($phpDir) . '/' . $rel] as $file) {
            if (is_file($file)) {
                $mtime = (int) filemtime($file);
                break;
            }
        }
        return '/' . $rel . '?v=' . $mtime;
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
