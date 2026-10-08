<!DOCTYPE html>
<html lang="en" data-theme="<?= Http::e(Http::theme()) ?>" style="color-scheme: <?= Http::theme() === 'dark' ? 'dark' : 'light' ?>">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="<?= Http::e(Http::csrfToken()) ?>" />
  <?php
    $meta_title = 'InvoicePay — get paid without chasing invoices';
    $meta_description = 'InvoicePay queues polite invoice reminders for service businesses. 14-day trial, no card. Then $4.99 a month or $49.99 a year. Email sends when mail is connected.';
    $faqItems = require __DIR__ . '/_faq_data.php';
    $base = rtrim(Http::canonicalBase(), '/');
    $json_ld = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'Organization',
                'name' => 'InvoicePay',
                'url' => $base . '/',
                'email' => 'support@invcpay.com',
                'logo' => $base . '/static/img/inpmnt-icon.png',
            ],
            [
                '@type' => 'SoftwareApplication',
                'name' => 'InvoicePay',
                'applicationCategory' => 'BusinessApplication',
                'operatingSystem' => 'Web',
                'url' => $base . '/',
                'description' => 'Invoice reminders, an overdue dashboard, and a reminder queue for small service businesses. 14-day trial, then $4.99 a month or $49.99 a year.',
                'offers' => [
                    ['@type' => 'Offer', 'name' => 'Monthly', 'price' => '4.99', 'priceCurrency' => 'USD', 'description' => 'Per month after a 14-day trial. Unlimited open invoices and email reminders. SMS templates included; carrier delivery is not connected yet.'],
                    ['@type' => 'Offer', 'name' => 'Yearly', 'price' => '49.99', 'priceCurrency' => 'USD', 'description' => 'Per year. Same features as monthly. $9.89 less than twelve months at $4.99.'],
                ],
            ],
            [
                '@type' => 'FAQPage',
                'mainEntity' => array_map(static function (array $item): array {
                    return [
                        '@type' => 'Question',
                        'name' => $item['q'],
                        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['a']],
                    ];
                }, $faqItems),
            ],
        ],
    ];
    require __DIR__ . '/_meta.php';
  ?>
  <link rel="icon" type="image/png" href="/static/img/inpmnt-icon.png" />
  <link rel="stylesheet" href="/static/css/app.css?v=<?= rawurlencode(Http::VERSION) ?>" />
</head>
<body class="landing">
<?php require __DIR__ . '/_nav.php'; ?>

  <div class="hero-band">
  <header class="landing-hero">
    <div>
      <p class="kicker">Invoice reminders for service businesses</p>
      <h1>Get paid without chasing the invoice.</h1>
      <p class="sub">Open invoices, reminder dates, and the message stay in one place. Email sends when mail is connected. Mark it paid and the queue stops.</p>
      <p class="hero-price">14 days free. Then <strong>$4.99 a month</strong> or <strong>$49.99 a year</strong>.</p>
      <div class="hero-cta">
        <a class="btn" href="/signup?plan=">Start 14-day free trial</a>
        <a class="btn secondary" href="/signup?plan=monthly">Subscribe — $4.99/mo</a>
      </div>
      <p class="hero-note">No card to start. Cancel anytime. Stripe handles the payment.</p>
    </div>
    <div class="hero-visual">
      <p class="float-paid">Paid ✓ · $1,240</p>
      <div class="browser-frame">
        <div class="browser-chrome" aria-hidden="true">
          <span></span><span></span><span></span>
          <div class="browser-url">invcpay.com/app</div>
        </div>
        <p class="hero-sample">Sample workspace. Illustration, not live results.</p>
        <div class="dash-shot">
          <div class="dash-top">
            <strong>Outstanding</strong>
          </div>
          <div class="dash-kpis">
            <div>
              <em>Open balance</em>
              <b>$18,460</b>
            </div>
            <div>
              <em>Overdue</em>
              <b>7</b>
            </div>
          </div>
          <div class="dash-aging" aria-label="Sample aging buckets">
            <div><span>1–30</span><i style="width:72%"></i><small>$6,200</small></div>
            <div><span>31–60</span><i style="width:48%"></i><small>$4,180</small></div>
            <div><span>60+</span><i style="width:34%"></i><small>$8,080</small></div>
          </div>
          <ul class="dash-reminders">
            <li><span>Due</span> Northside Landscaping · invoice 1042</li>
            <li><span>Queue</span> Keller Consulting · final notice</li>
            <li><span>Paid</span> Bright Frame Photo · reminders stopped</li>
          </ul>
        </div>
      </div>
    </div>
  </header>

  <section class="trust-strip" aria-label="Trial and checkout">
    <ul>
      <li>14-day trial, no credit card</li>
      <li>$4.99/month or $49.99/year</li>
      <li>Stripe checkout. Card numbers stay with Stripe</li>
      <li>Cancel anytime from Billing</li>
    </ul>
  </section>
  </div>

  <section class="landing-section" id="problem">
    <h2>Late invoices cost the evening first</h2>
    <p class="sub">You already know who owes you. The work is remembering to ask.</p>
    <ul class="problem-list">
      <li class="lift">
        <div class="feat-icon teal" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/><path d="M12 8v5l3 2"/></svg></div>
        <h3>Evenings spent chasing</h3>
        <p>The same follow-up, rewritten, while you try to remember if you already asked.</p>
      </li>
      <li class="lift">
        <div class="feat-icon blue" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 5h14v14H5z"/><path d="M8 9h8M8 13h5"/></svg></div>
        <h3>A note that sounds like you</h3>
        <p>Start from a nudge, a due-today note, an overdue follow-up, or a final notice. Edit any of them.</p>
      </li>
      <li class="lift">
        <div class="feat-icon violet" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 19V10M10 19V5M16 19v-7M22 19H2"/></svg></div>
        <h3>See which invoices went quiet</h3>
        <p>Open balance, sorted into current, 1–30, 31–60, and 60+ days.</p>
      </li>
    </ul>
  </section>

  <section class="band band-alt" id="how">
  <div class="landing-section">
    <h2>How it works</h2>
    <p class="sub">Four steps from a new invoice to a recorded payment.</p>
    <div class="steps">
      <article class="step-col">
        <div class="step-num">1</div>
        <div class="step lift">
          <h3>Add the client and invoice</h3>
          <p>Name, email, phone, amount, and due date.</p>
        </div>
      </article>
      <article class="step-col">
        <div class="step-num">2</div>
        <div class="step lift">
          <h3>Set the reminder days</h3>
          <p>Starts at 3 days before due, the due date, then 3, 7, and 14 days after. Change the offsets anytime.</p>
        </div>
      </article>
      <article class="step-col">
        <div class="step-num">3</div>
        <div class="step lift">
          <h3>Send the reminder</h3>
          <p>Email sends when Resend or SMTP is connected. Paid plans include SMS templates. Those texts stay in the reminder log until a carrier is connected.</p>
        </div>
      </article>
      <article class="step-col">
        <div class="step-num">4</div>
        <div class="step lift">
          <h3>Mark it paid</h3>
          <p>Record a partial or full payment. Queued reminders stop when the balance is gone.</p>
        </div>
      </article>
    </div>
  </div>
  </section>

  <section class="landing-section" id="features">
    <h2>Everything in the app</h2>
    <p class="sub">The screens you use to get an invoice paid.</p>
    <div class="feature-grid">
      <article class="feature">
        <div class="feat-icon teal" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 19V10M10 19V5M16 19v-7M22 19H2"/></svg></div>
        <h3>Dashboard</h3>
        <p>Open balance, overdue count, and aging: current, 1–30, 31–60, and 60+ days.</p>
      </article>
      <article class="feature">
        <div class="feat-icon blue" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 22a2.2 2.2 0 0 0 2.1-1.6H9.9A2.2 2.2 0 0 0 12 22zm7-6V11a7 7 0 1 0-14 0v5l-2 2h18l-2-2z"/></svg></div>
        <h3>Reminder queue</h3>
        <p>Due and pending reminders in one list, with the client and the invoice.</p>
      </article>
      <article class="feature">
        <div class="feat-icon violet" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M8 3h8l4 4v14H4V3h4zm0 0v4h8M8 13h8M8 17h5"/></svg></div>
        <h3>Invoices</h3>
        <p>Draft, sent, partial, overdue, and paid. The balance stays visible.</p>
      </article>
      <article class="feature">
        <div class="feat-icon teal" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z"/></svg></div>
        <h3>Clients</h3>
        <p>Name, email, phone, and the invoices that still have a balance.</p>
      </article>
      <article class="feature">
        <div class="feat-icon blue" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M8 4h9l3 3v13H8V4zm0 0H5v16h3M11 12h6M11 16h4"/></svg></div>
        <h3>Templates</h3>
        <p>Friendly nudge, due today, overdue, final notice, and a short SMS. Merge fields fill the name, amount, and date.</p>
      </article>
      <article class="feature">
        <div class="feat-icon violet" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 6h16v12H4z"/><path d="m4 7 8 6 8-6"/></svg></div>
        <h3>Email reminders</h3>
        <p>Included in the trial. They send when Resend or SMTP is connected, and each send is logged.</p>
      </article>
      <article class="feature">
        <div class="feat-icon teal" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M7 3h8l4 4v14H7z"/><path d="M15 3v5h5M9 13h6M9 17h4"/></svg></div>
        <h3>Final notice</h3>
        <p>One action fills the final-notice template and emails it when mail is connected.</p>
      </article>
      <article class="feature">
        <div class="feat-icon blue" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 16.5V7.5A3.5 3.5 0 0 1 8.5 4h7A3.5 3.5 0 0 1 19 7.5v5A3.5 3.5 0 0 1 15.5 16H9l-4 3.5z"/></svg></div>
        <h3>Help chat</h3>
        <p>Answers from the facts on this site. You can also email support@invcpay.com.</p>
      </article>
      <article class="feature">
        <div class="feat-icon violet" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="6" width="18" height="12" rx="2"/><path d="M3 10h18"/></svg></div>
        <h3>Billing</h3>
        <p>Subscribe monthly or yearly. Stripe Checkout and the customer portal handle the card.</p>
      </article>
    </div>
  </section>

  <section class="band band-alt" id="value">
  <div class="landing-section">
    <h2>Why $4.99 is a small price</h2>
    <p class="sub">One invoice paid a week sooner covers the subscription for years.</p>
    <div class="stat-row">
      <article class="stat-block lead">
        <strong>16¢</strong>
        <span>a day</span>
        <p>$4.99 a month is $4.99 × 12 ÷ 365.</p>
      </article>
      <article class="stat-block">
        <strong>$9.89</strong>
        <span>off the yearly plan</span>
        <p>$49.99 instead of twelve payments of $4.99.</p>
      </article>
      <article class="stat-block">
        <strong>3+ years</strong>
        <span>from one $200 invoice</span>
        <p>Paid a week sooner, that invoice covers $4.99 × 36 ($179.64).</p>
      </article>
    </div>
    <div class="value-grid">
      <article class="value-card lift">
        <div class="feat-icon teal" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/><path d="M12 8v5l3 2"/></svg></div>
        <h3>Less time chasing</h3>
        <p>The reminder is already written and dated. You send it instead of starting from a blank email.</p>
      </article>
      <article class="value-card lift">
        <div class="feat-icon blue" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 8h16M4 16h16M8 4v16M16 4v16"/></svg></div>
        <h3>Smaller than a full suite</h3>
        <p>Field-service suites often bundle dispatch, inventory, and payroll and cost much more a month. InvoicePay is the open invoices and the reminders.</p>
      </article>
      <article class="value-card lift">
        <div class="feat-icon violet" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/><path d="M8.5 12.2 11 14.7 15.5 9.5"/></svg></div>
        <h3>Same tools on both plans</h3>
        <p>Monthly and yearly both remove the 40-invoice trial cap and include SMS templates. Yearly is one payment of $49.99.</p>
      </article>
    </div>
    <form class="roi-panel" id="roi-calc">
      <div>
        <h3>Your late invoices, next to $4.99</h3>
        <div class="roi-fields">
          <label>Average invoice amount
            <input id="roi-amount" name="amount" type="number" min="0" step="1" value="200" inputmode="decimal" />
          </label>
          <label>Invoices late per month
            <input id="roi-count" name="count" type="number" min="0" step="1" value="1" inputmode="numeric" />
          </label>
        </div>
        <p class="roi-note">Arithmetic only. Not a promise that every reminder gets paid.</p>
      </div>
      <div class="roi-readout" aria-live="polite">
        <p class="roi-kicker">Cash a week sooner</p>
        <p class="roi-figure" id="roi-figure">$200</p>
        <p class="roi-result" id="roi-result"></p>
      </div>
    </form>
  </div>
  </section>

  <section class="landing-section" id="who">
    <h2>Who it is for</h2>
    <p class="sub">Any shop that invoices after the work and then waits.</p>
    <div class="audience-grid">
      <article class="audience-card lift"><h3>Plumbers and electricians</h3><p>The job is done. The invoice is still open.</p></article>
      <article class="audience-card lift"><h3>Landscapers and cleaners</h3><p>Repeat clients, and a reminder that sounds like you.</p></article>
      <article class="audience-card lift"><h3>Photographers and freelancers</h3><p>A handful of invoices. No accounting department.</p></article>
      <article class="audience-card lift"><h3>Contractors</h3><p>Same trial and prices on the <a href="/for-contractors">contractors page</a>.</p></article>
      <article class="audience-card lift"><h3>Consultants</h3><p>Track the balance, send the note, record the payment.</p></article>
      <article class="audience-card lift"><h3>Anyone with open invoices</h3><p>Type the amount and the due date. Run the queue.</p></article>
    </div>
  </section>

  <section class="band band-alt" id="pricing">
  <div class="landing-section">
    <h2>Pricing</h2>
    <p class="sub">14 days free. No card. Then $4.99 a month or $49.99 a year.</p>
    <?php require __DIR__ . '/_pricing_cards.php'; ?>
  </div>
  </section>

  <section class="landing-section" id="faq">
    <h2>Questions before you start</h2>
    <?php require __DIR__ . '/_faq_list.php'; ?>
    <p><a class="text-link" href="/faq">Read the FAQ on its own page</a></p>
  </section>

  <section class="cta-band" aria-labelledby="closing-cta">
    <div class="cta-inner">
      <div>
        <h2 id="closing-cta">Start with the invoices you already have</h2>
        <p>14 days free. No card. Then $4.99 a month or $49.99 a year.</p>
      </div>
      <div class="cta-actions">
        <a class="btn on-light" href="/signup?plan=">Start 14-day free trial</a>
        <a class="btn secondary" href="/signup?plan=yearly">Subscribe yearly — $49.99</a>
      </div>
    </div>
  </section>

  <footer class="landing-footer">
    <div class="footer-brand">
      <div class="brand">
        <div class="brand-logo img">
          <picture>
            <source srcset="/static/img/inpmnt-icon.webp" type="image/webp" />
            <img src="/static/img/inpmnt-icon.png" width="42" height="42" alt="InvoicePay logo" loading="lazy" decoding="async" />
          </picture>
        </div>
        <div class="brand-copy">
          <div class="brand-mark">InvoicePay</div>
          <div class="brand-sub">Get paid</div>
        </div>
      </div>
      <p>InvoicePay for service businesses. This site stays at invcpay.com.</p>
    </div>
    <div class="footer-col">
      <h2>Product</h2>
      <a href="/pricing">Pricing</a>
      <a href="#how">How it works</a>
      <a href="#features">Features</a>
      <a href="/for-contractors">Contractors</a>
      <a href="/faq">FAQ</a>
      <a href="/support">Help</a>
      <a href="/contact">Contact</a>
    </div>
    <div class="footer-col">
      <h2>Legal</h2>
      <a href="/privacy">Privacy</a>
      <a href="/terms">Terms</a>
      <a href="/security">Security</a>
      <a href="/refunds">Refund and cancellation</a>
    </div>
    <div class="footer-meta">
      <p class="page-version" id="site-version"><?php require __DIR__ . '/_version_line.php'; ?></p>
      <?php if (!empty($show_demo_login)): ?>
      <p class="page-version">Local demo: demouser@inpmnt.app / Demo</p>
      <?php endif; ?>
    </div>
  </footer>
  <script src="/static/js/landing.js?v=<?= rawurlencode(Http::VERSION) ?>" defer></script>
<?php require __DIR__ . '/_help_chat.php'; ?>
</body>
</html>
