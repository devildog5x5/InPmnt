<?php
$payUser = $GLOBALS['inpmnt_user'] ?? null;
$payLoggedIn = is_array($payUser) && !empty($payUser['id']);
$payAdmin = $payLoggedIn && strtolower((string) ($payUser['role'] ?? '')) === 'admin';
$payCfg = Billing::config();
$payCsrf = Http::csrfToken();
$payPlans = [
    'monthly' => 'Subscribe monthly — $4.99',
    'yearly' => 'Subscribe yearly — $49.99',
];
?>
<?php if (!$payCfg['enabled']): ?>
<p class="pay-setup" role="status">Payments are being set up. <?php require __DIR__ . '/_support_note.php'; ?>.</p>
<?php if ($payAdmin): ?>
<p class="pay-setup admin" role="status"><?= Http::e(Billing::adminSetupMessage()) ?></p>
<?php endif; ?>
<?php endif; ?>
<div class="pricing-grid">
  <article class="price-card">
    <h3>Monthly</h3>
    <div class="price">$4.99<span>/mo</span></div>
    <p class="plan-save">After the 14-day trial. Billed every month.</p>
    <ul>
      <li>Unlimited open invoices</li>
      <li>Email and SMS reminders</li>
      <li>Dashboard and aging</li>
      <li>Custom templates</li>
    </ul>
    <div class="price-actions">
      <?php if ($payLoggedIn): ?>
      <form method="post" action="/billing/checkout">
        <input type="hidden" name="csrf" value="<?= Http::e($payCsrf) ?>" />
        <input type="hidden" name="plan" value="monthly" />
        <button class="btn" type="submit"><?= Http::e($payPlans['monthly']) ?></button>
      </form>
      <?php else: ?>
      <a class="btn" href="/signup?plan=monthly"><?= Http::e($payPlans['monthly']) ?></a>
      <?php endif; ?>
      <a class="btn secondary" href="/signup?plan=">Start free trial</a>
    </div>
  </article>
  <article class="price-card featured">
    <div class="popular">Save $9.89 a year</div>
    <h3>Yearly</h3>
    <div class="price">$49.99<span>/yr</span></div>
    <p class="plan-save">Save $9.89 versus twelve months at $4.99.</p>
    <ul>
      <li>Same features as monthly</li>
      <li>Unlimited open invoices</li>
      <li>Email and SMS reminders</li>
      <li>One payment for the year</li>
    </ul>
    <div class="price-actions">
      <?php if ($payLoggedIn): ?>
      <form method="post" action="/billing/checkout">
        <input type="hidden" name="csrf" value="<?= Http::e($payCsrf) ?>" />
        <input type="hidden" name="plan" value="yearly" />
        <button class="btn" type="submit"><?= Http::e($payPlans['yearly']) ?></button>
      </form>
      <?php else: ?>
      <a class="btn" href="/signup?plan=yearly"><?= Http::e($payPlans['yearly']) ?></a>
      <?php endif; ?>
      <a class="btn secondary" href="/signup?plan=">Start free trial</a>
    </div>
  </article>
</div>
<p class="sub pricing-note">No credit card to start the 14-day trial. Subscribe at $4.99 a month or $49.99 a year whenever you are ready. Stripe handles the charge. InvoicePay does not store your customers’ card numbers. Cancel anytime; access continues through the time already paid.</p>
