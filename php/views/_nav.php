<?php
$navUser = $GLOBALS['inpmnt_user'] ?? null;
$navLoggedIn = is_array($navUser) && !empty($navUser['id']);
$navIsAdmin = $navLoggedIn && strtolower((string) ($navUser['role'] ?? '')) === 'admin';
$navPath = Http::path();
$navItems = $navLoggedIn
    ? [
        ['href' => '/app#/', 'label' => 'Dashboard'],
        ['href' => '/app#/reminders', 'label' => 'Reminders'],
        ['href' => '/app#/invoices', 'label' => 'Invoices'],
        ['href' => '/app#/clients', 'label' => 'Clients'],
        ['href' => '/app#/templates', 'label' => 'Templates'],
        ['href' => '/app#/settings', 'label' => 'Settings'],
        ['href' => '/support', 'label' => 'Help'],
        ['href' => '/contact', 'label' => 'Contact'],
        ['href' => '/privacy', 'label' => 'Privacy'],
        ['href' => '/terms', 'label' => 'Terms'],
        ['href' => '/security', 'label' => 'Security'],
        ['href' => '/refunds', 'label' => 'Refunds'],
    ]
    : [
        ['href' => '/', 'label' => 'Home'],
        ['href' => '/#how', 'label' => 'How it works'],
        ['href' => '/#pricing', 'label' => 'Pricing'],
        ['href' => '/support', 'label' => 'Help'],
        ['href' => '/contact', 'label' => 'Contact'],
        ['href' => '/privacy', 'label' => 'Privacy'],
        ['href' => '/terms', 'label' => 'Terms'],
        ['href' => '/security', 'label' => 'Security'],
        ['href' => '/refunds', 'label' => 'Refunds'],
        ['href' => '/login', 'label' => 'Log in', 'class' => 'btn secondary sm'],
        ['href' => '/signup', 'label' => 'Start free trial', 'class' => 'btn sm'],
    ];
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
    <div class="brand-logo img" aria-hidden="true"><img src="/static/img/inpmnt-icon.png" alt="" /></div>
    <div class="brand-copy">
      <div class="brand-mark">InPmnt</div>
      <div class="brand-sub">by InvcPay</div>
    </div>
  </a>
  <div class="nav-actions">
    <?php foreach ($navItems as $item): ?>
      <?php
        $href = (string) $item['href'];
        $itemPath = parse_url($href, PHP_URL_PATH) ?: '';
        $current = (!str_contains($href, '#') && $itemPath === $navPath) ? ' aria-current="page"' : '';
        $class = isset($item['class']) ? ' class="' . Http::e((string) $item['class']) . '"' : '';
      ?>
      <a href="<?= Http::e($href) ?>"<?= $class ?><?= $current ?>><?= Http::e((string) $item['label']) ?></a>
    <?php endforeach; ?>
  </div>
</nav>
