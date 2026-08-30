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
    <p><?= Http::e((string) $alerts[0]['message']) ?></p>
  </div>
<?php endif; ?>
<div class="grid-2">
  <form class="panel" method="post" action="/check" enctype="multipart/form-data">
    <h2>What landed in your lap?</h2>
    <p class="muted">Forward a suspicious email or text by pasting it. Add a screenshot if that is all you have.</p>
    <label>Message, email, or offer</label>
    <textarea name="text" placeholder="Paste the whole thing. Do not tap links inside it."></textarea>
    <label>Phone number</label>
    <input name="phone" placeholder="(555) 010-1234" />
    <label>Website</label>
    <input name="url" placeholder="https://…" />
    <label>Screenshot</label>
    <input name="screenshot" type="file" accept="image/*" />
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
          · <a href="<?= Http::e($join) ?>">Join link</a></li>
        <?php endforeach; ?>
      </ul>
      <p><a class="btn ghost" href="/circle">Invite family</a></p>
      <h3>Trusted list</h3>
      <p><?= count($trusted) ?> saved banks, doctors, utilities, and family numbers.</p>
      <ul class="list">
        <?php foreach ($trusted as $t): ?>
        <li><?= Http::e((string) ($t['name'] ?? '')) ?><?php if (!empty($t['phone'])): ?> · <?= Http::e((string) $t['phone']) ?><?php endif; ?></li>
        <?php endforeach; ?>
      </ul>
      <p><a class="btn ghost" href="/trusted">Open list</a></p>
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
