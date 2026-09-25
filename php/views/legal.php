<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= Http::e($title) ?> · InPmnt</title>
  <link rel="icon" type="image/png" href="/static/img/inpmnt-icon.png" />
  <link rel="stylesheet" href="/static/css/app.css?v=<?= rawurlencode(Http::VERSION) ?>" />
</head>
<body class="landing">
  <nav class="landing-nav">
    <a class="brand" href="/">
      <div class="brand-logo img" aria-hidden="true"><img src="/static/img/inpmnt-icon.png" alt="" /></div>
      <div class="brand-copy">
        <div class="brand-mark">InPmnt</div>
        <div class="brand-sub">by InvcPay</div>
      </div>
    </a>
    <div class="nav-actions">
      <a class="btn sm" href="/signup">Start free trial</a>
    </div>
  </nav>
  <article class="legal-page">
    <h1><?= Http::e($title) ?></h1>
    <?php if ($slug === 'privacy'): ?>
    <p>InPmnt by InvcPay stores the account, client, and invoice details you enter so reminders can be sent. We use that information to run the product, send the messages you schedule, and answer support requests.</p>
    <p>Paid checkout is handled by Stripe. InPmnt does not store your customers’ card numbers. Stripe’s own privacy terms apply to card data they process.</p>
    <p>To ask for a copy of your workspace data or to close an account, email <a href="mailto:<?= Http::e($support_email) ?>"><?= Http::e($support_email) ?></a>.</p>
    <?php elseif ($slug === 'terms'): ?>
    <p>InPmnt sends the invoice reminders you configure. You are responsible for the accuracy of invoice amounts, the permission to email or text your clients, and the content of those messages.</p>
    <p>The 14-day trial does not require a credit card. Starter is $10 per month, Pro is $20 per month, and Starter Annual is $100 per year for Starter features. You can change plans later from Billing. Cancel anytime; access continues through the period already paid.</p>
    <p>The service is provided by Robert Foster. Questions: <a href="mailto:<?= Http::e($support_email) ?>"><?= Http::e($support_email) ?></a>.</p>
    <?php elseif ($slug === 'contact'): ?>
    <p>Email <a href="mailto:<?= Http::e($support_email) ?>"><?= Http::e($support_email) ?></a> for sales, billing, or account questions.</p>
    <p>InPmnt is the product name. InvcPay is the public site and support domain.</p>
    <?php elseif ($slug === 'support'): ?>
    <p>Use the help button on any page, or email <a href="mailto:<?= Http::e($support_email) ?>"><?= Http::e($support_email) ?></a>.</p>
    <p>Include your account email and, for a billing question, the plan name (Starter, Pro, or Starter Annual).</p>
    <?php elseif ($slug === 'security'): ?>
    <p>Passwords are stored as hashes. Checkout goes through Stripe, and InPmnt does not store your customers’ card details.</p>
    <p>Sign-in is required for the workspace and the admin console. Report a security issue to <a href="mailto:<?= Http::e($support_email) ?>"><?= Http::e($support_email) ?></a>.</p>
    <?php else: ?>
    <p>The 14-day trial does not require a credit card, so there is nothing to refund before you subscribe.</p>
    <p>After you subscribe, you can cancel anytime from Billing. The plan stays active through the end of the period you already paid. Starter Annual is a yearly Starter plan at $100, which is $20 less than twelve months of Starter at $10.</p>
    <p>If a charge looks wrong, email <a href="mailto:<?= Http::e($support_email) ?>"><?= Http::e($support_email) ?></a> and we will look at that invoice with you.</p>
    <?php endif; ?>
    <p><a href="/">Back to home</a></p>
  </article>
<?php require __DIR__ . '/_help_chat.php'; ?>
</body>
</html>
