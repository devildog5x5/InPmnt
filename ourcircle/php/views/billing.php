<?php
/** @var array $household */
/** @var array $plans */
View::appOpen(get_defined_vars());
?>
<p>This household is on <strong><?= Http::e((string) ($household['plan'] ?? 'founding')) ?></strong><?php if (!empty($household['founding'])): ?> (founding)<?php endif; ?>.</p>
<div class="plans">
  <?php foreach ($plans as $p): ?>
  <div class="panel">
    <h3><?= Http::e($p['name']) ?></h3>
    <p><strong><?= Http::e($p['price']) ?></strong></p>
    <p><?= Http::e($p['detail']) ?></p>
  </div>
  <?php endforeach; ?>
</div>
<form method="post" action="/billing/founding">
  <button class="btn gold" type="submit">Reserve founding family year · $49</button>
</form>
<p class="disclaimer">No card is charged in this build. Partnerships (credit unions, insurers) and group licenses are quoted separately.</p>
<?php View::appClose(); ?>
