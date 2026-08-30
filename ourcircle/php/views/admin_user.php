<?php
/** @var array $person */
/** @var array $households */
View::adminOpen(get_defined_vars());
$totp = !empty($person['totp_enabled']);
?>
<p><a href="/admin">← Console</a>
 · <a href="/admin/households/<?= (int) ($person['household_id'] ?? 0) ?>">Open circle</a></p>
<h2>Edit <?= Http::e((string) ($person['name'] ?? '')) ?></h2>
<p class="muted"><?= Http::e((string) ($person['email'] ?? '')) ?> · <?= Http::e((string) ($person['role'] ?? '')) ?>
 · <span class="pill status-<?= Http::e((string) ($person['circle_status_key'] ?? '')) ?>"><?= Http::e((string) ($person['circle_status'] ?? '')) ?></span>
<?php if (!empty($person['last_access_at'])): ?> · last access <?= Http::e((string) $person['last_access_at']) ?><?php endif; ?>
 · <?= $totp ? '2FA on' : '2FA off' ?></p>
<form class="panel" method="post">
  <label>Name</label>
  <input name="name" value="<?= Http::e((string) ($person['name'] ?? '')) ?>" required autocomplete="name" />
  <label>Email</label>
  <input name="email" type="email" value="<?= Http::e((string) ($person['email'] ?? '')) ?>" required autocomplete="off" />
  <label>Mobile (optional)</label>
  <input name="phone" type="tel" inputmode="tel" value="<?= Http::e((string) ($person['phone'] ?? '')) ?>" placeholder="(555) 010-1234" autocomplete="off" />
  <label class="check-row"><input type="checkbox" name="sms_opt_out" value="1"<?= !empty($person['sms_opt_out']) ? ' checked' : '' ?> /> Opt out of Family Shield Pro texts</label>
  <label>Role</label>
  <select name="role">
    <option value="owner"<?= (($person['role'] ?? '') === 'owner') ? ' selected' : '' ?>>owner</option>
    <option value="member"<?= (($person['role'] ?? '') === 'member') ? ' selected' : '' ?>>member</option>
  </select>
  <label>Circle</label>
  <select name="household_id">
    <?php foreach ($households as $h): ?>
    <option value="<?= (int) ($h['id'] ?? 0) ?>"<?= ((int) ($h['id'] ?? 0) === (int) ($person['household_id'] ?? 0)) ? ' selected' : '' ?>><?= Http::e((string) ($h['name'] ?? '')) ?></option>
    <?php endforeach; ?>
  </select>
  <label>New password (optional, 8+)</label>
  <input name="password" type="password" minlength="8" autocomplete="new-password" />
  <p class="muted">Leave password blank to keep the current one. The last owner cannot be demoted or moved until another owner exists.</p>
  <p><button class="btn" type="submit">Save login</button></p>
</form>
<?php if ($totp): ?>
<form class="panel" method="post" action="/admin/users/disable-2fa" style="margin-top:16px">
  <input type="hidden" name="user_id" value="<?= (int) ($person['id'] ?? 0) ?>" />
  <p>Turn off 2FA if they are locked out of their authenticator.</p>
  <p><button class="btn ghost" type="submit">Turn off 2FA</button></p>
</form>
<?php endif; ?>
<form class="panel" method="post" action="/admin/users/delete" style="margin-top:16px" onsubmit="return confirm('Delete this login?')">
  <input type="hidden" name="user_id" value="<?= (int) ($person['id'] ?? 0) ?>" />
  <p><button class="btn danger" type="submit">Delete this login</button></p>
</form>
<?php View::adminClose(); ?>
