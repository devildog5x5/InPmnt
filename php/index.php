<?php
declare(strict_types=1);

/**
 * InPmnt v1.5.03
 *
 * Hostinger entry point. The version string above stays in lockstep with
 * Http::VERSION and the root VERSION file so a text search of index.php finds it.
 */

require_once __DIR__ . '/src/Env.php';
require_once __DIR__ . '/src/Http.php';
Env::load(__DIR__ . '/.env');
if (Http::maybeSendSeo()) {
    exit;
}

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
