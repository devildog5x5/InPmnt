<?php
/** @var string $title */
/** @var string $site_home */
/** @var array $flashes */
View::start($title, $site_home, 'index,follow', '/forgot');
?>
<div class="auth-page">
  <div class="auth-card">
    <?= View::brand($site_home, 'OurCircle', 'Password recovery') ?>
    <?= View::flashesHtml($flashes) ?>
    <h1>Reset with email</h1>
    <p class="muted">We’ll send a one-hour link if this email is on a circle. We never say whether the email exists.</p>
    <form method="post">
      <label>Email</label>
      <input name="email" type="email" required autocomplete="username" />
      <p><button class="btn wide" type="submit">Send reset link</button></p>
    </form>
    <h2>Or use a recovery code</h2>
    <p class="muted">If you turned on 2FA, you were shown one-time codes. That also works if email isn’t set up on this site yet.</p>
    <form method="post" action="/forgot/code">
      <label>Email</label>
      <input name="email" type="email" required autocomplete="username" />
      <label>Recovery code</label>
      <input name="recovery_code" required placeholder="xxxx-xxxx" autocomplete="off" />
      <label>New password (8+)</label>
      <input name="password" type="password" required minlength="8" autocomplete="new-password" />
      <p><button class="btn ghost wide" type="submit">Reset with recovery code</button></p>
    </form>
    <p><a href="/login">Back to sign in</a></p>
  </div>
</div>
<?php View::end(); ?>
