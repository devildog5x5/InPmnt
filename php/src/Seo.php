<?php
declare(strict_types=1);

final class Seo
{
    public const TITLE = 'InPmnt — Invoice reminders for trades and freelancers';
    public const DESCRIPTION = 'Invoice reminder software for plumbers, landscapers, photographers, and consultants. Track overdue invoices, auto-send payment reminders, and record payments without a full accounting suite.';

    public static function faqs(): array
    {
        return [
            [
                'q' => 'What is InPmnt?',
                'a' => 'InPmnt is invoice reminder software for solo trades and freelancers. You paste unpaid invoices, set a reminder schedule, and the app queues polite payment nudges so you spend less time chasing late clients.',
            ],
            [
                'q' => 'Who is InPmnt for?',
                'a' => 'Plumbers, HVAC techs, landscapers, photographers, videographers, and independent consultants who invoice clients and get paid late. It is not a replacement for QuickBooks or a full CRM.',
            ],
            [
                'q' => 'How do invoice payment reminders work?',
                'a' => 'You add an invoice with a due date and reminder offsets (before due, on the day, and after). InPmnt queues email or SMS templates you control, including a final notice when someone has gone quiet.',
            ],
            [
                'q' => 'How much does InPmnt cost?',
                'a' => 'Starter is $19/month for up to 40 open invoices. Pro is $39/month for unlimited invoices plus SMS and custom templates. Annual is $99/year with Starter features. All plans start with a 14-day trial.',
            ],
        ];
    }

    public static function origin(): string
    {
        return rtrim(Http::publicOrigin(), '/');
    }

    public static function landingHead(): void
    {
        $origin = self::origin();
        $url = $origin . '/';
        $img = $origin . '/static/img/inpmnt-icon.png';
        $title = self::TITLE;
        $desc = self::DESCRIPTION;
        echo '<title>' . Http::e($title) . "</title>\n";
        echo '<meta name="description" content="' . Http::e($desc) . "\" />\n";
        echo "<link rel=\"canonical\" href=\"" . Http::e($url) . "\" />\n";
        echo "<meta name=\"robots\" content=\"index, follow\" />\n";
        echo '<meta property="og:type" content="website" />' . "\n";
        echo '<meta property="og:site_name" content="InPmnt" />' . "\n";
        echo '<meta property="og:title" content="' . Http::e($title) . "\" />\n";
        echo '<meta property="og:description" content="' . Http::e($desc) . "\" />\n";
        echo '<meta property="og:url" content="' . Http::e($url) . "\" />\n";
        echo '<meta property="og:image" content="' . Http::e($img) . "\" />\n";
        echo '<meta name="twitter:card" content="summary" />' . "\n";
        echo '<meta name="twitter:title" content="' . Http::e($title) . "\" />\n";
        echo '<meta name="twitter:description" content="' . Http::e($desc) . "\" />\n";
        echo '<script type="application/ld+json">' . json_encode(self::graph($origin), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "</script>\n";
    }

    public static function noIndexHead(string $title): void
    {
        echo '<title>' . Http::e($title) . "</title>\n";
        echo "<meta name=\"robots\" content=\"noindex, nofollow\" />\n";
    }

    public static function robots(): never
    {
        $origin = self::origin();
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        header('X-Robots-Tag: noindex');
        echo "User-agent: *\n";
        echo "Allow: /\n";
        echo "Allow: /static/\n";
        echo "Disallow: /app\n";
        echo "Disallow: /api/\n";
        echo "Disallow: /login\n";
        echo "Disallow: /signup\n";
        echo "Disallow: /logout\n";
        echo "Disallow: /billing/\n";
        echo "Disallow: /inpmnt-check.php\n";
        echo "Disallow: /data/\n";
        echo "Disallow: /src/\n";
        echo "Sitemap: {$origin}/sitemap.xml\n";
        exit;
    }

    public static function sitemap(): never
    {
        $origin = self::origin();
        $today = gmdate('Y-m-d');
        header('Content-Type: application/xml; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        echo "  <url><loc>{$origin}/</loc><lastmod>{$today}</lastmod><changefreq>weekly</changefreq><priority>1.0</priority></url>\n";
        echo "</urlset>\n";
        exit;
    }

    public static function llms(): never
    {
        $origin = self::origin();
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        echo "InPmnt — Get paid without the chase.\n";
        echo "Invoice reminder software for solo trades and freelancers.\n\n";
        echo "Site: {$origin}/\n";
        echo "Signup: {$origin}/signup\n";
        echo "Pricing: {$origin}/#pricing\n\n";
        echo self::DESCRIPTION . "\n\n";
        foreach (self::faqs() as $faq) {
            echo "Q: {$faq['q']}\nA: {$faq['a']}\n\n";
        }
        exit;
    }

    private static function graph(string $origin): array
    {
        $faqs = [];
        foreach (self::faqs() as $faq) {
            $faqs[] = [
                '@type' => 'Question',
                'name' => $faq['q'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $faq['a'],
                ],
            ];
        }
        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'SoftwareApplication',
                    'name' => 'InPmnt',
                    'url' => $origin . '/',
                    'applicationCategory' => 'BusinessApplication',
                    'operatingSystem' => 'Web',
                    'description' => self::DESCRIPTION,
                    'offers' => [
                        [
                            '@type' => 'Offer',
                            'name' => 'Starter',
                            'price' => '19.00',
                            'priceCurrency' => 'USD',
                        ],
                        [
                            '@type' => 'Offer',
                            'name' => 'Pro',
                            'price' => '39.00',
                            'priceCurrency' => 'USD',
                        ],
                        [
                            '@type' => 'Offer',
                            'name' => 'Annual',
                            'price' => '99.00',
                            'priceCurrency' => 'USD',
                        ],
                    ],
                ],
                [
                    '@type' => 'Organization',
                    'name' => 'InPmnt',
                    'url' => $origin . '/',
                    'founder' => ['@type' => 'Person', 'name' => 'Robert Foster'],
                ],
                [
                    '@type' => 'FAQPage',
                    'mainEntity' => $faqs,
                ],
            ],
        ];
    }
}
