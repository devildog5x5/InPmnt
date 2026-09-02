  <section class="landing-section" id="faq">
    <h2>Invoice reminders, explained</h2>
    <p class="sub">Straight answers for trades and freelancers who are tired of chasing unpaid invoices.</p>
    <div class="faq-list">
      <?php foreach (Seo::faqs() as $faq): ?>
      <details>
        <summary><?= Http::e($faq['q']) ?></summary>
        <p><?= Http::e($faq['a']) ?></p>
      </details>
      <?php endforeach; ?>
    </div>
  </section>
