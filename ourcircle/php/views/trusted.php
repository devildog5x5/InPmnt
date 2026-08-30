<?php
/** @var array $rows */
View::appOpen(get_defined_vars());
?>
<p>Save the legitimate banks, doctors, insurers, utilities, and family numbers <em>before</em> a scare. When a message arrives, we compare it to this list — not to a number the stranger provided.</p>
<div class="panel">
  <h2>Protected contacts</h2>
  <p class="muted"><?= count($rows) ?> saved. Every contact you add stays here until you remove it.</p>
  <form id="trusted-delete" method="post"></form>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Kind</th><th>Name</th><th>Phone / site</th><th></th></tr></thead>
      <tbody>
        <?php if ($rows): ?>
          <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= Http::e($r['kind']) ?></td>
            <td><?= Http::e($r['name']) ?><div class="muted"><?= Http::e((string) $r['notes']) ?></div></td>
            <td><?= Http::tel((string) ($r['phone'] ?? '')) ?><div class="muted"><?= Http::website((string) ($r['website'] ?? '')) ?></div></td>
            <td>
              <button class="btn ghost" type="submit" form="trusted-delete" formaction="/trusted/<?= (int) $r['id'] ?>/delete" onclick="return confirm('Remove this contact?')">Remove</button>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="4">None yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<form class="panel" method="post" style="margin-top:16px" autocomplete="off">
  <h2>Add a real contact</h2>
  <label>Kind</label>
  <select name="kind">
    <option value="bank">Bank</option>
    <option value="doctor">Doctor / clinic</option>
    <option value="insurer">Insurer</option>
    <option value="utility">Utility</option>
    <option value="family">Family</option>
    <option value="other">Other</option>
  </select>
  <label>Name</label>
  <input name="name" required placeholder="Credit union fraud line" autocomplete="off" />
  <label>Phone (from a statement or the back of a card)</label>
  <input name="phone" autocomplete="off" />
  <label>Website</label>
  <input name="website" placeholder="https://" autocomplete="off" />
  <label>Notes</label>
  <input name="notes" autocomplete="off" />
  <p><button class="btn wide" type="submit">Save on trusted list</button></p>
</form>
<?php View::appClose(); ?>
