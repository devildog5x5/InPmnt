<?php
/** @var array $household */
/** @var array $members */
/** @var array $pending */
/** @var array $trusted */
/** @var array $checks */
View::adminOpen(get_defined_vars());
$hid = (int) ($household['id'] ?? 0);
?>
<p><a href="/admin">← Console</a></p>
<h2><?= Http::e((string) ($household['name'] ?? '')) ?></h2>
<p class="muted"><?= (int) ($household['user_count'] ?? 0) ?> people · <?= (int) ($household['invite_count'] ?? 0) ?> invites · <?= (int) ($household['trusted_count'] ?? 0) ?> trusted · <?= (int) ($household['check_count'] ?? 0) ?> checks · Stripe: <?= Http::e((string) (($household['stripe_status'] ?? '') !== '' ? $household['stripe_status'] : 'not billed yet')) ?></p>
<form class="panel" method="post">
  <label>Circle name</label>
  <input name="name" value="<?= Http::e((string) ($household['name'] ?? '')) ?>" required />
  <label>Plan flag</label>
  <select name="plan">
    <option value="monthly"<?= (($household['plan'] ?? '') === 'monthly') ? ' selected' : '' ?>>Family monthly</option>
    <option value="yearly"<?= (($household['plan'] ?? '') === 'yearly') ? ' selected' : '' ?>>Family yearly</option>
  </select>
  <p class="muted">This only stores monthly or yearly. It does not charge a card or change Stripe.</p>
  <p><button class="btn" type="submit">Save circle</button></p>
</form>
<div class="panel" style="margin-top:16px">
  <h2>People</h2>
  <form id="admin-user-delete" method="post" action="/admin/users/delete">
    <input type="hidden" name="return_household" value="<?= $hid ?>" />
  </form>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Name</th><th>Email</th><th>Mobile</th><th>Role</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($members as $u): ?>
        <tr>
          <td><?= Http::e((string) ($u['name'] ?? '')) ?></td>
          <td><?= Http::e((string) ($u['email'] ?? '')) ?></td>
          <td><?= Http::e((string) (($u['phone'] ?? '') !== '' ? $u['phone'] : '—')) ?></td>
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
  <h2>Add a user to this circle</h2>
  <input type="hidden" name="household_id" value="<?= $hid ?>" />
  <input type="hidden" name="return_household" value="<?= $hid ?>" />
  <label>Name</label>
  <input name="name" required autocomplete="off" />
  <label>Email</label>
  <input name="email" type="email" required autocomplete="off" />
  <label>Password (8+)</label>
  <input name="password" type="password" required minlength="8" autocomplete="new-password" />
  <label>Role</label>
  <select name="role"><option value="member">member</option><option value="owner">owner</option></select>
  <label>Mobile (optional)</label>
  <input name="phone" type="tel" autocomplete="off" />
  <p><button class="btn" type="submit">Add user</button></p>
</form>
<div class="panel" style="margin-top:16px">
  <h2>Pending invites</h2>
  <form id="admin-invite-resend" method="post" action="/admin/invites/resend"><input type="hidden" name="return_household" value="<?= $hid ?>" /></form>
  <form id="admin-invite-delete" method="post" action="/admin/invites/delete"><input type="hidden" name="return_household" value="<?= $hid ?>" /></form>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Name</th><th>Email</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php if (!$pending): ?><tr><td colspan="4">None waiting.</td></tr><?php endif; ?>
        <?php foreach ($pending as $p): ?>
        <tr>
          <td><?= Http::e((string) (($p['name'] ?? '') !== '' ? $p['name'] : '—')) ?></td>
          <td><?= Http::e((string) ($p['email'] ?? '')) ?></td>
          <td><span class="pill status-<?= Http::e((string) ($p['circle_status_key'] ?? '')) ?>"><?= Http::e((string) ($p['circle_status'] ?? '')) ?></span></td>
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
  <input type="hidden" name="household_id" value="<?= $hid ?>" />
  <input type="hidden" name="return_household" value="<?= $hid ?>" />
  <label>Invite name</label>
  <input name="name" autocomplete="off" />
  <label>Invite email</label>
  <input name="email" type="email" required autocomplete="off" />
  <label>Mobile (optional)</label>
  <input name="phone" type="tel" autocomplete="off" />
  <p><button class="btn" type="submit">Add invite</button></p>
</form>
<div class="panel" style="margin-top:16px">
  <h2>Trusted list</h2>
  <form id="admin-trusted-delete" method="post" action="/admin/trusted/delete"><input type="hidden" name="return_household" value="<?= $hid ?>" /></form>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Kind</th><th>Name</th><th>Phone</th><th></th></tr></thead>
      <tbody>
        <?php if (!$trusted): ?><tr><td colspan="4">None saved.</td></tr><?php endif; ?>
        <?php foreach ($trusted as $t): ?>
        <tr>
          <td><?= Http::e((string) ($t['kind'] ?? '')) ?></td>
          <td><?= Http::e((string) ($t['name'] ?? '')) ?></td>
          <td><?= Http::e((string) (($t['phone'] ?? '') !== '' ? $t['phone'] : '—')) ?></td>
          <td><button class="btn danger" type="submit" form="admin-trusted-delete" name="contact_id" value="<?= (int) ($t['id'] ?? 0) ?>" onclick="return confirm('Remove this contact?')">Delete</button></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<form class="panel" method="post" action="/admin/trusted/create" style="margin-top:16px">
  <input type="hidden" name="household_id" value="<?= $hid ?>" />
  <input type="hidden" name="return_household" value="<?= $hid ?>" />
  <label>Kind</label>
  <select name="kind">
    <option value="bank">bank</option><option value="doctor">doctor</option><option value="insurer">insurer</option>
    <option value="utility">utility</option><option value="family">family</option><option value="other">other</option>
  </select>
  <label>Name</label>
  <input name="name" required />
  <label>Phone</label>
  <input name="phone" />
  <label>Website</label>
  <input name="website" />
  <label>Notes</label>
  <input name="notes" />
  <p><button class="btn" type="submit">Add trusted contact</button></p>
</form>
<div class="panel" style="margin-top:16px">
  <h2>Checks</h2>
  <form id="admin-check-delete" method="post" action="/admin/checks/delete"><input type="hidden" name="return_household" value="<?= $hid ?>" /></form>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>When</th><th>Who</th><th>Kind</th><th>Risk</th><th></th></tr></thead>
      <tbody>
        <?php if (!$checks): ?><tr><td colspan="5">No checks.</td></tr><?php endif; ?>
        <?php foreach ($checks as $c): ?>
        <tr>
          <td><?= Http::e((string) ($c['created_at'] ?? '')) ?></td>
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
<form class="panel" method="post" action="/admin/households/delete" style="margin-top:16px" onsubmit="return confirm('Delete this circle and everyone in it?')">
  <input type="hidden" name="household_id" value="<?= $hid ?>" />
  <p><button class="btn danger" type="submit">Delete this circle</button></p>
</form>
<?php View::adminClose(); ?>
