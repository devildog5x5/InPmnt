<?php
/** @var array $counts */
/** @var array $users */
/** @var array $invites */
/** @var array $households */
/** @var array $checks */
/** @var string $q */
/** @var string $site_home */
View::adminOpen(get_defined_vars());
?>
<h2>Operator console</h2>
<p class="muted">Add, edit, and delete circles, logins, invites, trusted contacts, and checks. This is not shown to families. It cannot change .env, SMTP, Stripe, or Twilio keys. The last owner of a circle cannot be deleted — delete the circle, or add another owner first.</p>
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
  <h2>Circles</h2>
  <form id="admin-household-delete" method="post" action="/admin/households/delete"></form>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Name</th><th>Plan</th><th>People</th><th>Invites</th><th>Trusted</th><th>Checks</th><th></th></tr></thead>
      <tbody>
        <?php if (!$households): ?>
        <tr><td colspan="7">No circles match.</td></tr>
        <?php endif; ?>
        <?php foreach ($households as $h): ?>
        <tr>
          <td><?= Http::e((string) ($h['name'] ?? '')) ?></td>
          <td><?= Http::e((string) ($h['plan'] ?? '')) ?></td>
          <td><?= (int) ($h['user_count'] ?? 0) ?></td>
          <td><?= (int) ($h['invite_count'] ?? 0) ?></td>
          <td><?= (int) ($h['trusted_count'] ?? 0) ?></td>
          <td><?= (int) ($h['check_count'] ?? 0) ?></td>
          <td>
            <a class="btn ghost" href="/admin/households/<?= (int) ($h['id'] ?? 0) ?>">Open</a>
            <button class="btn danger" type="submit" form="admin-household-delete" name="household_id" value="<?= (int) ($h['id'] ?? 0) ?>" onclick="return confirm('Delete this circle and everyone in it?')">Delete circle</button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<form class="panel" method="post" action="/admin/households/create" style="margin-top:16px">
  <h2>Add a circle</h2>
  <label>Circle name</label>
  <input name="name" required placeholder="The Smith circle" />
  <label>Plan flag</label>
  <select name="plan">
    <option value="yearly">Family yearly</option>
    <option value="monthly">Family monthly</option>
  </select>
  <label>First owner name</label>
  <input name="owner_name" required autocomplete="off" />
  <label>First owner email</label>
  <input name="owner_email" type="email" required autocomplete="off" />
  <label>First owner password (8+)</label>
  <input name="owner_password" type="password" required minlength="8" autocomplete="new-password" />
  <label>Mobile (optional)</label>
  <input name="phone" type="tel" inputmode="tel" autocomplete="off" />
  <p><button class="btn" type="submit">Add circle</button></p>
</form>

<div class="panel" style="margin-top:16px">
  <h2>Users</h2>
  <form id="admin-user-delete" method="post" action="/admin/users/delete"></form>
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
          <td>
            <a class="btn ghost" href="/admin/users/<?= (int) ($u['id'] ?? 0) ?>">Edit</a>
            <button class="btn danger" type="submit" form="admin-user-delete" name="user_id" value="<?= (int) ($u['id'] ?? 0) ?>" onclick="return confirm('Delete this login?')">Delete</button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<form class="panel" method="post" action="/admin/users/create" style="margin-top:16px">
  <h2>Add a user</h2>
  <label>Circle</label>
  <select name="household_id" required>
    <?php foreach ($households as $h): ?>
    <option value="<?= (int) ($h['id'] ?? 0) ?>"><?= Http::e((string) ($h['name'] ?? '')) ?></option>
    <?php endforeach; ?>
  </select>
  <label>Name</label>
  <input name="name" required autocomplete="off" />
  <label>Email</label>
  <input name="email" type="email" required autocomplete="off" />
  <label>Password (8+)</label>
  <input name="password" type="password" required minlength="8" autocomplete="new-password" />
  <label>Role</label>
  <select name="role">
    <option value="member">member</option>
    <option value="owner">owner</option>
  </select>
  <label>Mobile (optional)</label>
  <input name="phone" type="tel" inputmode="tel" autocomplete="off" />
  <p><button class="btn" type="submit">Add user</button></p>
</form>

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
<form class="panel" method="post" action="/admin/invites/create" style="margin-top:16px">
  <h2>Add an invite</h2>
  <label>Circle</label>
  <select name="household_id" required>
    <?php foreach ($households as $h): ?>
    <option value="<?= (int) ($h['id'] ?? 0) ?>"><?= Http::e((string) ($h['name'] ?? '')) ?></option>
    <?php endforeach; ?>
  </select>
  <label>Name</label>
  <input name="name" autocomplete="off" />
  <label>Email</label>
  <input name="email" type="email" required autocomplete="off" />
  <label>Mobile (optional)</label>
  <input name="phone" type="tel" inputmode="tel" autocomplete="off" />
  <p><button class="btn" type="submit">Add invite</button></p>
</form>

<div class="panel" style="margin-top:16px">
  <h2>Recent checks</h2>
  <form id="admin-check-delete" method="post" action="/admin/checks/delete"></form>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>When</th><th>Circle</th><th>Who</th><th>Kind</th><th>Risk</th><th></th></tr></thead>
      <tbody>
        <?php if (empty($checks)): ?>
        <tr><td colspan="6">No checks yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($checks as $c): ?>
        <tr>
          <td><?= Http::e((string) ($c['created_at'] ?? '')) ?></td>
          <td><?= Http::e((string) ($c['household_name'] ?? '')) ?></td>
          <td><?= Http::e((string) ($c['user_name'] ?? '')) ?></td>
          <td><?= Http::e((string) ($c['kind'] ?? '')) ?></td>
          <td><?= Http::e((string) ($c['risk'] ?? '')) ?></td>
          <td><button class="btn danger" type="submit" form="admin-check-delete" name="check_id" value="<?= (int) ($c['id'] ?? 0) ?>" onclick="return confirm('Delete this check?')">Delete</button></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php View::adminClose(); ?>
