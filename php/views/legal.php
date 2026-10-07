<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="<?= Http::e(Http::csrfToken()) ?>" />
  <?php
    $meta_title = $meta_title ?? ($title . ' · ReceiptGrid');
    $meta_description = $meta_description ?? ($title . ' for ReceiptGrid Invoicing at invcpay.com.');
    require __DIR__ . '/_meta.php';
  ?>
  <link rel="icon" type="image/png" href="/static/img/inpmnt-icon.png" />
  <link rel="stylesheet" href="/static/css/app.css?v=<?= rawurlencode(Http::VERSION) ?>" />
</head>
<body class="landing">
<?php require __DIR__ . '/_nav.php'; ?>
  <article class="legal-page">
    <h1><?= Http::e($title) ?></h1>
    <?php if ($slug === 'privacy'): ?>
    <p>ReceiptGrid Invoicing stores the account, client, and invoice details you enter so reminders can be sent. We use that information to run the product, send the messages you schedule, and answer support requests.</p>
    <p>Paid checkout is handled by Stripe. ReceiptGrid does not store your customers’ card numbers. Stripe’s own privacy terms apply to card data they process.</p>
    <p>To ask for a copy of your workspace data or to close an account, email <a href="mailto:<?= Http::e($support_email) ?>"><?= Http::e($support_email) ?></a>.</p>
    <?php elseif ($slug === 'terms'): ?>
    <p>ReceiptGrid Invoicing sends the invoice reminders you configure. You are responsible for the accuracy of invoice amounts, the permission to email or text your clients, and the content of those messages.</p>
    <p>The 14-day trial does not require a credit card. After that, the price is $4.99 per month or $49.99 per year. You can switch between those two from Billing. Cancel anytime; access continues through the period already paid.</p>
    <p>The service is provided by Robert Foster. Questions: <a href="mailto:<?= Http::e($support_email) ?>"><?= Http::e($support_email) ?></a>.</p>
    <?php elseif ($slug === 'contact'): ?>
    <p>Email <a href="mailto:<?= Http::e($support_email) ?>"><?= Http::e($support_email) ?></a> for sales, billing, or account questions.</p>
    <p>ReceiptGrid Invoicing is the product. This site stays at invcpay.com, and support mail stays at support@invcpay.com.</p>
    <?php elseif ($slug === 'support'): ?>
    <p>Use the help button on any page, or email <a href="mailto:<?= Http::e($support_email) ?>"><?= Http::e($support_email) ?></a>.</p>
    <p>Include your account email and, for a billing question, whether you chose monthly or yearly.</p>
    <?php elseif ($slug === 'security'): ?>
    <p>Passwords are stored as hashes. Checkout goes through Stripe, and ReceiptGrid does not store your customers’ card details.</p>
    <p>Sign-in is required for the workspace and the admin console. Report a security issue to <a href="mailto:<?= Http::e($support_email) ?>"><?= Http::e($support_email) ?></a>.</p>
    <?php else: ?>
    <p>The 14-day trial does not require a credit card, so there is nothing to refund before you subscribe.</p>
    <p>After you subscribe, you can cancel anytime from Billing. The plan stays active through the end of the period you already paid. Yearly billing is $49.99, which is $9.89 less than twelve months at $4.99.</p>
    <p>If a charge looks wrong, email <a href="mailto:<?= Http::e($support_email) ?>"><?= Http::e($support_email) ?></a> and we will look at that invoice with you.</p>
    <?php endif; ?>
    <p><a href="/">Back to home</a></p>
  </article>
<?php require __DIR__ . '/_version.php'; ?>
<?php require __DIR__ . '/_help_chat.php'; ?>
</body>
</html>
