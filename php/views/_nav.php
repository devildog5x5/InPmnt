<?php
$navUser = $GLOBALS['inpmnt_user'] ?? null;
$navLoggedIn = is_array($navUser) && !empty($navUser['id']);
$navIsAdmin = $navLoggedIn && strtolower((string) ($navUser['role'] ?? '')) === 'admin';
$navPath = Http::path();
$navPublic = [
    ['href' => '/', 'label' => 'Home'],
    ['href' => '/pricing', 'label' => 'Pricing'],
    ['href' => '/invoice-reminders', 'label' => 'Reminders'],
    ['href' => '/overdue-invoices', 'label' => 'Overdue invoices'],
    ['href' => '/for-contractors', 'label' => 'Contractors'],
    ['href' => '/faq', 'label' => 'FAQ'],
    ['href' => '/support', 'label' => 'Help'],
    ['href' => '/contact', 'label' => 'Contact'],
    ['href' => '/privacy', 'label' => 'Privacy'],
    ['href' => '/terms', 'label' => 'Terms'],
    ['href' => '/security', 'label' => 'Security'],
    ['href' => '/refunds', 'label' => 'Refunds'],
];
$navItems = $navLoggedIn
    ? array_merge([
        ['href' => '/app#/', 'label' => 'Dashboard'],
        ['href' => '/app#/reminders', 'label' => 'Reminder queue'],
        ['href' => '/app#/invoices', 'label' => 'Invoices'],
        ['href' => '/app#/clients', 'label' => 'Clients'],
        ['href' => '/app#/templates', 'label' => 'Templates'],
        ['href' => '/app#/settings', 'label' => 'Settings'],
        ['href' => '/app#/billing', 'label' => 'Billing'],
    ], $navPublic)
    : array_merge($navPublic, [
        ['href' => '/login', 'label' => 'Log in', 'class' => 'btn secondary sm'],
        ['href' => '/signup', 'label' => 'Start free trial', 'class' => 'btn sm'],
    ]);
if ($navIsAdmin) {
    $navItems[] = ['href' => '/admin', 'label' => 'Admin'];
}
if ($navLoggedIn) {
    $navItems[] = ['href' => '/logout', 'label' => 'Log out', 'class' => 'btn secondary sm'];
}
$navHome = $navLoggedIn ? '/app' : '/';
?>
<nav class="landing-nav site-nav" aria-label="Main">
  <a class="brand" href="<?= Http::e($navHome) ?>">
    <div class="brand-logo img">
      <picture>
        <source srcset="/static/img/inpmnt-icon.webp" type="image/webp" />
        <img src="/static/img/inpmnt-icon.png" width="42" height="42" alt="InvoicePay logo" />
      </picture>
    </div>
    <div class="brand-copy">
      <div class="brand-mark">InvoicePay</div>
      <div class="brand-sub">Get paid</div>
    </div>
  </a>
  <button type="button" class="nav-toggle" aria-expanded="false" aria-controls="site-menu">
    <span class="sr-only">Menu</span>
    <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
  </button>
  <div class="nav-actions" id="site-menu">
    <?php foreach ($navItems as $item): ?>
      <?php if (isset($item['class'])) { continue; } ?>
      <?php
        $href = (string) $item['href'];
        $itemPath = parse_url($href, PHP_URL_PATH) ?: '';
        $current = (!str_contains($href, '#') && $itemPath === $navPath) ? ' aria-current="page"' : '';
      ?>
      <a href="<?= Http::e($href) ?>"<?= $current ?>><?= Http::e((string) $item['label']) ?></a>
    <?php endforeach; ?>
  </div>
  <div class="nav-cta">
    <?php foreach ($navItems as $item): ?>
      <?php if (!isset($item['class'])) { continue; } ?>
      <?php
        $href = (string) $item['href'];
        $itemPath = parse_url($href, PHP_URL_PATH) ?: '';
        $current = (!str_contains($href, '#') && $itemPath === $navPath) ? ' aria-current="page"' : '';
      ?>
      <a class="<?= Http::e((string) $item['class']) ?>" href="<?= Http::e($href) ?>"<?= $current ?>><?= Http::e((string) $item['label']) ?></a>
    <?php endforeach; ?>
  </div>
</nav>
