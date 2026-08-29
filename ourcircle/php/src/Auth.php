<?php
declare(strict_types=1);

/** TOTP 2FA and password-reset helpers for Family Shield Pro. Not InPmnt. */
final class Auth
{
    public static function totpOn(?array $user): bool
    {
        if (!$user) {
            return false;
        }
        return (int) ($user['totp_enabled'] ?? 0) === 1 && trim((string) ($user['totp_secret'] ?? '')) !== '';
    }

    public static function newSecret(): string
    {
        return self::b32encode(random_bytes(20));
    }

    public static function otpauthUri(string $email, string $secret): string
    {
        $label = rawurlencode('Family Shield Pro:' . $email);
        $q = http_build_query([
            'secret' => $secret,
            'issuer' => 'Family Shield Pro',
            'algorithm' => 'SHA1',
            'digits' => 6,
            'period' => 30,
        ], '', '&', PHP_QUERY_RFC3986);
        return 'otpauth://totp/' . $label . '?' . $q;
    }

    public static function totpAt(string $secret, int $timestamp, int $digits = 6, int $period = 30): string
    {
        $key = self::b32decode($secret);
        $counter = intdiv($timestamp, $period);
        $msg = pack('N*', 0, $counter);
        $hash = hash_hmac('sha1', $msg, $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $bin = unpack('N', substr($hash, $offset, 4));
        $code = ($bin[1] & 0x7FFFFFFF) % (10 ** $digits);
        return str_pad((string) $code, $digits, '0', STR_PAD_LEFT);
    }

    public static function verifyTotp(string $secret, string $code, int $timestamp = 0, int $window = 1): bool
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if (!preg_match('/^\d{6}$/', $code)) {
            return false;
        }
        $t = $timestamp > 0 ? $timestamp : time();
        for ($w = -$window; $w <= $window; $w++) {
            $expected = self::totpAt($secret, $t + ($w * 30));
            if (hash_equals($expected, $code)) {
                return true;
            }
        }
        return false;
    }

    /** @return list<string> plaintext codes; store the hashes */
    public static function newRecoveryCodes(int $n = 10): array
    {
        $codes = [];
        for ($i = 0; $i < $n; $i++) {
            $raw = bin2hex(random_bytes(4));
            $codes[] = substr($raw, 0, 4) . '-' . substr($raw, 4, 4);
        }
        return $codes;
    }

    public static function hashRecoveryCode(string $code): string
    {
        $norm = strtolower(preg_replace('/[^a-z0-9]/', '', $code) ?? '');
        return hash('sha256', 'ourcircle-recovery|' . $norm);
    }

    public static function hashList(array $codes): string
    {
        $hashes = [];
        foreach ($codes as $c) {
            $hashes[] = self::hashRecoveryCode((string) $c);
        }
        return json_encode($hashes, JSON_UNESCAPED_SLASHES) ?: '[]';
    }

    public static function consumeRecovery(string $storedJson, string $code): ?string
    {
        $hashes = json_decode($storedJson, true);
        if (!is_array($hashes) || $hashes === []) {
            return null;
        }
        $want = self::hashRecoveryCode($code);
        $found = false;
        $keep = [];
        foreach ($hashes as $h) {
            if (!$found && is_string($h) && hash_equals($h, $want)) {
                $found = true;
                continue;
            }
            $keep[] = $h;
        }
        if (!$found) {
            return null;
        }
        return json_encode(array_values($keep), JSON_UNESCAPED_SLASHES) ?: '[]';
    }

    public static function newResetToken(): array
    {
        $raw = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        return ['token' => $raw, 'hash' => hash('sha256', $raw)];
    }

    public static function groupSecret(string $secret): string
    {
        return trim(chunk_split($secret, 4, ' '));
    }

    private static function b32encode(string $raw): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        $len = strlen($raw);
        for ($i = 0; $i < $len; $i++) {
            $bits .= str_pad(decbin(ord($raw[$i])), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            if ($chunk === '') {
                continue;
            }
            $chunk = str_pad($chunk, 5, '0');
            $out .= $alphabet[bindec($chunk)];
        }
        return $out;
    }

    private static function b32decode(string $secret): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $s = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret) ?? '');
        $bits = '';
        $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $pos = strpos($alphabet, $s[$i]);
            if ($pos === false) {
                continue;
            }
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) < 8) {
                continue;
            }
            $out .= chr(bindec($chunk));
        }
        return $out;
    }
}
