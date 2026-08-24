<?php
declare(strict_types=1);

header('X-InPmnt: php');
header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

register_shutdown_function(static function (): void {
    $err = error_get_last();
    if ($err === null) {
        return;
    }
    $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array($err['type'], $fatal, true)) {
        return;
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        header('X-InPmnt: php');
    }
    echo "InPmnt PHP fatal error\n\n{$err['message']}\nin {$err['file']}:{$err['line']}\n";
});

try {
    $db = require __DIR__ . '/bootstrap.php';

    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    if (str_starts_with($path, '/static/')) {
        $rel = str_replace('..', '', substr($path, 8));
        foreach ([__DIR__ . '/static/' . $rel, dirname(__DIR__) . '/static/' . $rel] as $file) {
            if (is_file($file)) {
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                $types = [
                    'css' => 'text/css; charset=utf-8',
                    'js' => 'application/javascript; charset=utf-8',
                    'png' => 'image/png',
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'svg' => 'image/svg+xml',
                    'ico' => 'image/x-icon',
                    'webp' => 'image/webp',
                ];
                header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
                header('Cache-Control: public, max-age=86400');
                readfile($file);
                exit;
            }
        }
    }

    (new App($db))->run();
} catch (Throwable $e) {
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        header('X-InPmnt: php');
    }
    echo "InPmnt PHP error\n\n{$e->getMessage()}\nin {$e->getFile()}:{$e->getLine()}\n";
    exit;
}
