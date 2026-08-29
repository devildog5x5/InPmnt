<?php
/** @var string $title */
/** @var string $site_home */
/** @var string $core_rule */
/** @var array $flashes */
View::start($title, $site_home, 'index,follow', '/offers');
?>
<div class="wrap">
  <header class="site-header">
    <?= View::brand($site_home, 'Offers', 'Family Shield Pro') ?>
    <a class="btn ghost" href="<?= Http::e($site_home) ?>">Family Shield Pro</a>
  </header>
  <p class="core-rule">We ask for a refundable reservation — not merely an opinion.</p>
  <?= View::flashesHtml($flashes) ?>
  <h1>Three offers. Money decides which we build next.</h1>
  <p class="lede">InPmnt can take the first small payments fastest. VendorReady can bring higher-value customers. OurCircle (Family Scam Shield) can help the most families. The paid test tells us which opportunity exists in practice.</p>
  <div class="grid-3">
    <div class="panel">
      <h2>InPmnt</h2>
      <p>Invoice chase + payment reminders for trades and freelancers. Already running.</p>
      <p><strong>Ask:</strong> five $99 annual customers in seven days.</p>
    </div>
    <div class="panel">
      <h2>VendorReady</h2>
      <p>Security questionnaires now; CMMC, NIST evidence, ISO 27001, HIPAA vendor packs later.</p>
      <p><strong>Ask:</strong> two $500 setup deposits.</p>
    </div>
    <div class="panel">
      <h2>OurCircle</h2>
      <p>Family circle, not a generic AI detector. Pause, warning signs, trusted list, “call me before I pay.”</p>
      <p><strong>Ask:</strong> ten Family yearly at $119.99 (or monthly at $14.99).</p>
      <p><a class="btn" href="/signup">Start a circle</a></p>
    </div>
  </div>
  <form class="panel" method="post" style="margin-top:24px">
    <h2>Refundable reservation</h2>
    <label>Which offer</label>
    <select name="product" required>
      <option value="ourcircle">OurCircle · Family yearly $119.99</option>
      <option value="inpmnt">InPmnt · $99 annual</option>
      <option value="vendorready">VendorReady · $500 setup deposit</option>
    </select>
    <label>Name</label>
    <input name="name" required />
    <label>Email</label>
    <input name="email" type="email" required />
    <label>Offer / amount you intend</label>
    <input name="offer" placeholder="$119.99 family year" />
    <label>Note</label>
    <textarea name="note" placeholder="Church group of 40, or CMMC Level 1 shop, etc."></textarea>
    <p><button class="btn wide" type="submit">Hold my spot (not a charge)</button></p>
    <p class="disclaimer">We store the reservation so we can count seven-day demand. Nothing is billed until you confirm.</p>
  </form>
</div>
</body></html>
