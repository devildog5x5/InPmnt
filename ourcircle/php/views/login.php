<?php
/** @var string $title */
/** @var string $site_home */
/** @var array $flashes */
/** @var string $next */
View::start($title, $site_home, 'index,follow', '/login');
?>
<div class="auth-page">
  <div class="auth-card">
    <?= View::brand($site_home, 'OurCircle', 'Sign in') ?>
    <?= View::flashesHtml($flashes) ?>
    <form method="post">
      <input type="hidden" name="next" value="<?= Http::e($next ?? '') ?>" />
      <label>Email</label>
      <input name="email" type="email" required autocomplete="username" />
      <label>Password</label>
      <input name="password" type="password" required autocomplete="current-password" />
      <p><button class="btn wide" type="submit">Sign in</button></p>
    </form>
    <p class="muted">Demo circle: family@ourcircle.app / password123</p>
    <p><a href="/forgot">Forgot password</a></p>
    <p><a href="/signup">Start a circle</a></p>
  </div>
</div>
</body></html>
