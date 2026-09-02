<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Forgot password · InPmnt</title>
  <link rel="icon" type="image/png" href="/static/img/inpmnt-icon.png" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=Source+Serif+4:opsz,wght@8..60,500;8..60,600;8..60,700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/static/css/app.css" />
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
</body>
</html>
