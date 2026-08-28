<?php
declare(strict_types=1);

// WordPress auto-updates overwrite index.php. The real app is app.php;
// .htaccess also rewrites /index.php → /app.php.
require __DIR__ . '/app.php';
