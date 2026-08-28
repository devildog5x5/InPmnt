<?php
/** @var string $title */
/** @var string $site_home */
/** @var string $core_rule */
/** @var string $disclaimer */
/** @var array $plans */
/** @var array $flashes */
View::start($title, $site_home, 'index,follow', '/');
?>
<div class="wrap">
  <header class="site-header">
    <?= View::brand($site_home) ?>
    <nav class="nav">
      <a class="btn ghost" href="/offers">Founding offers</a>
      <a class="btn ghost" href="/login">Sign in</a>
      <a class="btn" href="/signup">Start a circle</a>
    </nav>
  </header>
  <?= View::flashesHtml($flashes) ?>
  <section class="hero">
    <div>
      <p class="core-rule"><?= Http::e($core_rule) ?></p>
      <h1>Not another AI scam detector. A circle that helps you pause.</h1>
      <p class="lede">Forward a sketchy text, upload a screenshot, or paste a number or website. We show warning signs in plain language — then you ask someone you trust before you send a dime.</p>
      <p>
        <a class="btn" href="/signup">Create your family circle</a>
        <a class="btn gold" href="/signup">Founding year $49</a>
      </p>
      <p class="disclaimer"><?= Http::e($disclaimer) ?></p>
    </div>
    <div class="hero-card">
      <a href="<?= Http::e($site_home) ?>" title="Family Shield Pro">
        <img src="/static/img/logo-lockup.png" alt="Family Shield Pro" />
      </a>
    </div>
  </section>
  <h2>What you can do</h2>
  <div class="grid-3">
    <div class="panel step"><strong>1. Bring the request in</strong>Paste the email or text, upload a screenshot, or enter a phone number, website, offer, or payment ask.</div>
    <div class="panel step"><strong>2. Read the warning signs</strong>See why it might be a scam, whether a number or site resembles a known trick, and what to do next — never a “this is safe” stamp.</div>
    <div class="panel step"><strong>3. Involve your circle</strong>Ask a family member to look. Tap “Please call me before I pay” when it is urgent.</div>
  </div>
  <div class="grid-2" style="margin-top:18px">
    <div class="panel">
      <h3>Protected trusted list</h3>
      <p>Save the real numbers for banks, doctors, insurers, utilities, and family. Checks compare incoming numbers and websites to that list — not to a stranger in the message.</p>
    </div>
    <div class="panel">
      <h3>If something already went wrong</h3>
      <p>Get calm instructions to report fraud, freeze cards, and tell the people who can actually stop a payment. Speed matters more than shame.</p>
    </div>
  </div>
  <h2 style="margin-top:36px">Plans</h2>
  <div class="plans">
    <?php foreach ($plans as $p): ?>
    <div class="panel">
      <h3><?= Http::e($p['name']) ?></h3>
      <p><strong><?= Http::e($p['price']) ?></strong></p>
      <p class="muted"><?= Http::e($p['detail']) ?></p>
    </div>
    <?php endforeach; ?>
  </div>
  <p>Credit unions and insurers: per-member partnership pricing. Churches, senior centers, and veterans groups: $299–$999/year.</p>
  <footer class="footer">
    OurCircle is built for families — including parents, adult children, and grandparents — who want a second set of eyes. We do not guarantee that a request is legitimate.
    <span class="muted"> · <?= Http::e(Product::label()) ?> · Hostinger PHP · not InPmnt
     · <a href="<?= Http::e($site_home) ?>/robots.txt">robots.txt</a> · <a href="<?= Http::e($site_home) ?>/sitemap.xml">sitemap.xml</a></span>
  </footer>
</div>
</body></html>
