<?php
/** @var array $members */
/** @var array $pending */
/** @var array $alerts */
/** @var string $site_home */
/** @var bool $sms_enabled */
View::appOpen(get_defined_vars());
?>
<div class="grid-2">
  <div class="panel">
    <h2>People in this circle</h2>
    <p class="muted">Up to five people on the family plan. Invite the person who will actually answer the phone.</p>
    <p class="status-legend" aria-label="Circle status">
      <span class="pill status-invited">Invited</span>
      <span class="status-arrow" aria-hidden="true">→</span>
      <span class="pill status-sent">Invite sent</span>
      <span class="status-arrow" aria-hidden="true">→</span>
      <span class="pill status-accepted">Invite Accepted</span>
      <span class="status-arrow" aria-hidden="true">→</span>
      <span class="pill status-access">User Accesses the Circle</span>
    </p>
    <table class="table">
      <thead><tr><th>Name</th><th>Email</th><th>Mobile</th><th>Role</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($members as $m): ?>
        <tr>
          <td><?= Http::e((string) ($m['name'] ?? '')) ?></td>
          <td><?= Http::e((string) ($m['email'] ?? '')) ?></td>
          <td><?= Http::e((string) (($m['phone'] ?? '') !== '' ? $m['phone'] : '—')) ?></td>
          <td><?= Http::e((string) ($m['role'] ?? '')) ?></td>
          <td><span class="pill status-<?= Http::e((string) ($m['circle_status_key'] ?? '')) ?>"><?= Http::e((string) ($m['circle_status'] ?? '')) ?></span></td>
        </tr>
        <?php endforeach; ?>
        <?php foreach ($pending as $p): ?>
        <?php $join = rtrim((string) ($site_home ?? ''), '/') . '/join/' . rawurlencode((string) ($p['token'] ?? '')); ?>
        <tr>
          <td><?= Http::e((string) (($p['name'] ?? '') !== '' ? $p['name'] : '—')) ?></td>
          <td><?= Http::e((string) ($p['email'] ?? '')) ?></td>
          <td><?= Http::e((string) (($p['phone'] ?? '') !== '' ? $p['phone'] : '—')) ?></td>
          <td>invited</td>
          <td>
            <span class="pill status-<?= Http::e((string) ($p['circle_status_key'] ?? '')) ?>"><?= Http::e((string) ($p['circle_status'] ?? '')) ?></span>
            <div class="join-url"><a href="<?= Http::e($join) ?>"><?= Http::e($join) ?></a></div>
            <form class="resend-form" method="post" action="/circle/resend">
              <input type="hidden" name="invite_id" value="<?= (int) ($p['id'] ?? 0) ?>" />
              <button class="btn ghost" type="submit">Resend invite</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <form class="panel" method="post">
    <h2>Invite someone</h2>
    <label>Their name</label>
    <input name="name" />
    <label>Email</label>
    <input name="email" type="email" required />
    <label>Mobile (optional)</label>
    <input name="phone" type="tel" inputmode="tel" placeholder="(555) 010-1234" autocomplete="tel" />
    <p><button class="btn wide" type="submit">Send invite</button></p>
    <p class="disclaimer">We email a join link when mail is set up<?php if (!empty($sms_enabled)): ?>, and text it when you add a mobile number<?php endif; ?>. Also copy it from the status table and share it in a call you already trust — not inside a suspicious thread. Reply STOP on texts to opt out.</p>
  </form>
</div>
<?php if ($alerts): ?>
<div class="panel" style="margin-top:16px">
  <h2>Call-me alerts</h2>
  <?php foreach ($alerts as $a): ?>
    <p><?= Http::e($a['created_at']) ?> — <?= Http::e($a['message']) ?></p>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php View::appClose(); ?>
