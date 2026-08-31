<?php
/** @var string $title */
/** @var string $site_home */
/** @var array $flashes */
/** @var string $token */
View::start($title, $site_home, 'noindex,nofollow', '/join/' . ($token ?? ''));
?>
<div class="auth-page">
  <div class="auth-card">
    <?= View::brand($site_home, 'OurCircle', 'Join a circle') ?>
    <h1>This invite is not available</h1>
    <p class="core-rule"><?= Http::e($core_rule) ?></p>
    <?= View::flashesHtml($flashes) ?>
    <p>That join link is expired or already used. Ask someone in the circle to send a new invite.</p>
    <p><a class="btn wide" href="<?= Http::e((string) $site_home) ?>">Open Family Shield Pro</a></p>
    <p class="disclaimer"><?= Http::e($guidance ?? Analyze::GUIDANCE) ?></p>
  </div>
</div>
<?php View::end(); ?>
