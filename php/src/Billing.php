<?php
declare(strict_types=1);

final class Billing
{
    /** Plans a new customer can buy. Charges use the Stripe price IDs in env. */
    public const PLANS = [
        'monthly' => ['name' => 'Monthly', 'amount_label' => '$4.99/mo', 'env_price' => 'STRIPE_PRICE_MONTHLY'],
        'yearly' => ['name' => 'Yearly', 'amount_label' => '$49.99/yr', 'env_price' => 'STRIPE_PRICE_YEARLY'],
    ];

    /**
     * Older subscriptions already stored as starter / pro / annual.
     * These env keys stay so webhooks can still map those price IDs.
     * They are not offered at checkout.
     */
    public const LEGACY_PLANS = [
        'starter' => ['name' => 'Starter', 'amount_label' => '$10/mo', 'env_price' => 'STRIPE_PRICE_STARTER'],
        'pro' => ['name' => 'Pro', 'amount_label' => '$20/mo', 'env_price' => 'STRIPE_PRICE_PRO'],
        'annual' => ['name' => 'Starter Annual', 'amount_label' => '$100/yr', 'env_price' => 'STRIPE_PRICE_ANNUAL'],
    ];

    public static function configuredValue(string $value, string $prefix = '', int $minLen = 16): bool
    {
        $v = trim($value);
        if ($v === '' || str_contains($v, '...')) {
            return false;
        }
        if ($prefix !== '' && !str_starts_with($v, $prefix)) {
            return false;
        }
        return strlen($v) >= $minLen;
    }

    public const CUSTOMER_SETUP = 'Payments are being set up. Inquiries Text First Then Call: 801.319.1061.';

    public const PAID_PLANS = ['monthly', 'yearly', 'starter', 'pro', 'annual'];

    /** Keys checkout needs. Placeholders (sk_test_..., price_...) count as invalid. */
    public static function configIssues(): array
    {
        $issues = [];
        if (!self::configuredValue(trim(Env::get('STRIPE_SECRET_KEY')), 'sk_', 20)) {
            $issues[] = 'STRIPE_SECRET_KEY';
        }
        foreach (self::PLANS as $meta) {
            if (!self::configuredValue(trim(Env::get($meta['env_price'])), 'price_', 20)) {
                $issues[] = $meta['env_price'];
            }
        }
        return $issues;
    }

    public static function adminSetupMessage(): string
    {
        $issues = self::configIssues();
        $list = $issues ? implode(', ', $issues) : 'STRIPE_SECRET_KEY, STRIPE_PRICE_MONTHLY, STRIPE_PRICE_YEARLY';
        return 'Stripe checkout is off. These keys in public_html/.env are missing or invalid: ' . $list . '.';
    }

    /** Calendar days until trial_ends_on. Today through that date is the remaining count. */
    public static function daysLeft(?string $date): int
    {
        $raw = substr(trim((string) $date), 0, 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return 0;
        }
        $end = DateTimeImmutable::createFromFormat('Y-m-d', $raw);
        $today = new DateTimeImmutable('today');
        if (!$end) {
            return 0;
        }
        $days = (int) $today->diff($end)->format('%r%a');
        return max(0, $days);
    }

    /**
     * Public origin for Stripe success and cancel URLs.
     * When BASE_URL is unset, use the host on this request so a live site
     * does not send customers back to 127.0.0.1.
     */
    public static function baseUrl(): string
    {
        $configured = trim(Env::get('BASE_URL'));
        if ($configured !== '') {
            return rtrim($configured, '/');
        }
        return rtrim(Http::requestOrigin(), '/');
    }

    public static function config(): array
    {
        $prices = [];
        foreach (self::PLANS as $key => $meta) {
            $prices[$key] = trim(Env::get($meta['env_price']));
        }
        $legacy = [];
        foreach (self::LEGACY_PLANS as $key => $meta) {
            $legacy[$key] = trim(Env::get($meta['env_price']));
        }
        $secret = trim(Env::get('STRIPE_SECRET_KEY'));
        $enabled = self::configIssues() === [];
        return [
            'secret_key' => $secret,
            'publishable_key' => trim(Env::get('STRIPE_PUBLISHABLE_KEY')),
            'webhook_secret' => trim(Env::get('STRIPE_WEBHOOK_SECRET')),
            'base_url' => self::baseUrl(),
            'prices' => $prices,
            'legacy_prices' => $legacy,
            'enabled' => $enabled,
        ];
    }

    public static function planFromPriceId(?string $priceId): ?string
    {
        if (!$priceId) {
            return null;
        }
        $cfg = self::config();
        foreach ([$cfg['prices'], $cfg['legacy_prices']] as $map) {
            foreach ($map as $plan => $pid) {
                if ($pid !== '' && $pid === $priceId) {
                    return $plan;
                }
            }
        }
        return null;
    }

    public static function api(string $method, string $path, array $params = []): array
    {
        if (Env::get('STRIPE_STUB') === 'error') {
            throw new RuntimeException('No such price: price_test_missing');
        }
        if (Env::get('STRIPE_STUB') === '1') {
            return self::stubResponse($method, $path, $params);
        }
        $cfg = self::config();
        if ($cfg['secret_key'] === '') {
            throw new RuntimeException('STRIPE_SECRET_KEY is not set');
        }
        $ch = curl_init('https://api.stripe.com' . $path);
        $headers = ['Authorization: Bearer ' . $cfg['secret_key']];
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        if ($params) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        }
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($raw === false) {
            throw new RuntimeException('Stripe request failed');
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new RuntimeException('Stripe returned invalid JSON');
        }
        if ($code >= 400) {
            $msg = $data['error']['message'] ?? $raw;
            throw new RuntimeException((string) $msg);
        }
        return $data;
    }

    public static function createCheckout(array $args): array
    {
        $plan = $args['plan'];
        if (!isset(self::PLANS[$plan])) {
            throw new InvalidArgumentException('Unknown plan');
        }
        $cfg = self::config();
        $price = $cfg['prices'][$plan] ?? '';
        if ($price === '') {
            throw new RuntimeException("Missing Stripe price for plan '{$plan}'");
        }
        $meta = ['plan' => $plan, 'user_id' => (string) $args['client_reference_id']];
        if (isset($args['workspace_id'])) {
            $meta['workspace_id'] = (string) $args['workspace_id'];
        }
        $params = [
            'mode' => 'subscription',
            'line_items' => [['price' => $price, 'quantity' => 1]],
            'success_url' => $cfg['base_url'] . '/billing/success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cfg['base_url'] . '/app#/billing',
            'client_reference_id' => (string) $args['client_reference_id'],
            'metadata' => $meta,
            'allow_promotion_codes' => 'true',
            'subscription_data' => [
                'metadata' => $meta,
                'description' => 'InvoicePay',
            ],
        ];
        if (!empty($args['customer_id'])) {
            $params['customer'] = $args['customer_id'];
        } else {
            $params['customer_email'] = $args['customer_email'];
        }
        return self::api('POST', '/v1/checkout/sessions', $params);
    }

    public static function createPortal(string $customerId): array
    {
        $cfg = self::config();
        return self::api('POST', '/v1/billing_portal/sessions', [
            'customer' => $customerId,
            'return_url' => $cfg['base_url'] . '/app#/billing',
        ]);
    }

    public static function retrieveCheckout(string $sessionId): array
    {
        return self::api('GET', '/v1/checkout/sessions/' . rawurlencode($sessionId) . '?expand[]=subscription&expand[]=subscription.items.data.price');
    }

    public static function constructEvent(string $payload, string $header, string $secret): array
    {
        $parts = [];
        foreach (explode(',', $header) as $item) {
            if (!str_contains($item, '=')) {
                continue;
            }
            [$k, $v] = array_map('trim', explode('=', $item, 2));
            $parts[$k][] = $v;
        }
        $timestamp = $parts['t'][0] ?? '';
        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
        $ok = false;
        foreach ($parts['v1'] ?? [] as $sig) {
            if (hash_equals($expected, $sig)) {
                $ok = true;
            }
        }
        if (!$ok) {
            throw new RuntimeException('Invalid Stripe signature');
        }
        if (abs(time() - (int) $timestamp) > 300) {
            throw new RuntimeException('Stripe timestamp too old');
        }
        $event = json_decode($payload, true);
        if (!is_array($event)) {
            throw new RuntimeException('Invalid Stripe event JSON');
        }
        return $event;
    }

    /** Local tests only. STRIPE_STUB=1 returns a checkout URL and does not mark anyone paid. */
    private static function stubResponse(string $method, string $path, array $params): array
    {
        $cfg = self::config();
        $plan = '';
        if (isset($params['metadata']['plan'])) {
            $plan = (string) $params['metadata']['plan'];
        }
        if (strtoupper($method) === 'POST' && str_contains($path, '/v1/checkout/sessions')) {
            return [
                'id' => 'cs_test_stub',
                'url' => $cfg['base_url'] . '/billing/stub-checkout?plan=' . rawurlencode($plan),
            ];
        }
        if (strtoupper($method) === 'POST' && str_contains($path, '/v1/billing_portal/sessions')) {
            return [
                'id' => 'bps_test_stub',
                'url' => $cfg['base_url'] . '/billing/stub-checkout?portal=1',
            ];
        }
        if (strtoupper($method) === 'GET' && str_contains($path, '/v1/checkout/sessions/')) {
            return ['id' => 'cs_test_stub', 'metadata' => ['plan' => $plan], 'customer' => null, 'subscription' => null];
        }
        throw new RuntimeException('Stripe stub has no response for ' . $path);
    }
}
