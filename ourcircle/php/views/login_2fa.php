<?php
/** @var string $title */
/** @var string $site_home */
/** @var string $core_rule */
/** @var array $flashes */
View::start($title, $site_home, 'noindex,nofollow', '/login/2fa');
?>
<div class="auth-page">
  <div class="auth-card">
    <?= View::brand($site_home, 'OurCircle', 'Two-factor') ?>
    <p class="core-rule"><?= Http::e($core_rule) ?></p>
    <?= View::flashesHtml($flashes) ?>
    <p>Enter the 6-digit code from your authenticator app, or a one-time recovery code.</p>
    <form method="post">
      <label>Authenticator code</label>
      <input class="otp" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="8" />
      <p class="muted">Or</p>
      <label>Recovery code</label>
      <input name="recovery_code" placeholder="xxxx-xxxx" autocomplete="off" />
      <p><button class="btn wide" type="submit">Continue</button></p>
    </form>
    <p><a href="/logout">Cancel</a></p>
  </div>
</div>
<?php View::end(); ?>
