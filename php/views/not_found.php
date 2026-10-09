<?php
/** Copyright (c) 2026 REKKY Consulting LLC */
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="<?= Http::e(Http::csrfToken()) ?>" />
  <?php
    $meta_title = 'Page not found · InvoicePay';
    $meta_description = 'That InvoicePay page is not here. Open pricing, help, or the workspace from the menu.';
    $meta_robots = 'noindex, nofollow';
    require __DIR__ . '/_meta.php';
  ?>
  <link rel="icon" type="image/png" href="/static/img/inpmnt-icon.png" />
  <link rel="stylesheet" href="/static/css/app.css?v=<?= rawurlencode(Http::VERSION) ?>" />
</head>
<body class="landing">
<?php require __DIR__ . '/_nav.php'; ?>
  <article class="legal-page">
    <h1>Page not found</h1>
    <p>That address is not on InvoicePay. Use the menu to open pricing, reminders, or your workspace.</p>
    <p><?php require __DIR__ . '/_support_note.php'; ?>.</p>
    <p><a href="/">Back to home</a></p>
  </article>
<?php require __DIR__ . '/_version.php'; ?>
<?php require __DIR__ . '/_help_chat.php'; ?>
</body>
</html>
