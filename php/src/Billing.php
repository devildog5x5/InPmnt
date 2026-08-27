<?php
declare(strict_types=1);

final class Billing
{
    public const PLANS = [
        'starter' => ['name' => 'Starter', 'amount_label' => '$19/mo', 'env_price' => 'STRIPE_PRICE_STARTER'],
        'pro' => ['name' => 'Pro', 'amount_label' => '$39/mo', 'env_price' => 'STRIPE_PRICE_PRO'],
        'annual' => ['name' => 'Annual', 'amount_label' => '$99/yr', 'env_price' => 'STRIPE_PRICE_ANNUAL'],
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

    public static function config(): array
    {
        $prices = [];
        foreach (self::PLANS as $key => $meta) {
            $prices[$key] = trim(Env::get($meta['env_price']));
        }
        $secret = trim(Env::get('STRIPE_SECRET_KEY'));
        $enabled = self::configuredValue($secret, 'sk_', 20);
        foreach ($prices as $pid) {
            $enabled = $enabled && self::configuredValue($pid, 'price_', 20);
        }
        return [
            'secret_key' => $secret,
            'publishable_key' => trim(Env::get('STRIPE_PUBLISHABLE_KEY')),
            'webhook_secret' => trim(Env::get('STRIPE_WEBHOOK_SECRET')),
            'base_url' => Http::publicOrigin(),
            'prices' => $prices,
            'enabled' => $enabled,
        ];
    }

    public static function planFromPriceId(?string $priceId): ?string
    {
        if (!$priceId) {
            return null;
        }
        foreach (self::config()['prices'] as $plan => $pid) {
            if ($pid !== '' && $pid === $priceId) {
                return $plan;
            }
        }
        return null;
    }

    public static function api(string $method, string $path, array $params = []): array
    {
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
            'cancel_url' => $cfg['base_url'] . '/#pricing',
            'client_reference_id' => (string) $args['client_reference_id'],
            'metadata' => $meta,
            'allow_promotion_codes' => 'true',
            'subscription_data' => ['metadata' => $meta],
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
            'return_url' => $cfg['base_url'] . '/app#/settings',
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
}
