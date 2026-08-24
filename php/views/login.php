<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Log in · InPmnt</title>
  <link rel="icon" type="image/png" href="/static/img/inpmnt-icon.png" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600;700&family=Source+Serif+4:opsz,wght@8..60,500;8..60,600;8..60,700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/static/css/app.css" />
  <?php require __DIR__ . '/_client_boot.php'; ?>
</head>
<body>
  <div class="auth-page">
    <div class="auth-card">
      <div class="auth-brand">
        <div class="brand-logo img" aria-hidden="true">
          <img src="/static/img/inpmnt-icon.png" alt="" />
        </div>
        <div>
          <div class="brand-mark" style="color:var(--ink);font-size:1.35rem">InPmnt</div>
          <div class="brand-sub" style="color:var(--muted)">Get paid without the chase</div>
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
          <input id="email" name="email" type="email" required value="<?= !empty($show_demo_login) ? 'demouser@inpmnt.app' : '' ?>" autocomplete="username" />
        </div>
        <div class="field">
          <label for="password">Password</label>
          <input id="password" name="password" type="password" required value="<?= !empty($show_demo_login) ? 'Demo' : '' ?>" autocomplete="current-password" />
        </div>
        <button class="btn" type="submit">Sign in</button>
      </form>
      <p class="auth-foot">
        New here? <a href="<?= Http::e(Http::url('/signup')) ?>">Start free trial</a><br />
        <a href="<?= Http::e(Http::url('/')) ?>">← Back to home</a>
        <?php if (!empty($show_demo_login)): ?><br />Demo: demouser@inpmnt.app / Demo<?php endif; ?>
      </p>
    </div>
  </div>
</body>
</html>
