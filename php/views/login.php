<!DOCTYPE html>
<html lang="en" data-theme="<?= Http::e(Http::theme()) ?>" style="color-scheme: <?= Http::theme() === 'dark' ? 'dark' : 'light' ?>">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="<?= Http::e(Http::csrfToken()) ?>" />
  <?php
    $meta_title = 'Log in to InvoicePay to manage invoice reminders';
    $meta_description = 'Sign in to InvoicePay to track unpaid invoices and send payment reminders to clients.';
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
          <img src="/static/img/inpmnt-icon.png" width="42" height="42" alt="InvoicePay logo" />
        </div>
        <div>
          <div class="brand-mark" style="color:var(--ink);font-size:1.35rem">InvoicePay</div>
          <div class="brand-sub" style="color:var(--muted)">Get paid</div>
        </div>
      </div>
      <h1>Welcome back</h1>
      <p class="lead">Sign in to your invoice chase workspace.</p>
      <?php if (!empty($error)): ?>
      <div class="auth-error"><?= Http::e($error) ?></div>
      <?php endif; ?>
      <form method="post">
        <?php if (!empty($next)): ?>
        <input type="hidden" name="next" value="<?= Http::e($next) ?>" />
        <?php endif; ?>
        <div class="field">
          <label for="email">Email</label>
          <input id="email" name="email" type="email" required autocomplete="username" />
        </div>
        <div class="field">
          <label for="password">Password</label>
          <input id="password" name="password" type="password" required value="<?= !empty($show_demo_login) ? 'Demo' : '' ?>" autocomplete="current-password" />
        </div>
        <button class="btn" type="submit">Sign in</button>
      </form>
      <p class="auth-foot">
        <a href="/forgot-password">Forgot password?</a><br />
        New here? <a href="/signup">Start free trial</a><br />
        <a href="/">← Back to home</a>
      </p>
    </div>
  </div>
<?php require __DIR__ . '/_version.php'; ?>
<?php require __DIR__ . '/_help_chat.php'; ?>
</body>
</html>
