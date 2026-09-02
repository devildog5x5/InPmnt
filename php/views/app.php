<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>InPmnt</title>
  <meta name="robots" content="noindex, nofollow" />
  <link rel="icon" type="image/png" href="/static/img/inpmnt-icon.png" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600;700&family=Source+Serif+4:opsz,wght@8..60,500;8..60,600;8..60,700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/static/css/app.css" />
  <?php require __DIR__ . '/_client_boot.php'; ?>
</head>
<body>
  <div class="app-shell">
    <aside class="sidebar">
      <div class="brand">
        <div class="brand-logo img" aria-hidden="true">
          <img src="/static/img/inpmnt-icon.png" alt="" />
        </div>
        <div class="brand-copy">
          <div class="brand-mark">InPmnt</div>
          <div class="brand-sub">Get paid without the chase</div>
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
          <a href="#/settings" data-route="/settings">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7z"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.87l.04.04a2 2 0 1 1-2.83 2.83l-.04-.04A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 .6 1.7 1.7 0 0 0-.4 1.08V21a2 2 0 1 1-4 0v-.06A1.7 1.7 0 0 0 9 19.4a1.7 1.7 0 0 0-1.87.34l-.04.04a2 2 0 1 1-2.83-2.83l.04-.04A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-.6-1A1.7 1.7 0 0 0 2.92 13.6H3a2 2 0 1 1 0-4h.06A1.7 1.7 0 0 0 4.6 9a1.7 1.7 0 0 0-.34-1.87l-.04-.04a2 2 0 1 1 2.83-2.83l.04.04A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-.6A1.7 1.7 0 0 0 10.4 2.9V3a2 2 0 1 1 4 0v.06a1.7 1.7 0 0 0 .4 1.08 1.7 1.7 0 0 0 1 .6 1.7 1.7 0 0 0 1.87-.34l.04-.04a2 2 0 1 1 2.83 2.83l-.04.04A1.7 1.7 0 0 0 19.4 9c.24.3.4.67.44 1.07H20a2 2 0 1 1 0 4h-.06c-.1.4-.26.77-.54 1.08z"/></svg>
            Settings
          </a>
        </div>
      </nav>

      <div class="sidebar-footer">
        <strong><?= Http::e($user['name'] ?? '') ?></strong>
        <span id="workspace-label">Foster Field Services · Trial</span>
      </div>
    </aside>

    <main class="main" id="app"></main>
  </div>

  <div id="toast-host" class="toast-host"></div>
  <div id="modal-root" class="modal-backdrop"></div>

  <script>
    window.__INPMNT__ = {
      user: <?= json_encode($user ?? new stdClass(), JSON_UNESCAPED_SLASHES) ?>,
      logoutUrl: "/logout"
    };
  </script>
  <script type="module" src="/static/js/app.js"></script>
</body>
</html>
