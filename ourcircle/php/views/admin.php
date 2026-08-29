<?php
/** @var array $counts */
/** @var array $users */
/** @var array $invites */
/** @var string $q */
/** @var string $site_home */
View::adminOpen(get_defined_vars());
?>
<h2>Operator console</h2>
<p class="muted">Households, logins, pending invites, trusted contacts, and checks. Search by name, email, phone, or circle name. This is not shown to families.</p>
<div class="stat-grid">
  <div class="stat"><strong><?= (int) ($counts['households'] ?? 0) ?></strong><span>Households</span></div>
  <div class="stat"><strong><?= (int) ($counts['users'] ?? 0) ?></strong><span>Users</span></div>
  <div class="stat"><strong><?= (int) ($counts['pending_invites'] ?? 0) ?></strong><span>Pending invites</span></div>
  <div class="stat"><strong><?= (int) ($counts['trusted'] ?? 0) ?></strong><span>Trusted contacts</span></div>
  <div class="stat"><strong><?= (int) ($counts['checks'] ?? 0) ?></strong><span>Checks</span></div>
</div>
<form method="get" class="panel" style="margin-bottom:16px">
  <label>Search</label>
  <input name="q" value="<?= Http::e((string) ($q ?? '')) ?>" placeholder="Name, email, phone, or circle" />
  <p><button class="btn" type="submit">Search</button>
  <?php if (($q ?? '') !== ''): ?><a class="btn ghost" href="/admin">Clear</a><?php endif; ?></p>
</form>
<div class="panel">
  <h2>Users</h2>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Name</th><th>Email</th><th>Mobile</th><th>Circle</th><th>Role</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php if (!$users): ?>
        <tr><td colspan="7">No users match.</td></tr>
        <?php endif; ?>
        <?php foreach ($users as $u): ?>
        <tr>
          <td><?= Http::e((string) ($u['name'] ?? '')) ?></td>
          <td><?= Http::e((string) ($u['email'] ?? '')) ?></td>
          <td><?= Http::e((string) (($u['phone'] ?? '') !== '' ? $u['phone'] : '—')) ?></td>
          <td><?= Http::e((string) ($u['household_name'] ?? '')) ?> (<?= Http::e((string) ($u['household_plan'] ?? '')) ?>)</td>
          <td><?= Http::e((string) ($u['role'] ?? '')) ?></td>
          <td><span class="pill status-<?= Http::e((string) ($u['circle_status_key'] ?? '')) ?>"><?= Http::e((string) ($u['circle_status'] ?? '')) ?></span></td>
          <td><a class="btn ghost" href="/admin/users/<?= (int) ($u['id'] ?? 0) ?>">Edit</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<div class="panel" style="margin-top:16px">
  <h2>Pending invites</h2>
  <form id="admin-invite-resend" method="post" action="/admin/invites/resend"></form>
  <form id="admin-invite-delete" method="post" action="/admin/invites/delete"></form>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Name</th><th>Email</th><th>Mobile</th><th>Circle</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php if (!$invites): ?>
        <tr><td colspan="6">No pending invites.</td></tr>
        <?php endif; ?>
        <?php foreach ($invites as $p): ?>
        <?php $join = rtrim((string) ($site_home ?? ''), '/') . '/join/' . rawurlencode((string) ($p['token'] ?? '')); ?>
        <tr>
          <td><?= Http::e((string) (($p['name'] ?? '') !== '' ? $p['name'] : '—')) ?></td>
          <td><?= Http::e((string) ($p['email'] ?? '')) ?></td>
          <td><?= Http::e((string) (($p['phone'] ?? '') !== '' ? $p['phone'] : '—')) ?></td>
          <td><?= Http::e((string) ($p['household_name'] ?? '')) ?></td>
          <td>
            <span class="pill status-<?= Http::e((string) ($p['circle_status_key'] ?? '')) ?>"><?= Http::e((string) ($p['circle_status'] ?? '')) ?></span>
            <div class="join-url"><a href="<?= Http::e($join) ?>"><?= Http::e($join) ?></a></div>
          </td>
          <td>
            <button class="btn ghost" type="submit" form="admin-invite-resend" name="invite_id" value="<?= (int) ($p['id'] ?? 0) ?>">Resend</button>
            <button class="btn danger" type="submit" form="admin-invite-delete" name="invite_id" value="<?= (int) ($p['id'] ?? 0) ?>">Delete invite</button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php View::adminClose(); ?>
