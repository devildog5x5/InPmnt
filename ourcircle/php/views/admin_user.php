<?php
/** @var array $person */
View::adminOpen(get_defined_vars());
?>
<p><a href="/admin">← Console</a></p>
<h2>Edit <?= Http::e((string) ($person['name'] ?? '')) ?></h2>
<p class="muted"><?= Http::e((string) ($person['email'] ?? '')) ?> · <?= Http::e((string) ($person['role'] ?? '')) ?>
 · <span class="pill status-<?= Http::e((string) ($person['circle_status_key'] ?? '')) ?>"><?= Http::e((string) ($person['circle_status'] ?? '')) ?></span>
<?php if (!empty($person['last_access_at'])): ?> · last access <?= Http::e((string) $person['last_access_at']) ?><?php endif; ?></p>
<form class="panel" method="post">
  <label>Name</label>
  <input name="name" value="<?= Http::e((string) ($person['name'] ?? '')) ?>" required autocomplete="name" />
  <label>Email</label>
  <input name="email" type="email" value="<?= Http::e((string) ($person['email'] ?? '')) ?>" required autocomplete="off" />
  <label>Mobile (optional)</label>
  <input name="phone" type="tel" inputmode="tel" value="<?= Http::e((string) ($person['phone'] ?? '')) ?>" placeholder="(555) 010-1234" autocomplete="off" />
  <label class="check-row"><input type="checkbox" name="sms_opt_out" value="1"<?= !empty($person['sms_opt_out']) ? ' checked' : '' ?> /> Opt out of Family Shield Pro texts</label>
  <label>New password (optional, 8+)</label>
  <input name="password" type="password" minlength="8" autocomplete="new-password" />
  <p class="muted">Leave password blank to keep the current one. This does not turn 2FA on or off.</p>
  <p><button class="btn" type="submit">Save login</button></p>
</form>
<form class="panel" method="post" action="/admin/households/<?= (int) ($person['household_id'] ?? 0) ?>" style="margin-top:16px">
  <h2>Circle</h2>
  <input type="hidden" name="return_user" value="<?= (int) ($person['id'] ?? 0) ?>" />
  <label>Circle name</label>
  <input name="name" value="<?= Http::e((string) ($person['household_name'] ?? '')) ?>" required />
  <label>Plan flag</label>
  <select name="plan">
    <option value="monthly"<?= (($person['household_plan'] ?? '') === 'monthly') ? ' selected' : '' ?>>Family monthly</option>
    <option value="yearly"<?= (($person['household_plan'] ?? '') === 'yearly') ? ' selected' : '' ?>>Family yearly</option>
  </select>
  <p class="muted">This only stores monthly or yearly. It does not charge a card or change Stripe. Stripe status: <?= Http::e((string) (($person['household_stripe_status'] ?? '') !== '' ? $person['household_stripe_status'] : 'not billed yet')) ?>.</p>
  <p><button class="btn" type="submit">Save circle</button></p>
</form>
<?php View::adminClose(); ?>
