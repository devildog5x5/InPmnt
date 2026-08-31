<?php
/** @var array $alerts */
/** @var array $members */
/** @var array $pending */
/** @var array $trusted */
/** @var array $checks */
View::appOpen(get_defined_vars());
?>
<?php if (!empty($alerts)): ?>
  <div class="flash error">
    <strong>Urgent circle alert</strong>
    <p><?= Http::e((string) $alerts[0]['message']) ?><?php if (!empty($alerts[0]['check_id'])): ?> <a href="/checks/<?= (int) $alerts[0]['check_id'] ?>">Open this check</a><?php endif; ?></p>
  </div>
<?php endif; ?>
<div class="grid-2">
  <form class="panel" method="post" action="/check" enctype="multipart/form-data">
    <h2>What landed in your lap?</h2>
    <p class="muted">Paste the message or drop a screenshot. We pull numbers and links out of the paste — you do not have to retype them.</p>
    <label>Message, email, or offer</label>
    <textarea name="text" placeholder="Paste the whole thing. Do not tap links inside it."></textarea>
    <label>Screenshot (if that is all you have)</label>
    <input name="screenshot" type="file" accept="image/*" />
    <details class="more">
      <summary>Add a number or website separately</summary>
      <label>Phone number</label>
      <input name="phone" placeholder="(555) 010-1234" />
      <label>Website</label>
      <input name="url" placeholder="https://…" />
    </details>
    <p><button class="btn wide" type="submit">Check this with OurCircle</button></p>
    <p class="disclaimer">This will not say the request is safe. It will help you pause. <?= Http::e($guidance ?? Analyze::GUIDANCE) ?></p>
  </form>
  <div>
    <div class="panel">
      <h3>Your circle</h3>
      <p><?= count($members) ?> of 5 people · <?= count($pending) ?> invite<?= count($pending) !== 1 ? 's' : '' ?> waiting</p>
      <ul class="list">
        <?php foreach ($members as $m): ?>
        <li><?= Http::e((string) ($m['name'] ?? '')) ?> · <?= Http::mailto((string) ($m['email'] ?? '')) ?></li>
        <?php endforeach; ?>
        <?php foreach ($pending as $p): ?>
        <?php $join = rtrim((string) ($site_home ?? ''), '/') . '/join/' . rawurlencode((string) ($p['token'] ?? '')); ?>
        <li><?= Http::e((string) (($p['name'] ?? '') !== '' ? $p['name'] : ($p['email'] ?? ''))) ?> · <?= Http::mailto((string) ($p['email'] ?? '')) ?> · invited
          · <a href="<?= Http::e($join) ?>">Join link</a>
          <button class="btn ghost resend-btn" type="submit" form="circle-resend" name="invite_id" value="<?= (int) ($p['id'] ?? 0) ?>">Resend</button></li>
        <?php endforeach; ?>
      </ul>
      <form id="circle-resend" method="post" action="/circle/resend">
        <input type="hidden" name="return" value="home" />
      </form>
      <form method="post" action="/circle">
        <input type="hidden" name="return" value="home" />
        <label>Invite by email</label>
        <input name="email" type="email" required placeholder="family@example.com" autocomplete="off" />
        <p><button class="btn wide" type="submit">Send invite</button></p>
      </form>
      <p><a href="/circle">Everyone in the circle</a></p>
      <h3>Trusted list</h3>
      <p><?= count($trusted) ?> saved banks, doctors, utilities, and family numbers.</p>
      <ul class="list">
        <?php foreach ($trusted as $t): ?>
        <li><?= Http::e((string) ($t['name'] ?? '')) ?><?php if (!empty($t['phone'])): ?> · <?= Http::tel((string) $t['phone']) ?><?php endif; ?></li>
        <?php endforeach; ?>
      </ul>
      <p><a class="btn ghost" href="/trusted"><?= count($trusted) ? 'Open list' : 'Add a real number' ?></a></p>
    </div>
    <div class="panel" style="margin-top:16px">
      <h3>Recent checks</h3>
      <?php if ($checks): ?>
      <ul class="list">
        <?php foreach ($checks as $c): ?>
        <?php $snippet = (string) ($c['raw_text'] ?: $c['phone'] ?: $c['url'] ?: 'Screenshot'); ?>
        <li><a href="/checks/<?= (int) $c['id'] ?>"><?= Http::e($c['risk']) ?> · <?= Http::e(substr($snippet, 0, 80)) ?></a></li>
        <?php endforeach; ?>
      </ul>
      <?php else: ?>
      <p class="muted">None yet. Paste the next odd message here before you reply.</p>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php View::appClose(); ?>
