<?php
/** @var string $title */
/** @var string $site_home */
/** @var string $core_rule */
/** @var array $flashes */
/** @var array $invite */
/** @var string $token */
View::start($title, $site_home, 'noindex,nofollow', '/join/' . ($token ?? ''));
?>
<div class="auth-page">
  <div class="auth-card">
    <?= View::brand($site_home, 'OurCircle', 'Join a circle') ?>
    <h1>Join this family circle</h1>
    <p class="core-rule"><?= Http::e($core_rule) ?></p>
    <?= View::flashesHtml($flashes) ?>
    <p>Invite for <?= Http::mailto((string) ($invite['email'] ?? '')) ?></p>
    <form method="post" action="/join/<?= Http::e((string) ($token ?? '')) ?>">
      <label>Your name</label>
      <input name="name" required value="<?= Http::e((string) ($invite['name'] ?? '')) ?>" />
      <label>Mobile (optional)</label>
      <input name="phone" type="tel" inputmode="tel" value="<?= Http::e((string) ($invite['phone'] ?? '')) ?>" placeholder="(555) 010-1234" autocomplete="tel" />
      <label>Choose a password (8+)</label>
      <input name="password" type="password" required minlength="8" />
      <p><button class="btn wide" type="submit">Join the circle</button></p>
    </form>
    <p class="disclaimer"><?= Http::e($guidance ?? Analyze::GUIDANCE) ?></p>
  </div>
</div>
<?php View::end(); ?>
