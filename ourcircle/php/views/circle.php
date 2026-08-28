<?php
/** @var array $members */
/** @var array $pending */
/** @var array $alerts */
View::appOpen(get_defined_vars());
?>
<div class="grid-2">
  <div class="panel">
    <h2>People in this circle</h2>
    <p class="muted">Up to five people on the family plan. Invite the person who will actually answer the phone.</p>
    <table class="table">
      <thead><tr><th>Name</th><th>Email</th><th>Role</th></tr></thead>
      <tbody>
        <?php foreach ($members as $m): ?>
        <tr><td><?= Http::e($m['name']) ?></td><td><?= Http::e($m['email']) ?></td><td><?= Http::e($m['role']) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php if ($pending): ?>
    <h3>Waiting to join</h3>
    <ul class="list">
      <?php foreach ($pending as $p): ?>
      <li><?= Http::e($p['email']) ?> · join link token <?= Http::e($p['token']) ?></li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </div>
  <form class="panel" method="post">
    <h2>Invite someone</h2>
    <label>Their name</label>
    <input name="name" />
    <label>Email</label>
    <input name="email" type="email" required />
    <p><button class="btn wide" type="submit">Create invite link</button></p>
    <p class="disclaimer">Share the join link in a call or a message you already trust — not inside a suspicious thread.</p>
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
