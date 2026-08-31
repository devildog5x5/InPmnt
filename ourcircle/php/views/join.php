<?php
/** @var string $title */
/** @var string $site_home */
/** @var string $core_rule */
/** @var array $flashes */
/** @var array $invite */
/** @var string $token */
View::start($title, $site_home, 'noindex,nofollow', '/join/' . ($token ?? ''));
$inviteName = (string) ($invite['name'] ?? '');
$invitePhone = (string) ($invite['phone'] ?? '');
?>
<div class="auth-page">
  <div class="auth-card">
    <?= View::brand($site_home, 'OurCircle', 'Join a circle') ?>
    <h1>Join this family circle</h1>
    <p class="core-rule"><?= Http::e($core_rule) ?></p>
    <?= View::flashesHtml($flashes) ?>
    <p>Invite for <?= Http::mailto((string) ($invite['email'] ?? '')) ?></p>
    <form method="post" action="/join/<?= Http::e((string) ($token ?? '')) ?>">
      <?php if ($inviteName !== ''): ?>
        <input type="hidden" name="name" value="<?= Http::e($inviteName) ?>" />
        <p>Joining as <strong><?= Http::e($inviteName) ?></strong></p>
      <?php else: ?>
        <label>Your name</label>
        <input name="name" required />
      <?php endif; ?>
      <label>Choose a password (8+)</label>
      <input name="password" type="password" required minlength="8" />
      <?php if ($invitePhone !== ''): ?>
        <input type="hidden" name="phone" value="<?= Http::e($invitePhone) ?>" />
      <?php else: ?>
        <details class="more">
          <summary>Mobile (optional — for call-me texts)</summary>
          <input name="phone" type="tel" inputmode="tel" placeholder="(555) 010-1234" autocomplete="tel" />
        </details>
      <?php endif; ?>
      <p><button class="btn wide" type="submit">Join the circle</button></p>
    </form>
    <p class="disclaimer"><?= Http::e($guidance ?? Analyze::GUIDANCE) ?></p>
  </div>
</div>
<?php View::end(); ?>
