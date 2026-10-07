<!DOCTYPE html>
<html lang="en" data-theme="<?= Http::e(Http::theme()) ?>" style="color-scheme: <?= Http::theme() === 'dark' ? 'dark' : 'light' ?>">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="<?= Http::e(Http::csrfToken()) ?>" />
  <?php
    $meta_title = 'Reset a forgotten password on your ReceiptGrid account';
    $meta_description = 'Email yourself a link to reset the password on your ReceiptGrid account, then return to your invoices.';
    $meta_robots = 'noindex, nofollow';
    require __DIR__ . '/_meta.php';
  ?>
  <link rel="icon" type="image/png" href="/static/img/inpmnt-icon.png" />
  <link rel="stylesheet" href="/static/css/app.css?v=<?= rawurlencode(Http::VERSION) ?>" />
</head>
<body class="landing">
<?php require __DIR__ . '/_nav.php'; ?>
  <div class="auth-page">
    <div class="auth-card">
      <div class="auth-brand">
        <div class="brand-logo img">
          <img src="/static/img/inpmnt-icon.png" width="42" height="42" alt="ReceiptGrid logo" />
        </div>
        <div>
          <div class="brand-mark" style="color:var(--ink);font-size:1.35rem">ReceiptGrid</div>
          <div class="brand-sub" style="color:var(--muted)">Invoicing</div>
        </div>
      </div>
      <h1>Reset your password</h1>
      <p class="lead">Enter the email for your admin or workspace account. We’ll send a reset link, or save one next to the database if email isn’t set up.</p>
      <?php if (!empty($notice)): ?>
      <div class="auth-ok"><?= Http::e($notice) ?></div>
      <?php endif; ?>
      <?php if (empty($notice)): ?>
      <form method="post">
        <div class="field">
          <label for="email">Email</label>
          <input id="email" name="email" type="email" required autocomplete="username" />
        </div>
        <button class="btn" type="submit">Send reset link</button>
      </form>
      <?php endif; ?>
      <p class="auth-foot">
        <a href="/login">← Back to log in</a>
      </p>
    </div>
  </div>
<?php require __DIR__ . '/_version.php'; ?>
<?php require __DIR__ . '/_help_chat.php'; ?>
</body>
</html>
