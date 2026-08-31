<?php
declare(strict_types=1);

final class Analyze
{
    public const PAUSE = 'pause';
    public const CAUTION = 'caution';
    public const LOOKALIKE = 'lookalike';
    public const UNKNOWN = 'unknown';

    public const CORE_RULE = 'Never send money, cryptocurrency, gift cards, passwords, or account information until the request is independently verified.';
    public const GUIDANCE = 'This application offers guidance, not a guarantee.';
    public const DISCLAIMER = 'OurCircle cannot tell you that something is safe. We help you pause, look for warning signs, check your family\'s trusted list, and ask someone you trust before you act. ' . self::GUIDANCE;

    public const KNOWN_BRANDS = [
        'irs.gov', 'ssa.gov', 'usa.gov', 'ftc.gov', 'paypal.com', 'amazon.com', 'apple.com',
        'microsoft.com', 'google.com', 'wellsfargo.com', 'chase.com', 'bankofamerica.com',
        'usbank.com', 'capitalone.com', 'aetna.com', 'uhc.com', 'anthem.com', 'medicare.gov', 'va.gov',
    ];

    public static function digitsOnly(string $value): string
    {
        $d = preg_replace('/\D+/', '', $value) ?? '';
        if (strlen($d) === 11 && str_starts_with($d, '1')) {
            $d = substr($d, 1);
        }
        return $d;
    }

    public static function extractUrls(string $text): array
    {
        preg_match_all('#https?://[^\s<>]+|(?:www\.)[a-z0-9.-]+\.[a-z]{2,}#i', $text, $m);
        $out = [];
        foreach ($m[0] as $raw) {
            $item = rtrim(trim($raw), ').,;');
            if ($item !== '' && !in_array($item, $out, true)) {
                $out[] = $item;
            }
        }
        return $out;
    }

    public static function extractPhones(string $text): array
    {
        preg_match_all('/(?:\+?1[-.\s]?)?(?:\(?\d{3}\)?[-.\s]?)\d{3}[-.\s]?\d{4}/', $text, $m);
        $out = [];
        $seen = [];
        foreach ($m[0] as $match) {
            $d = self::digitsOnly($match);
            if (strlen($d) === 10 && !isset($seen[$d])) {
                $seen[$d] = true;
                $out[] = $d;
            }
        }
        return $out;
    }

    public static function registrableDomain(string $url): string
    {
        $raw = trim($url);
        if ($raw === '') {
            return '';
        }
        if (!str_contains($raw, '://')) {
            $raw = 'https://' . $raw;
        }
        $host = strtolower((string) (parse_url($raw, PHP_URL_HOST) ?? ''));
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }
        return $host;
    }

    public static function lookalikeHits(string $domain, array $trustedDomains): array
    {
        $host = self::registrableDomain($domain);
        if ($host === '') {
            $host = strtolower(trim($domain));
            if (str_starts_with($host, 'www.')) {
                $host = substr($host, 4);
            }
        }
        if ($host === '') {
            return [];
        }
        $pool = array_fill_keys(self::KNOWN_BRANDS, true);
        foreach ($trustedDomains as $item) {
            $cleaned = self::registrableDomain((string) $item) ?: strtolower(trim((string) $item));
            if ($cleaned !== '') {
                $pool[$cleaned] = true;
            }
        }
        $hits = [];
        $hostCore = explode('.', $host)[0];
        $brands = array_keys($pool);
        sort($brands);
        foreach ($brands as $brand) {
            if ($host === $brand) {
                continue;
            }
            $base = explode('.', $brand)[0];
            if (strlen($base) < 4) {
                continue;
            }
            $dist = levenshtein($host, $brand);
            $distCore = levenshtein($hostCore, $base);
            $contains = strlen($base) >= 5 && str_contains(str_replace('-', '', $host), $base);
            if ($dist <= 2 || $distCore <= 1 || $contains) {
                $hits[] = $brand;
            }
        }
        return $hits;
    }

    public static function analyze(string $text = '', string $phone = '', string $url = '', array $trusted = []): array
    {
        $blob = trim(implode(' ', array_filter([$text, $phone, $url], fn ($p) => $p !== '')));
        $trustedPhones = [];
        $trustedDomains = [];
        foreach ($trusted as $row) {
            $d = self::digitsOnly((string) ($row['phone'] ?? ''));
            if ($d !== '') {
                $trustedPhones[$d] = true;
            }
            $site = (string) ($row['website'] ?? $row['domain'] ?? '');
            $host = self::registrableDomain($site);
            if ($host !== '') {
                $trustedDomains[] = $host;
            }
        }

        $urls = self::extractUrls($blob);
        if ($url !== '') {
            $host = (str_contains($url, '://') || str_contains($url, '.')) ? $url : $url;
            if (!in_array($host, $urls, true)) {
                array_unshift($urls, $host);
            }
        }
        $phones = self::extractPhones($blob);
        $p = self::digitsOnly($phone);
        if (strlen($p) === 10 && !in_array($p, $phones, true)) {
            array_unshift($phones, $p);
        }

        $signs = [];
        $matches = [];
        $level = self::UNKNOWN;

        if (preg_match('/\b(gift\s*cards?|steam\s*card|apple\s*card|google\s*play\s*card|itunes|vanilla\s*card|moneygram|western\s*union|bitcoin|btc|crypto|usdt|ether|wire\s*transfer|cashier\'?s?\s*check)\b/i', $blob)) {
            $signs[] = 'This asks for a gift card, crypto, wire, or similar hard-to-reverse payment. Real banks, tax offices, and family emergencies almost never demand that.';
            $level = self::PAUSE;
        }
        if (preg_match('/\b(don\'?t tell|keep this (secret|quiet)|do not (tell|call)|between us|your grandson|your grandson\'?s? in (jail|trouble)|act now|today only|limited time|or else|account (will be )?suspend|warrant for (your )?arrest)\b/i', $blob)) {
            $signs[] = 'It pushes you to keep it secret or act before you can talk to anyone. Scams thrive on isolation and panic.';
            $level = self::PAUSE;
        }
        if (preg_match('/\b(anydesk|teamviewer|remote\s*access|let me on your computer|install this (app|program)|screen\s*share)\b/i', $blob)) {
            $signs[] = 'It wants remote access to your computer or phone. Hang up and use a device they cannot see.';
            $level = self::PAUSE;
        }
        if (preg_match('/\b(pay (now|immediately|today)|send (it|money) now|wire it|before (midnight|they arrest)|confirm (your )?(password|ssn|social|routing|account number))\b/i', $blob)) {
            $signs[] = 'It asks you to pay or share a password / account number right now. Independent verification comes first.';
            if ($level !== self::PAUSE) {
                $level = self::CAUTION;
            }
        }
        if (preg_match('/\b(you(\'ve| have)? won|claim your prize|free (iphone|car|vacation)|unclaimed (refund|benefit)|overpaid taxes)\b/i', $blob)) {
            $signs[] = 'Unexpected prizes, refunds, or \'you won\' messages are a classic lure. Do not pay a fee to receive money.';
            if ($level === self::UNKNOWN) {
                $level = self::CAUTION;
            }
        }

        foreach ($urls as $hostSrc) {
            $host = self::registrableDomain($hostSrc) ?: strtolower($hostSrc);
            $likes = self::lookalikeHits($hostSrc, $trustedDomains);
            if ($likes) {
                $top = implode(', ', array_slice($likes, 0, 3));
                $signs[] = "The website {$host} resembles {$top} but is not an exact match. Lookalike sites are a common trick.";
                $matches[] = "Lookalike of {$top}";
                if ($level !== self::PAUSE) {
                    $level = self::LOOKALIKE;
                }
            }
            if (in_array($host, $trustedDomains, true)) {
                $matches[] = "Domain matches a trusted contact: {$host}";
            } elseif (in_array($host, self::KNOWN_BRANDS, true)) {
                $matches[] = "{$host} is a well-known official domain — still call using a number you already have, not a number in the message.";
            }
        }

        foreach ($phones as $num) {
            $pretty = '(' . substr($num, 0, 3) . ') ' . substr($num, 3, 3) . '-' . substr($num, 6);
            if (isset($trustedPhones[$num])) {
                $matches[] = "{$pretty} is on your family's trusted list.";
            } else {
                $matches[] = "{$pretty} is not on your trusted list. Call the organization using a number from a statement, the back of your card, or a contact you already saved — not this one.";
                if ($level === self::UNKNOWN) {
                    $level = self::CAUTION;
                }
            }
        }

        if ($blob === '') {
            $signs[] = 'Nothing was pasted yet. Add the message, number, or website you were given.';
            $level = self::UNKNOWN;
        } elseif (!$signs && $level === self::UNKNOWN) {
            $signs[] = 'No classic scam phrases jumped out, which does not mean it is genuine. Pause and verify with your circle anyway.';
        }

        $titles = [
            self::PAUSE => 'Pause. Do not pay or share anything yet.',
            self::CAUTION => 'Slow down and verify this independently.',
            self::LOOKALIKE => 'This may be pretending to be someone you know.',
            self::UNKNOWN => 'We cannot confirm this. Ask your circle before you act.',
        ];
        $next = [
            'Do not send money, crypto, gift cards, passwords, or account details yet.',
            'Ask someone in your family circle to look at this with you.',
            'If they want you to pay, use a phone number you already trust — not a number in the message.',
            'If you already paid or shared information, open Report & recover for the next steps.',
        ];
        if ($level === self::PAUSE) {
            array_splice($next, 1, 0, ['Send a “Please call me before I pay” alert to your circle so nobody is left alone with this.']);
        }

        return [
            'level' => $level,
            'title' => $titles[$level],
            'explanation' => $signs ? implode(' ', array_slice($signs, 0, 3)) : self::DISCLAIMER,
            'warning_signs' => $signs,
            'matches' => $matches,
            'next_steps' => $next,
            'phones' => $phones,
            'urls' => array_map(fn ($u) => self::registrableDomain($u) ?: $u, $urls),
            'core_rule' => self::CORE_RULE,
            'disclaimer' => self::DISCLAIMER,
            'never_safe' => true,
        ];
    }
}
