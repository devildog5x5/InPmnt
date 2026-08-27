<?php
declare(strict_types=1);

final class Env
{
    public static function load(string $path): void
    {
        if (!is_file($path)) {
            return;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return;
        }
        foreach ($lines as $i => $line) {
            if ($i === 0) {
                $line = preg_replace('/^\xEF\xBB\xBF/', '', $line) ?? $line;
            }
            $trim = trim($line);
            if ($trim === '' || str_starts_with($trim, '#') || !str_contains($trim, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $trim, 2);
            $k = trim($k);
            $v = self::unquote(trim($v));
            if ($k === '') {
                continue;
            }
            $current = getenv($k);
            if ($current === false || trim((string) $current) === '') {
                putenv("{$k}={$v}");
                $_ENV[$k] = $v;
            }
        }
    }

    public static function get(string $key, string $default = ''): string
    {
        $v = getenv($key);
        if ($v === false || $v === '') {
            $v = $_ENV[$key] ?? $default;
        }
        return is_string($v) ? self::unquote($v) : $default;
    }

    private static function unquote(string $v): string
    {
        $v = trim($v);
        if (strlen($v) >= 2) {
            $q = $v[0];
            if (($q === '"' || $q === "'") && str_ends_with($v, $q)) {
                return substr($v, 1, -1);
            }
        }
        return $v;
    }

    public static function truthy(string $key): bool
    {
        return in_array(strtolower(self::get($key)), ['1', 'true', 'yes', 'on'], true);
    }
}
