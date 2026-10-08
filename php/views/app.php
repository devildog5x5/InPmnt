<!DOCTYPE html>
<html lang="en" data-theme="<?= Http::e(Http::theme()) ?>" style="color-scheme: <?= Http::theme() === 'dark' ? 'dark' : 'light' ?>">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="<?= Http::e(Http::csrfToken()) ?>" />
  <?php
    $meta_title = 'InvoicePay workspace for open invoice reminders';
    $meta_description = 'Your InvoicePay workspace for open invoices, reminder schedules, clients, and recorded payments.';
    $meta_robots = 'noindex, nofollow';
    require __DIR__ . '/_meta.php';
  ?>
  <link rel="icon" type="image/png" href="/static/img/inpmnt-icon.png" />
  <link rel="stylesheet" href="/static/css/app.css?v=<?= rawurlencode(Http::VERSION) ?>" />
</head>
<body class="app-body">
<?php require __DIR__ . '/_nav.php'; ?>
  <div class="app-shell">
    <aside class="sidebar">
      <div class="brand">
        <div class="brand-logo img">
          <img src="/static/img/inpmnt-icon.png" width="42" height="42" alt="InvoicePay logo" />
        </div>
        <div class="brand-copy">
          <div class="brand-mark">InvoicePay</div>
          <div class="brand-sub">Get paid</div>
        </div>
      </div>

      <nav class="nav" aria-label="Primary" id="main-nav">
        <div class="nav-section">
          <div class="nav-label">Overview</div>
          <a href="#/" data-route="/">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 13h7V4H4v9zm9 7h7V4h-7v16zM4 20h7v-5H4v5z"/></svg>
            Dashboard
          </a>
          <a href="#/reminders" data-route="/reminders">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 22a2.2 2.2 0 0 0 2.1-1.6H9.9A2.2 2.2 0 0 0 12 22zm7-6V11a7 7 0 1 0-14 0v5l-2 2h18l-2-2z"/></svg>
            Reminder queue
          </a>
        </div>
        <div class="nav-section">
          <div class="nav-label">Collections</div>
          <a href="#/invoices" data-route="/invoices">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M8 3h8l4 4v14H4V3h4zm0 0v4h8M8 13h8M8 17h5"/></svg>
            Invoices
          </a>
          <a href="#/clients" data-route="/clients">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zm13 10v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            Clients
          </a>
        </div>
        <div class="nav-section">
          <div class="nav-label">Workspace</div>
          <a href="#/templates" data-route="/templates">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M8 4h9l3 3v13H8V4zm0 0H5v16h3M11 12h6M11 16h4"/></svg>
            Templates
          </a>
          <a href="#/billing" data-route="/billing">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 7h18v10H3z"/><path d="M3 10h18"/><path d="M7 15h4"/></svg>
            <span id="nav-billing-label">Billing</span>
          </a>
          <a href="#/settings" data-route="/settings">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7z"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.87l.04.04a2 2 0 1 1-2.83 2.83l-.04-.04A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 .6 1.7 1.7 0 0 0-.4 1.08V21a2 2 0 1 1-4 0v-.06A1.7 1.7 0 0 0 9 19.4a1.7 1.7 0 0 0-1.87.34l-.04.04a2 2 0 1 1-2.83-2.83l.04-.04A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-.6-1A1.7 1.7 0 0 0 2.92 13.6H3a2 2 0 1 1 0-4h.06A1.7 1.7 0 0 0 4.6 9a1.7 1.7 0 0 0-.34-1.87l-.04-.04a2 2 0 1 1 2.83-2.83l.04.04A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-.6A1.7 1.7 0 0 0 10.4 2.9V3a2 2 0 1 1 4 0v.06a1.7 1.7 0 0 0 .4 1.08 1.7 1.7 0 0 0 1 .6 1.7 1.7 0 0 0 1.87-.34l.04-.04a2 2 0 1 1 2.83 2.83l-.04.04A1.7 1.7 0 0 0 19.4 9c.24.3.4.67.44 1.07H20a2 2 0 1 1 0 4h-.06c-.1.4-.26.77-.54 1.08z"/></svg>
            Settings
          </a>
          <?php if (strtolower((string) ($user['role'] ?? '')) === 'admin'): ?>
          <a href="/admin">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M4 12h16M4 17h10"/><circle cx="18" cy="17" r="3"/></svg>
            Admin
          </a>
          <?php endif; ?>
          <a href="/support">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 16.5V7.5A3.5 3.5 0 0 1 8.5 4h7A3.5 3.5 0 0 1 19 7.5v5A3.5 3.5 0 0 1 15.5 16H9l-4 3.5z"/></svg>
            Help
          </a>
          <a href="/contact">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 6h16v12H4z"/><path d="m4 7 8 6 8-6"/></svg>
            Contact
          </a>
          <a href="/logout">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>
            Log out
          </a>
        </div>
      </nav>

      <div class="sidebar-footer">
        <strong><?= Http::e($user['name'] ?? '') ?></strong>
        <span id="workspace-label">Foster Field Services · Trial</span>
      </div>
    </aside>

    <div class="app-column">
      <div id="trial-banner" class="trial-banner-host" hidden></div>
      <main class="main" id="app"></main>
    </div>
  </div>

  <?php require __DIR__ . '/_version.php'; ?>

  <div id="toast-host" class="toast-host"></div>
  <div id="modal-root" class="modal-backdrop"></div>

  <script>
    window.__INPMNT__ = {
      user: <?= json_encode(array_diff_key(is_array($user ?? null) ? $user : [], ['email' => true]), JSON_UNESCAPED_SLASHES) ?>,
      logoutUrl: "/logout",
      theme: <?= json_encode(Http::theme(), JSON_UNESCAPED_SLASHES) ?>,
      billingNotice: <?= json_encode((string) ($_SESSION['billing_notice'] ?? ''), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
      billingAdminNotice: <?= json_encode((string) ($_SESSION['billing_admin_notice'] ?? ''), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
    };
    <?php unset($_SESSION['billing_notice'], $_SESSION['billing_admin_notice']); ?>
  </script>
  <script type="module" src="/static/js/app.js?v=<?= rawurlencode(Http::VERSION) ?>"></script>
<?php require __DIR__ . '/_help_chat.php'; ?>
</body>
</html>
