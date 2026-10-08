<!DOCTYPE html>
<html lang="en" data-theme="<?= Http::e(Http::theme()) ?>" style="color-scheme: <?= Http::theme() === 'dark' ? 'dark' : 'light' ?>">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="<?= Http::e(Http::csrfToken()) ?>" />
  <?php
    $meta_title = 'Start a free 14-day InvoicePay trial, no card';
    $meta_description = 'Open an InvoicePay trial in a minute. No credit card. 14 days of invoice reminders, then $4.99 a month or $49.99 a year.';
    require __DIR__ . '/_meta.php';
  ?>
  <link rel="icon" type="image/png" href="/static/img/inpmnt-icon.png" />
  <link rel="stylesheet" href="/static/css/app.css?v=<?= rawurlencode(Http::VERSION) ?>" />
</head>
<body class="landing">
<?php require __DIR__ . '/_nav.php'; ?>
  <div class="signup-layout">
    <section class="signup-offer">
      <h1>Start the 14-day trial</h1>
      <p>No credit card. You get the InvoicePay workspace: unpaid invoices, a reminder schedule, and a place to record the payment.</p>
      <?php if (!empty($plan)): ?>
      <p class="pay-setup" role="status">After this account is created you go straight to checkout for <?= Http::e((string) $plan_label) ?>.</p>
      <?php endif; ?>
      <ul>
        <li>$4.99 a month after the trial, or $49.99 for the year</li>
        <li>Email reminders during the trial</li>
        <li>SMS and unlimited open invoices once you subscribe</li>
      </ul>
      <p>You can look at <a href="/pricing">pricing</a> or the <a href="/faq">FAQ</a> first. Questions: <a href="mailto:support@invcpay.com">support@invcpay.com</a>.</p>
    </section>
    <div class="auth-card">
      <h2>Create your account</h2>
      <p class="lead">Name, email, and a password. That is the whole form.</p>
      <?php if (!empty($error)): ?>
      <div class="auth-error"><?= Http::e($error) ?></div>
      <?php endif; ?>
      <form method="post">
        <input type="hidden" name="plan" value="<?= Http::e((string) ($plan ?? '')) ?>" />
        <div class="field">
          <label for="name">Your name</label>
          <input id="name" name="name" type="text" required autocomplete="name" />
        </div>
        <div class="field">
          <label for="business_name">Business name (optional)</label>
          <input id="business_name" name="business_name" type="text" autocomplete="organization" />
        </div>
        <div class="field">
          <label for="email">Email</label>
          <input id="email" name="email" type="email" required autocomplete="username" />
        </div>
        <div class="field">
          <label for="password">Password</label>
          <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password" aria-describedby="password-hint" />
          <p class="field-hint" id="password-hint">At least 8 characters. Use Show if you want to check it.</p>
        </div>
        <button class="btn" type="submit">Start free trial</button>
      </form>
      <p class="auth-foot">
        Already have an account? <a href="/login">Log in</a>
      </p>
    </div>
  </div>
<?php require __DIR__ . '/_version.php'; ?>
<?php require __DIR__ . '/_help_chat.php'; ?>
</body>
</html>
