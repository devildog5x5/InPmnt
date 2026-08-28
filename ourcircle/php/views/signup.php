<?php
/** @var string $title */
/** @var string $site_home */
/** @var string $core_rule */
/** @var array $flashes */
View::start($title, $site_home, 'index,follow', '/signup');
?>
<div class="auth-page">
  <div class="auth-card">
    <?= View::brand($site_home, 'OurCircle', 'Founding family') ?>
    <p class="core-rule"><?= Http::e($core_rule) ?></p>
    <?= View::flashesHtml($flashes) ?>
    <form method="post">
      <label>Your name</label>
      <input name="name" required />
      <label>Circle name</label>
      <input name="household" placeholder="The Patel circle" />
      <label>Email</label>
      <input name="email" type="email" required />
      <label>Password (8+ characters)</label>
      <input name="password" type="password" required minlength="8" />
      <p><button class="btn wide" type="submit">Create circle · $49 founding year</button></p>
    </form>
    <p class="disclaimer">No card is charged in this build. You are reserving the founding price.</p>
    <p><a href="/login">Already have a login</a></p>
  </div>
</div>
</body></html>
