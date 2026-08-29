<?php
declare(strict_types=1);

final class SupportChat
{
    public const SUPPORT_EMAIL = 'CustomerService@FamilyShieldPro.com';

    // Customer-service phone is not live. Homepage markup is commented out.
    // To publish a number: see SUPPORT.md, then uncomment the homepage
    // <p class="support-phone"> block and (optionally) this constant.
    // public const SUPPORT_PHONE = '+1XXXXXXXXXX';

    public static function openaiConfigured(): bool
    {
        $key = trim(Env::get('OPENAI_API_KEY', ''));
        return str_starts_with($key, 'sk-') && !str_contains($key, '...') && strlen($key) > 24;
    }

    public static function faqReply(string $message): string
    {
        $text = strtolower(trim($message));
        if ($text === '') {
            return 'Ask me about plans, login, or how the circle works. For a person, email ' . self::SUPPORT_EMAIL . '.';
        }
        $faq = [
            [['send them money', 'asks me to send', 'should i send', 'should i do it', 'give them money', 'wire them', 'send money'],
                'NO!!! Do not send the money. Not unless a family member helps you vet it, and you can make sure it is the person you think it is — without a doubt. Call a number you already have for them, not the one in the message. ' . Analyze::CORE_RULE],
            [['safe', 'scam', 'verify', 'legit', 'real or', 'too good'],
                'I will never tell you a request is safe. ' . Analyze::CORE_RULE . ' If it sounds too good to be true, it usually is. Really! Really! Really! Pause, involve your circle, and call numbers you already trust — not the ones in the message.'],
            [['price', 'cost', 'plan', 'monthly', 'yearly', 'how much', 'subscription', '119', '14.99'],
                'Family Shield Pro is $14.99 per month or $119.99 per year for one circle of up to five people. Yearly is the better family value. Start at familyshieldpro.com/signup. Billing questions: ' . self::SUPPORT_EMAIL . '.'],
            [['password', 'forgot', 'reset', '2fa', 'two-factor', 'two factor', 'authenticator', 'recovery code'],
                'Forgot your password? Use /forgot. We can email a one-hour reset link if mail is set up, or you can use a one-time recovery code from when you turned on 2FA. Turn 2FA on or off under Account after you sign in. Stuck? ' . self::SUPPORT_EMAIL . '.'],
            [['phone', 'call us', 'telephone', 'support number', 'customer service number'],
                'We do not publish a customer-service phone number yet. Email ' . self::SUPPORT_EMAIL . ' — a person reads every message.'],
            [['email', 'contact', 'reach you', 'customer service', 'support'],
                'Email customer service at ' . self::SUPPORT_EMAIL . '. A person reads every message. There is no public phone number yet.'],
            [['circle', 'invite', 'member', 'five', '5 people', 'household'],
                'A circle is up to five people in one household. Invite them from Circle after you sign in. Anyone in the circle can look at a check and you can tap “Please call me before I pay” when it is urgent.'],
            [['stripe', 'billing', 'cancel', 'refund', 'charge', 'card', 'invoice'],
                'Plans are Family monthly $14.99 or Family yearly $119.99. If Stripe keys are in .env, Plans sends you to Stripe Checkout; you can manage or cancel in the Stripe customer portal. Until keys are live, choosing a plan only saves the flag — no charge. Email ' . self::SUPPORT_EMAIL . ' for billing help.'],
            [['login', 'sign in', 'signin', 'account', 'demo'],
                'Sign in at /login. The demo circle is family@ourcircle.app / password123 (2FA off until you enable it). If you are locked out, try /forgot or email ' . self::SUPPORT_EMAIL . '.'],
            [['what is', 'what’s this', 'whats this', 'ourcircle', 'family shield', 'how it works', 'pause'],
                'Family Shield Pro (OurCircle) is a trusted family circle for sketchy texts, calls, prizes, and urgent payment asks. It is not an AI that stamps a request as safe. You paste the message, read the warning signs, and get someone you trust on the phone — then you decide.'],
            [['hello', 'hi ', 'hey', 'help', 'thanks'],
                'Hi — I can explain plans, login, the circle of five, and the pause-and-verify rule. I will never tell you a request is safe. For a person, email ' . self::SUPPORT_EMAIL . '.'],
        ];
        $best = '';
        $bestHits = 0;
        foreach ($faq as [$keys, $reply]) {
            $hits = 0;
            foreach ($keys as $k) {
                if (str_contains($k, ' ')) {
                    if (str_contains($text, $k)) {
                        $hits++;
                    }
                } elseif (preg_match('/\b' . preg_quote($k, '/') . '\b/u', $text)) {
                    $hits++;
                }
            }
            if ($hits > $bestHits) {
                $bestHits = $hits;
                $best = $reply;
            }
        }
        if ($bestHits > 0) {
            return $best;
        }
        return 'I can help with Family Shield Pro plans ($14.99/month or $119.99/year), login and password reset, the circle of five, and the pause-and-verify rule. I will never tell you a request is safe. For anything else, email ' . self::SUPPORT_EMAIL . '.';
    }

    /** @param list<array<string,mixed>> $history */
    public static function reply(string $message, array $history = []): array
    {
        $msg = trim($message);
        if (strlen($msg) > 800) {
            $msg = substr($msg, 0, 800);
        }
        if ($msg === '') {
            return ['reply' => self::faqReply(''), 'source' => 'faq'];
        }
        $ai = self::openaiReply($msg, $history);
        if (is_string($ai) && $ai !== '') {
            return ['reply' => $ai, 'source' => 'openai'];
        }
        return ['reply' => self::faqReply($msg), 'source' => 'faq'];
    }

    /** @param list<array<string,mixed>> $history */
    private static function openaiReply(string $message, array $history): ?string
    {
        if (!self::openaiConfigured()) {
            return null;
        }
        $key = trim(Env::get('OPENAI_API_KEY', ''));
        $model = trim(Env::get('OPENAI_MODEL', 'gpt-4o-mini')) ?: 'gpt-4o-mini';
        $system = 'You are the Family Shield Pro (OurCircle) customer-service helper at familyshieldpro.com. '
            . 'Product: a family pause-and-verify circle of up to five people. '
            . 'Plans: $14.99/month or $119.99/year. '
            . 'Never tell anyone a request, message, phone number, or website is safe or legitimate. '
            . 'Always keep this rule in mind: ' . Analyze::CORE_RULE . ' '
            . 'If it sounds too good to be true, it usually is. '
            . 'If they ask whether they should send money because someone asked them to: answer with a resounding NO!!! '
            . 'Not unless a family member helps them vet it, and they can make sure it is the person they think it is — without a doubt. '
            . 'Support email: ' . self::SUPPORT_EMAIL . '. Do not invent a phone number — phone support is not published yet. '
            . 'Password reset is at /forgot. 2FA is on Account. '
            . 'Keep answers short (2–6 sentences). Do not ask for passwords, card numbers, or 2FA codes. '
            . 'If you cannot help, point to the support email.';
        $messages = [['role' => 'system', 'content' => $system]];
        $slice = array_slice($history, -8);
        foreach ($slice as $item) {
            if (!is_array($item)) {
                continue;
            }
            $role = (string) ($item['role'] ?? '');
            $content = substr((string) ($item['content'] ?? ''), 0, 400);
            if (($role === 'user' || $role === 'assistant') && $content !== '') {
                $messages[] = ['role' => $role, 'content' => $content];
            }
        }
        $messages[] = ['role' => 'user', 'content' => $message];
        $payload = json_encode([
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.3,
            'max_tokens' => 400,
        ], JSON_UNESCAPED_SLASHES);
        if (!is_string($payload)) {
            return null;
        }
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        if ($ch === false) {
            return null;
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $key,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $data = json_decode($raw, true);
        $text = trim((string) ($data['choices'][0]['message']['content'] ?? ''));
        if ($text === '' || preg_match('/\b(this is safe|it is safe|looks safe|seems safe)\b/i', $text)) {
            return null;
        }
        return $text;
    }
}
