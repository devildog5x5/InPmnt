<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Sign up · InPmnt</title>
  <link rel="icon" type="image/png" href="/static/img/inpmnt-icon.png" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=Source+Serif+4:opsz,wght@8..60,500;8..60,600;8..60,700&display=swap" rel="stylesheet" />
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
      <h1>Start your free trial</h1>
      <p class="lead">14 days to recover late invoices. No card required to start.</p>
      <?php if (!empty($error)): ?>
      <div class="auth-error"><?= Http::e($error) ?></div>
      <?php endif; ?>
      <form method="post">
        <div class="field">
          <label for="name">Your name</label>
          <input id="name" name="name" type="text" required autocomplete="name" />
        </div>
        <div class="field">
          <label for="business_name">Business name</label>
          <input id="business_name" name="business_name" type="text" autocomplete="organization" />
        </div>
        <div class="field">
          <label for="email">Email</label>
          <input id="email" name="email" type="email" required autocomplete="username" />
        </div>
        <div class="field">
          <label for="password">Password</label>
          <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password" />
        </div>
        <button class="btn" type="submit">Create account</button>
      </form>
      <p class="auth-foot">
        Already have an account? <a href="<?= Http::e(Http::url('/login')) ?>">Log in</a><br />
        <a href="<?= Http::e(Http::url('/')) ?>">← Back to home</a>
      </p>
    </div>
  </div>
</body>
</html>
