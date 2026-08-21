<?php
declare(strict_types=1);

header('X-InPmnt: php');
header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

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
