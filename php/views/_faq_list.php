<dl class="faq-list">
  <?php foreach ($faqItems as $item): ?>
    <dt><?= Http::e($item['q']) ?></dt>
    <dd><?= Http::e($item['a']) ?></dd>
  <?php endforeach; ?>
</dl>
