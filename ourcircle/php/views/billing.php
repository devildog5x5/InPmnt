<?php
/** @var array $household */
/** @var array $plans */
View::appOpen(get_defined_vars());
?>
<p>This household is on <strong><?= Http::e((string) ($household['plan'] ?? 'yearly')) ?></strong>.</p>
<div class="plans">
  <?php foreach ($plans as $p): ?>
  <div class="panel<?= !empty($p['featured']) ? ' featured' : '' ?>">
    <?php if (!empty($p['featured'])): ?><span class="plan-badge">Best for families</span><?php endif; ?>
    <h3><?= Http::e($p['name']) ?></h3>
    <p><strong><?= Http::e($p['price']) ?></strong></p>
    <p><?= Http::e($p['detail']) ?></p>
    <form method="post" action="/billing/choose">
      <input type="hidden" name="plan" value="<?= Http::e($p['id']) ?>" />
      <p><button class="btn<?= !empty($p['featured']) ? ' gold' : '' ?> wide" type="submit">Choose <?= Http::e($p['name']) ?></button></p>
    </form>
  </div>
  <?php endforeach; ?>
</div>
<p class="disclaimer">No card is charged in this build. Churches, senior centers, and veterans groups: ask us about a shared license.</p>
<?php View::appClose(); ?>
