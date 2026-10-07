<?php
$metaTitle = $meta_title ?? 'ReceiptGrid Invoicing';
$metaDesc = $meta_description ?? 'ReceiptGrid Invoicing sends invoice reminders so small businesses get paid without chasing clients. 14-day trial, no credit card. Then $5.00 a month or $50 a year.';
$metaRobots = $meta_robots ?? '';
$metaPath = Http::path();
$canonical = rtrim(Http::canonicalBase(), '/') . ($metaPath === '/' ? '/' : $metaPath);
$ogImage = rtrim(Http::canonicalBase(), '/') . '/static/img/og-image.png';
$googleVerify = Env::get('GOOGLE_SITE_VERIFICATION');
$msVerify = Env::get('MSVALIDATE_01');
?>
  <title><?= Http::e($metaTitle) ?></title>
  <meta name="description" content="<?= Http::e($metaDesc) ?>" />
  <link rel="canonical" href="<?= Http::e($canonical) ?>" />
  <meta property="og:type" content="website" />
  <meta property="og:site_name" content="ReceiptGrid Invoicing" />
  <meta property="og:title" content="<?= Http::e($metaTitle) ?>" />
  <meta property="og:description" content="<?= Http::e($metaDesc) ?>" />
  <meta property="og:url" content="<?= Http::e($canonical) ?>" />
  <meta property="og:image" content="<?= Http::e($ogImage) ?>" />
  <meta property="og:image:width" content="1200" />
  <meta property="og:image:height" content="630" />
  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:title" content="<?= Http::e($metaTitle) ?>" />
  <meta name="twitter:description" content="<?= Http::e($metaDesc) ?>" />
  <meta name="twitter:image" content="<?= Http::e($ogImage) ?>" />
<?php if ($metaRobots !== ''): ?>
  <meta name="robots" content="<?= Http::e($metaRobots) ?>" />
<?php endif; ?>
  <meta name="google-site-verification" content="<?= Http::e($googleVerify) ?>" />
  <meta name="msvalidate.01" content="<?= Http::e($msVerify) ?>" />
<?php if (!empty($json_ld)): ?>
  <script type="application/ld+json"><?= json_encode($json_ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
<?php endif; ?>
