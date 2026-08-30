<?php
/** @var array $members */
/** @var array $pending */
/** @var array $alerts */
/** @var string $site_home */
/** @var bool $sms_enabled */
View::appOpen(get_defined_vars());
?>
<div class="panel">
  <h2>People in this circle</h2>
  <p class="muted">Up to five people on the family plan. Invite the person who will actually answer the phone. Everyone you invited stays in this list until they join or you replace them.</p>
  <p class="status-legend" aria-label="Circle status">
    <span class="pill status-invited">Invited</span>
    <span class="status-arrow" aria-hidden="true">→</span>
    <span class="pill status-sent">Invite sent</span>
    <span class="status-arrow" aria-hidden="true">→</span>
    <span class="pill status-accepted">Invite Accepted</span>
    <span class="status-arrow" aria-hidden="true">→</span>
    <span class="pill status-access">User Accesses the Circle</span>
  </p>
  <form id="circle-resend" method="post" action="/circle/resend"></form>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Name</th><th>Email</th><th>Mobile</th><th>Role</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($members as $m): ?>
        <tr>
          <td><?= Http::e((string) ($m['name'] ?? '')) ?></td>
          <td><?= Http::mailto((string) ($m['email'] ?? '')) ?></td>
          <td><?= Http::tel((string) ($m['phone'] ?? '')) ?></td>
          <td><?= Http::e((string) ($m['role'] ?? '')) ?></td>
          <td><span class="pill status-<?= Http::e((string) ($m['circle_status_key'] ?? '')) ?>"><?= Http::e((string) ($m['circle_status'] ?? '')) ?></span></td>
        </tr>
        <?php endforeach; ?>
        <?php foreach ($pending as $p): ?>
        <?php $join = rtrim((string) ($site_home ?? ''), '/') . '/join/' . rawurlencode((string) ($p['token'] ?? '')); ?>
        <tr>
          <td><?= Http::e((string) (($p['name'] ?? '') !== '' ? $p['name'] : '—')) ?></td>
          <td><?= Http::mailto((string) ($p['email'] ?? '')) ?></td>
          <td><?= Http::tel((string) ($p['phone'] ?? '')) ?></td>
          <td>invited</td>
          <td>
            <span class="pill status-<?= Http::e((string) ($p['circle_status_key'] ?? '')) ?>"><?= Http::e((string) ($p['circle_status'] ?? '')) ?></span>
            <div class="join-url"><a href="<?= Http::e($join) ?>"><?= Http::e($join) ?></a></div>
            <button class="btn ghost resend-btn" type="submit" form="circle-resend" name="invite_id" value="<?= (int) ($p['id'] ?? 0) ?>">Resend invite</button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<form class="panel" method="post" style="margin-top:16px">
  <h2>Invite someone</h2>
  <label>Their name</label>
  <input name="name" autocomplete="name" />
  <label>Email</label>
  <input name="email" type="email" required autocomplete="off" />
  <label>Mobile (optional)</label>
  <input name="phone" type="tel" inputmode="tel" placeholder="(555) 010-1234" autocomplete="off" />
  <p><button class="btn wide" type="submit">Send invite</button></p>
  <p class="disclaimer">We email a tap-to-open join button when mail is set up<?php if (!empty($sms_enabled)): ?>, and text the join URL when you add a mobile number<?php endif; ?>. You can also tap the join link in the status table and share it in a call you already trust — not inside a suspicious thread. Reply STOP on texts to opt out.</p>
</form>
<?php if ($alerts): ?>
<div class="panel" style="margin-top:16px">
  <h2>Call-me alerts</h2>
  <?php foreach ($alerts as $a): ?>
    <p><?= Http::e($a['created_at']) ?> — <?= Http::e($a['message']) ?><?php if (!empty($a['check_id'])): ?> <a href="/checks/<?= (int) $a['check_id'] ?>">Open this check</a><?php endif; ?></p>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php View::appClose(); ?>
