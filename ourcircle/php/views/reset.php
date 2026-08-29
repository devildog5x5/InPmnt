<?php
/** @var string $title */
/** @var string $site_home */
/** @var array $flashes */
/** @var string $token */
View::start($title, $site_home, 'noindex,nofollow', '/reset/' . ($token ?? ''));
?>
<div class="auth-page">
  <div class="auth-card">
    <?= View::brand($site_home, 'OurCircle', 'New password') ?>
    <?= View::flashesHtml($flashes) ?>
    <form method="post">
      <label>New password (8+)</label>
      <input name="password" type="password" required minlength="8" autocomplete="new-password" />
      <p><button class="btn wide" type="submit">Save password</button></p>
    </form>
  </div>
</div>
</body></html>
