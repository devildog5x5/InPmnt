<?php
/** @var array $flashes */
View::start($title ?? 'Operator console · Family Shield Pro', $site_home, 'noindex,nofollow', '/admin/login');
?>
<div class="auth-page">
  <div class="auth-card">
    <?= View::brand($site_home, 'OurCircle', 'Operator console') ?>
    <?= View::flashesHtml($flashes ?? []) ?>
    <p class="muted">This is not a family login. Families use Sign in. The console is off until ADMIN_PASSWORD is set in .env.</p>
    <form method="post">
      <label>Operator password</label>
      <input name="password" type="password" required autocomplete="current-password" />
      <p><button class="btn wide" type="submit">Open console</button></p>
    </form>
    <p class="disclaimer"><?= Http::e($guidance ?? Analyze::GUIDANCE) ?></p>
    <p><a href="/login">Family sign in</a></p>
  </div>
</div>
<?php View::end(); ?>
