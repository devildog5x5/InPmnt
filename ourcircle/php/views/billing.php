<?php
/** @var array $household */
/** @var array $plans */
/** @var bool $stripe_enabled */
View::appOpen(get_defined_vars());
$stripeOn = !empty($stripe_enabled);
$status = (string) ($household['stripe_status'] ?? '');
?>
<p>This household is on <strong><?= Http::e((string) ($household['plan'] ?? 'yearly')) ?></strong><?php if ($status !== ''): ?> · Stripe: <?= Http::e($status) ?><?php endif; ?>.</p>
<div class="plans">
  <?php foreach ($plans as $p): ?>
  <div class="panel<?= !empty($p['featured']) ? ' featured' : '' ?>">
    <?php if (!empty($p['featured'])): ?><span class="plan-badge">Best for families</span><?php endif; ?>
    <h3><?= Http::e($p['name']) ?></h3>
    <p><strong><?= Http::e($p['price']) ?></strong></p>
    <p><?= Http::e($p['detail']) ?></p>
    <form method="post" action="/billing/choose">
      <input type="hidden" name="plan" value="<?= Http::e($p['id']) ?>" />
      <p><button class="btn<?= !empty($p['featured']) ? ' gold' : '' ?> wide" type="submit"><?= $stripeOn ? 'Pay ' . Http::e($p['price']) : 'Choose ' . Http::e($p['name']) ?></button></p>
    </form>
  </div>
  <?php endforeach; ?>
</div>
<?php if ($stripeOn && !empty($household['stripe_customer_id'])): ?>
<form method="post" action="/billing/portal">
  <p><button class="btn ghost wide" type="submit">Update card or cancel</button></p>
</form>
<?php endif; ?>
<?php if ($stripeOn): ?>
<p class="disclaimer">Choosing a plan opens Stripe Checkout. Churches, senior centers, and veterans groups: ask us about a shared license.</p>
<?php else: ?>
<p class="disclaimer">Stripe keys are not in <code>.env</code> yet, so a plan choice is only saved on this circle. Add keys (see STRIPE.md) to charge $14.99/month or $119.99/year. Churches, senior centers, and veterans groups: ask us about a shared license.</p>
<?php endif; ?>
<?php View::appClose(); ?>
