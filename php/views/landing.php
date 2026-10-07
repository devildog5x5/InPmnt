<!DOCTYPE html>
<html lang="en" data-theme="<?= Http::e(Http::theme()) ?>" style="color-scheme: <?= Http::theme() === 'dark' ? 'dark' : 'light' ?>">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="<?= Http::e(Http::csrfToken()) ?>" />
  <?php
    $meta_title = 'ReceiptGrid Invoicing — reminders for small businesses';
    $meta_description = 'Get paid without chasing clients. ReceiptGrid Invoicing sends invoice reminders for contractors and other service businesses. 14-day trial, no credit card. Then $5.00 a month or $50 a year.';
    $faqItems = require __DIR__ . '/_faq_data.php';
    $base = rtrim(Http::canonicalBase(), '/');
    $json_ld = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'Organization',
                'name' => 'ReceiptGrid',
                'url' => $base . '/',
                'email' => 'support@invcpay.com',
                'logo' => $base . '/static/img/inpmnt-icon.png',
            ],
            [
                '@type' => 'SoftwareApplication',
                'name' => 'ReceiptGrid Invoicing',
                'applicationCategory' => 'BusinessApplication',
                'operatingSystem' => 'Web',
                'url' => $base . '/',
                'description' => 'Invoice reminders for small service businesses. 14-day trial, no credit card required. Then $5.00 a month or $50 a year.',
                'offers' => [
                    ['@type' => 'Offer', 'name' => 'Monthly', 'price' => '5.00', 'priceCurrency' => 'USD', 'description' => 'Per month after a 14-day trial. Unlimited open invoices, email and SMS reminders.'],
                    ['@type' => 'Offer', 'name' => 'Yearly', 'price' => '50.00', 'priceCurrency' => 'USD', 'description' => 'Per year. Same features as monthly. $10 less than twelve months at $5.00.'],
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

  <header class="landing-hero">
    <div>
      <h1>Get paid without chasing the invoice.</h1>
      <p class="sub">ReceiptGrid Invoicing sends the reminder for you. Polite, on a schedule, and stopped when the invoice is paid.</p>
      <p class="audience">For contractors, consultants, photographers, landscapers, and other service businesses.</p>
      <p class="hero-price">14-day trial, no credit card. Then <strong>$5.00/month</strong> or <strong>$50/year</strong>.</p>
      <div class="hero-cta">
        <a class="btn" href="/signup">Start free trial</a>
        <a class="text-link" href="/pricing">See what is included</a>
      </div>
      <p class="hero-note">No card to start. Cancel anytime after you subscribe.</p>
    </div>
    <div class="hero-visual">
      <div class="browser-frame">
        <div class="browser-chrome" aria-hidden="true">
          <span></span><span></span><span></span>
          <div class="browser-url">app.invcpay.com</div>
        </div>
        <p class="hero-sample">Example workspace. The amounts are a sample, not live results.</p>
        <div class="dash-shot">
          <div class="dash-top">
            <strong>Outstanding</strong>
            <span class="dash-toast">Payment received · Harbor Studio · $1,240</span>
          </div>
          <div class="dash-kpis">
            <div>
              <em>Total outstanding</em>
              <b>$18,460</b>
            </div>
            <div>
              <em>Overdue invoices</em>
              <b>7</b>
            </div>
          </div>
          <div class="dash-aging" aria-label="Aging buckets">
            <div><span>1–30</span><i style="width:72%"></i><small>$6,200</small></div>
            <div><span>31–60</span><i style="width:48%"></i><small>$4,180</small></div>
            <div><span>60+</span><i style="width:34%"></i><small>$8,080</small></div>
          </div>
          <ul class="dash-reminders">
            <li><span>Tomorrow</span> Northside Landscaping · invoice 1042</li>
            <li><span>Fri</span> Keller Consulting · final notice</li>
            <li><span>Mon</span> Bright Frame Photo · due-date reminder</li>
          </ul>
        </div>
      </div>
    </div>
  </header>

  <section class="trust-strip" aria-label="Security and trial">
    <ul>
      <li>14-day trial, no credit card</li>
      <li>$5.00/month or $50/year</li>
      <li>Stripe checkout. We do not store your customers’ cards</li>
      <li>Cancel anytime</li>
    </ul>
  </section>

  <section class="landing-section" id="how">
    <h2>How it works</h2>
    <p class="sub">Three steps from an unpaid invoice to a recorded payment.</p>
    <div class="steps">
      <article class="step">
        <div class="step-art" aria-hidden="true">
          <div class="mini-row"></div>
          <div class="mini-row short"></div>
          <div class="mini-row"></div>
        </div>
        <h3>1. Add your unpaid invoices</h3>
        <p>Type in the unpaid invoices you already have.</p>
      </article>
      <article class="step">
        <div class="step-art" aria-hidden="true">
          <div class="mini-cal"><b>Due</b><b class="on">+3</b><b>+7</b></div>
        </div>
        <h3>2. Choose your reminder schedule</h3>
        <p>Pick the days. Email is in the trial. SMS is included once you subscribe.</p>
      </article>
      <article class="step">
        <div class="step-art" aria-hidden="true">
          <div class="mini-paid">Paid · reminders stopped</div>
        </div>
        <h3>3. Get paid and move on</h3>
        <p>Record the payment and future reminders stop automatically.</p>
      </article>
    </div>
  </section>

  <section class="landing-section" id="features">
    <h2>Everything you need to collect faster</h2>
    <p class="sub">A focused tool — not another bloated accounting suite.</p>
    <div class="feature-grid">
      <article class="feature">
        <div class="feat-icon teal" aria-hidden="true">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/><path d="M12 8v5l3 2"/></svg>
        </div>
        <h3>Smart reminder schedules</h3>
        <p>Auto-queue friendly nudges before due date, on the day, and after — email or SMS templates you control.</p>
      </article>
      <article class="feature">
        <div class="feat-icon blue" aria-hidden="true">
          <svg viewBox="0 0 24 24"><path d="M4 19V10M10 19V5M16 19v-7M22 19H2"/></svg>
        </div>
        <h3>Aging &amp; overdue dashboard</h3>
        <p>See open balances, 1–30 / 31–60 / 60+ aging, and which invoices need a final notice today.</p>
      </article>
      <article class="feature">
        <div class="feat-icon violet" aria-hidden="true">
          <svg viewBox="0 0 24 24"><path d="M7 3h8l4 4v14H7z"/><path d="M15 3v5h5M9 13h6M9 17h4"/></svg>
        </div>
        <h3>One-tap final notice</h3>
        <p>Escalate politely when someone’s been quiet too long. Log every send for your records.</p>
      </article>
      <article class="feature">
        <div class="feat-icon teal" aria-hidden="true">
          <svg viewBox="0 0 24 24"><circle cx="9" cy="9" r="3"/><circle cx="16" cy="10" r="2.2"/><path d="M4 19c.6-2.6 2.6-4 5-4s4.4 1.4 5 4M14 15.2c1.4-.5 2.8-.3 4 .6.8.7 1.3 1.7 1.5 3.2"/></svg>
        </div>
        <h3>Clients &amp; open balances</h3>
        <p>Keep contact details, notes, and who still owes you — without leaving the app.</p>
      </article>
      <article class="feature">
        <div class="feat-icon blue" aria-hidden="true">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/><path d="M8.5 12.2l2.4 2.4 4.6-5"/></svg>
        </div>
        <h3>Payment tracking</h3>
        <p>Record partial or full payments and watch reminders cancel themselves when you’re paid.</p>
      </article>
      <article class="feature">
        <div class="feat-icon violet" aria-hidden="true">
          <svg viewBox="0 0 24 24"><path d="M12 3v18M16.5 7.5c-.8-1.2-2.2-2-4.2-2-2.6 0-4.3 1.4-4.3 3.3 0 4.6 8.5 2.4 8.5 6.4 0 2-1.8 3.5-4.5 3.5-2.2 0-3.8-.9-4.6-2.3"/><path d="M16 5l3-2v5"/></svg>
        </div>
        <h3>Pays for itself</h3>
        <p>Recover one late invoice and the subscription is covered. Built by Robert Foster.</p>
      </article>
    </div>
  </section>

  <section class="landing-section" id="pricing">
    <h2>Simple pricing</h2>
    <p class="sub">14 days free. No credit card. You can change plans later from Billing.</p>
    <?php require __DIR__ . '/_pricing_cards.php'; ?>
  </section>

  <section class="landing-section" id="faq">
    <h2>Questions before you start</h2>
    <?php require __DIR__ . '/_faq_list.php'; ?>
    <p><a class="text-link" href="/faq">Read the full FAQ</a></p>
  </section>

  <footer class="landing-footer">
    <div class="footer-brand">
      <div class="brand">
        <div class="brand-logo img">
          <picture>
            <source srcset="/static/img/inpmnt-icon.webp" type="image/webp" />
            <img src="/static/img/inpmnt-icon.png" width="42" height="42" alt="ReceiptGrid logo" loading="lazy" decoding="async" />
          </picture>
        </div>
        <div class="brand-copy">
          <div class="brand-mark">ReceiptGrid</div>
          <div class="brand-sub">Invoicing</div>
        </div>
      </div>
      <p>ReceiptGrid Invoicing for service businesses. This site stays at invcpay.com.</p>
    </div>
    <div class="footer-col">
      <h2>Product</h2>
      <a href="/pricing">Pricing</a>
      <a href="#how">How it works</a>
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
      <p class="site-version" id="site-version">ReceiptGrid v<?= Http::e(Http::VERSION) ?></p>
      <p>© 2026 Robert Foster</p>
      <p>Support: <a href="mailto:support@invcpay.com">support@invcpay.com</a></p>
      <?php if (!empty($show_demo_login)): ?>
      <p>Local demo: demouser@inpmnt.app / Demo (SHOW_DEMO_LOGIN=1)</p>
      <?php endif; ?>
    </div>
  </footer>
<?php require __DIR__ . '/_help_chat.php'; ?>
</body>
</html>
