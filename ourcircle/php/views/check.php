<?php
/** @var array $item */
/** @var array $report */
/** @var array $reviews */
View::appOpen(get_defined_vars());
$level = Http::e((string) ($report['level'] ?? 'unknown'));
?>
<div class="check-card panel">
  <div class="risk <?= $level ?>"><?= Http::e((string) ($report['title'] ?? '')) ?></div>
  <p><?= Http::e((string) ($report['explanation'] ?? '')) ?></p>
  <form method="post" action="/checks/<?= (int) $item['id'] ?>/alert">
    <button class="btn danger wide" type="submit">Please call me before I pay</button>
  </form>
  <form method="post" action="/checks/<?= (int) $item['id'] ?>/review">
    <p><button class="btn gold wide" type="submit">Send to family circle</button></p>
  </form>
  <p class="disclaimer"><?= Http::e($guidance ?? Analyze::GUIDANCE) ?></p>
  <?php if (!empty($item['screenshot'])): ?>
    <p><img src="/uploads/<?= Http::e((string) $item['screenshot']) ?>" alt="Uploaded screenshot" style="max-width:420px;border-radius:12px" /></p>
  <?php endif; ?>
  <?php if (!empty($item['raw_text'])): ?><p><strong>What you pasted</strong><br /><?= Http::e((string) $item['raw_text']) ?></p><?php endif; ?>
  <?php if (!empty($item['phone'])): ?><p><strong>Number:</strong> <?= Http::e((string) $item['phone']) ?></p><?php endif; ?>
  <?php if (!empty($item['url'])): ?><p><strong>Website:</strong> <?= Http::e((string) $item['url']) ?></p><?php endif; ?>
</div>
<div class="grid-2" style="margin-top:16px">
  <div class="panel">
    <h2>Warning signs</h2>
    <ul class="list"><?php foreach ($report['warning_signs'] ?? [] as $s): ?><li><?= Http::e((string) $s) ?></li><?php endforeach; ?></ul>
    <h3>Number, domain, or known patterns</h3>
    <?php if (!empty($report['matches'])): ?>
    <ul class="list"><?php foreach ($report['matches'] as $m): ?><li><?= Http::e((string) $m) ?></li><?php endforeach; ?></ul>
    <?php else: ?>
    <p class="muted">Nothing matched your trusted list or a well-known official domain.</p>
    <?php endif; ?>
  </div>
  <div class="panel">
    <h2>What to do</h2>
    <ol class="list"><?php foreach ($report['next_steps'] ?? [] as $s): ?><li><?= Http::e((string) $s) ?></li><?php endforeach; ?></ol>
  </div>
</div>
<div class="panel" style="margin-top:16px">
  <h2>Circle notes</h2>
  <?php if ($reviews): ?>
    <?php foreach ($reviews as $r): ?>
      <p><span class="pill"><?= Http::e((string) $r['status']) ?></span> <?= Http::e((string) $r['comment']) ?></p>
    <?php endforeach; ?>
  <?php else: ?>
    <p class="muted">No one has left a note yet.</p>
  <?php endif; ?>
  <form method="post" action="/checks/<?= (int) $item['id'] ?>/review/reply">
    <label>Leave a note for the circle</label>
    <textarea name="reply" placeholder="I looked — keep pausing."></textarea>
    <p><button class="btn" type="submit">Add note</button></p>
  </form>
</div>
<p><a href="/report">If money or passwords already went out → Report & recover</a></p>
<?php View::appClose(); ?>
