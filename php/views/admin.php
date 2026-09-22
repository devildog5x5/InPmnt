<?php
/** Copyright (c) 2026 Robert Foster */
$tables = $tables ?? [];
$table = $table ?? null;
$browse = $browse ?? null;
$edit = $edit ?? null;
$columns = $columns ?? [];
$sql = $sql ?? '';
$sqlResult = $sql_result ?? null;
$flash = $flash ?? null;
$error = $error ?? null;
$mode = $mode ?? 'home';
$base = '/admin';
$ver = Http::VERSION;
$csrf = Admin::csrfToken();
?>
<!doctype html>
<html lang="en" data-theme="light" style="color-scheme: light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <meta name="csrf-token" content="<?= Http::e(Http::csrfToken()) ?>">
  <title>Admin · InPmnt <?= Http::e($ver) ?></title>
  <meta name="description" content="Owner console to browse InPmnt accounts, invoices, payment reminders, and billing records.">
  <link rel="stylesheet" href="/static/css/app.css?v=<?= rawurlencode(Http::VERSION) ?>">
  <script>
  (function () {
    try {
      var t = sessionStorage.getItem('inpmnt_admin_theme') || 'light';
      if (t !== 'dark') t = 'light';
      document.documentElement.setAttribute('data-theme', t);
      document.documentElement.style.colorScheme = t;
    } catch (e) {}
  })();
  </script>
  <style>
    :root {
      --bg: #f4f7f6; --card: #fff; --text: #14201c; --muted: #5b6b65; --line: #d7e0dc;
      --accent: #0d7a62; --danger: #a4262c; --nav: #eef5f2;
      --brand: #0d7a62; --brand-dark: #0a5f4c; --brand-soft: #e5f4f3;
      --surface: #fff; --surface-2: #f4f7f6; --ink: #14201c;
      --font-body: "IBM Plex Sans", system-ui, sans-serif;
      --shadow-lg: 0 16px 40px rgba(0,0,0,0.16);
    }
    html[data-theme="dark"] {
      --bg: #101816; --card: #1a2421; --text: #e8f2ee; --muted: #9bb0a7; --line: #2c3a35;
      --accent: #3dba9a; --danger: #f1707b; --nav: #0c1210;
      --brand: #3dba9a; --brand-dark: #2a9a7e; --brand-soft: #1a2421;
      --surface: #1a2421; --surface-2: #101816; --ink: #e8f2ee;
    }
    * { box-sizing: border-box; }
    body { margin: 0; font: 14px/1.45 "IBM Plex Sans", system-ui, sans-serif; background: var(--bg); color: var(--text); }
    a { color: var(--accent); }
    .top {
      display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: space-between;
      padding: 10px 16px; background: var(--nav); border-bottom: 1px solid var(--line);
    }
    .brand { font-weight: 650; text-decoration: none; color: var(--text); }
    .wrap { max-width: 1200px; margin: 0 auto; padding: 16px; }
    .grid { display: grid; grid-template-columns: 220px 1fr; gap: 16px; }
    @media (max-width: 800px) { .grid { grid-template-columns: 1fr; } }
    .card { background: var(--card); border: 1px solid var(--line); border-radius: 6px; padding: 14px; }
    .side a { display: block; padding: 6px 8px; border-radius: 4px; text-decoration: none; color: var(--text); }
    .side a:hover, .side a.on { background: var(--bg); }
    .muted { color: var(--muted); }
    .flash { background: #dff6dd; color: #0e7a3d; padding: 8px 10px; border-radius: 4px; margin-bottom: 12px; }
    html[data-theme="dark"] .flash { background: #0e3b22; color: #b7f0c8; }
    .err { background: #fde7e9; color: #a4262c; padding: 8px 10px; border-radius: 4px; margin-bottom: 12px; }
    html[data-theme="dark"] .err { background: #3b1215; color: #f7c0c4; }
    table.data { width: 100%; border-collapse: collapse; font-size: 12px; }
    table.data th, table.data td {
      border-bottom: 1px solid var(--line); padding: 6px 8px; text-align: left; vertical-align: top;
      max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }
    table.data th { color: var(--muted); font-weight: 600; }
    .toolbar { display: flex; flex-wrap: wrap; gap: 8px; margin: 0 0 12px; align-items: center; }
    button, .btn {
      appearance: none; border: 1px solid var(--line); background: var(--card); color: var(--text);
      border-radius: 4px; padding: 7px 12px; cursor: pointer; text-decoration: none; font: inherit;
    }
    button.primary, .btn.primary { background: var(--accent); border-color: var(--accent); color: #fff; }
    button.danger, .btn.danger { background: var(--danger); border-color: var(--danger); color: #fff; }
    input, textarea, select {
      width: 100%; padding: 8px 10px; border: 1px solid var(--line); border-radius: 4px;
      background: var(--card); color: var(--text); font: inherit;
    }
    textarea.sql { min-height: 140px; font-family: ui-monospace, Consolas, monospace; }
    .field { margin-bottom: 10px; }
    .field label { display: block; font-size: 12px; color: var(--muted); margin-bottom: 4px; }
    .pager { display: flex; gap: 8px; align-items: center; margin-top: 10px; }
    .foot { margin-top: 18px; color: var(--muted); font-size: 12px; display: flex; justify-content: space-between; }
  </style>
</head>
<body>
  <header class="top">
    <div>
      <a class="brand" href="<?= Http::e($base) ?>">InPmnt Admin</a>
      <span class="muted"> · <?= Http::e($ver) ?></span>
    </div>
    <div class="toolbar" style="margin:0">
      <button type="button" data-admin-theme="light">Light</button>
      <button type="button" data-admin-theme="dark">Dark</button>
      <a class="btn" href="/app">App</a>
      <a class="btn" href="<?= Http::e($base) ?>?view=sql">SQL</a>
    </div>
  </header>
  <div class="wrap">
    <?php if ($flash): ?><div class="flash"><?= Http::e($flash) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="err"><?= Http::e($error) ?></div><?php endif; ?>
    <div class="grid">
      <aside class="card side">
        <strong>Tables</strong>
        <p class="muted" style="margin:6px 0 10px">Tap a table to browse rows.</p>
        <?php foreach ($tables as $t): ?>
          <a class="<?= ($table === $t['name']) ? 'on' : '' ?>"
             href="<?= Http::e($base . '?table=' . rawurlencode($t['name'])) ?>">
            <?= Http::e($t['name']) ?>
            <span class="muted"><?= (int) $t['rows'] ?></span>
          </a>
        <?php endforeach; ?>
      </aside>
      <main>
        <?php if ($mode === 'sql'): ?>
          <div class="card">
            <strong>SQL</strong>
            <p class="muted">One statement. SELECT runs immediately. Writes need the confirm box.</p>
            <form method="post" action="<?= Http::e($base) ?>">
              <input type="hidden" name="csrf" value="<?= Http::e($csrf) ?>">
              <input type="hidden" name="op" value="sql">
              <div class="field">
                <textarea class="sql" name="sql" required><?= Http::e($sql) ?></textarea>
              </div>
              <label class="muted"><input type="checkbox" name="confirm_write" value="1"> I confirm this may change data</label>
              <div class="toolbar" style="margin-top:10px">
                <button class="primary" type="submit">Run</button>
              </div>
            </form>
            <?php if (is_array($sqlResult)): ?>
              <?php if (!empty($sqlResult['error'])): ?>
                <div class="err" style="margin-top:12px"><?= Http::e((string) $sqlResult['error']) ?></div>
              <?php elseif (!empty($sqlResult['write'])): ?>
                <p class="muted" style="margin-top:12px">OK — affected <?= (int) ($sqlResult['affected'] ?? 0) ?> row(s).</p>
              <?php else: ?>
                <p class="muted" style="margin-top:12px"><?= (int) ($sqlResult['affected'] ?? 0) ?> row(s) (max 500 shown).</p>
                <div style="overflow:auto">
                  <table class="data">
                    <thead><tr>
                      <?php foreach (($sqlResult['columns'] ?? []) as $c): ?>
                        <th><?= Http::e((string) $c) ?></th>
                      <?php endforeach; ?>
                    </tr></thead>
                    <tbody>
                      <?php foreach (($sqlResult['rows'] ?? []) as $r): ?>
                        <tr>
                          <?php foreach (($sqlResult['columns'] ?? []) as $c): ?>
                            <td title="<?= Http::e((string) ($r[$c] ?? '')) ?>"><?= Http::e((string) ($r[$c] ?? '')) ?></td>
                          <?php endforeach; ?>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php endif; ?>
            <?php endif; ?>
          </div>

        <?php elseif ($mode === 'edit' || $mode === 'new'): ?>
          <div class="card">
            <div class="toolbar">
              <strong><?= $mode === 'new' ? 'New row' : 'Edit row' ?> · <?= Http::e((string) $table) ?></strong>
              <a class="btn" href="<?= Http::e($base . '?table=' . rawurlencode((string) $table)) ?>">Back</a>
            </div>
            <form method="post" action="<?= Http::e($base) ?>">
              <input type="hidden" name="csrf" value="<?= Http::e($csrf) ?>">
              <input type="hidden" name="op" value="<?= $mode === 'new' ? 'insert' : 'update' ?>">
              <input type="hidden" name="table" value="<?= Http::e((string) $table) ?>">
              <?php if ($mode === 'edit'): ?>
                <input type="hidden" name="rowid" value="<?= (int) ($edit['__rowid'] ?? 0) ?>">
              <?php endif; ?>
              <?php foreach ($columns as $c): ?>
                <?php
                  $n = $c['name'];
                  $val = $mode === 'edit' ? (string) ($edit[$n] ?? '') : '';
                ?>
                <div class="field">
                  <label><?= Http::e($n) ?><?php if ($c['pk']): ?> · pk<?php endif; ?><?php if ($c['notnull']): ?> · required<?php endif; ?></label>
                  <?php if (strlen($val) > 120 || str_contains(strtolower($c['type']), 'text')): ?>
                    <textarea name="f[<?= Http::e($n) ?>]" rows="3"><?= Http::e($val) ?></textarea>
                  <?php else: ?>
                    <input name="f[<?= Http::e($n) ?>]" value="<?= Http::e($val) ?>">
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
              <div class="toolbar">
                <button class="primary" type="submit">Save</button>
                <?php if ($mode === 'edit'): ?>
                  <button class="danger" type="submit" name="op" value="delete"
                          onclick="return confirm('Delete this row?');">Delete</button>
                <?php endif; ?>
              </div>
            </form>
          </div>

        <?php elseif ($mode === 'browse' && $browse): ?>
          <div class="card">
            <div class="toolbar">
              <strong><?= Http::e((string) $table) ?></strong>
              <span class="muted"><?= (int) $browse['total'] ?> rows</span>
              <a class="btn primary" href="<?= Http::e($base . '?table=' . rawurlencode((string) $table) . '&new=1') ?>">New</a>
            </div>
            <div style="overflow:auto">
              <table class="data">
                <thead>
                  <tr>
                    <?php foreach ($browse['columns'] as $c): ?>
                      <th><?= Http::e((string) $c) ?></th>
                    <?php endforeach; ?>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($browse['rows'] as $r): ?>
                    <tr>
                      <?php foreach ($browse['columns'] as $c): ?>
                        <td title="<?= Http::e((string) ($r[$c] ?? '')) ?>"><?= Http::e((string) ($r[$c] ?? '')) ?></td>
                      <?php endforeach; ?>
                      <td>
                        <a href="<?= Http::e($base . '?table=' . rawurlencode((string) $table) . '&rowid=' . (int) $r['__rowid']) ?>">Edit</a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <div class="pager">
              <?php if ($browse['page'] > 1): ?>
                <a class="btn" href="<?= Http::e($base . '?table=' . rawurlencode((string) $table) . '&page=' . ($browse['page'] - 1)) ?>">Prev</a>
              <?php endif; ?>
              <span class="muted">Page <?= (int) $browse['page'] ?> / <?= (int) $browse['pages'] ?></span>
              <?php if ($browse['page'] < $browse['pages']): ?>
                <a class="btn" href="<?= Http::e($base . '?table=' . rawurlencode((string) $table) . '&page=' . ($browse['page'] + 1)) ?>">Next</a>
              <?php endif; ?>
            </div>
          </div>

        <?php else: ?>
          <div class="card">
            <strong>Database</strong>
            <p class="muted">Admin-only console for this InPmnt SQLite file. Pick a table or open SQL.</p>
            <div class="toolbar">
              <a class="btn primary" href="<?= Http::e($base) ?>?view=sql">Open SQL</a>
            </div>
          </div>
        <?php endif; ?>
      </main>
    </div>
    <footer class="foot">
      <span>InPmnt <?= Http::e($ver) ?></span>
      <span>© 2026 Robert Foster</span>
    </footer>
  </div>
  <script>
  (function () {
    var key = 'inpmnt_admin_theme';
    function apply(t) {
      if (t !== 'dark') t = 'light';
      document.documentElement.setAttribute('data-theme', t);
      document.documentElement.style.colorScheme = t;
      try { sessionStorage.setItem(key, t); } catch (e) {}
      document.querySelectorAll('[data-admin-theme]').forEach(function (b) {
        b.classList.toggle('primary', b.getAttribute('data-admin-theme') === t);
      });
    }
    apply((function () { try { return sessionStorage.getItem(key) || 'light'; } catch (e) { return 'light'; } })());
    document.querySelectorAll('[data-admin-theme]').forEach(function (b) {
      b.addEventListener('click', function () { apply(b.getAttribute('data-admin-theme')); });
    });
  })();
  </script>
<?php require __DIR__ . '/_help_chat.php'; ?>
</body>
</html>
