<?php
declare(strict_types=1);

/** Operator console gate for Family Shield Pro. Not a family page. Not InPmnt. */
final class Admin
{
    public static function password(): string
    {
        return trim(Env::get('ADMIN_PASSWORD'));
    }

    public static function configured(): bool
    {
        $password = self::password();
        if ($password === '' || str_contains($password, '...')) {
            return false;
        }
        return strlen($password) >= 12;
    }

    /** @return list<string> */
    public static function emails(): array
    {
        $raw = trim(Env::get('ADMIN_EMAIL'));
        if ($raw === '' || str_contains($raw, '...')) {
            return [];
        }
        $out = [];
        foreach (explode(',', $raw) as $part) {
            $email = strtolower(trim($part));
            if ($email !== '' && str_contains($email, '@')) {
                $out[] = $email;
            }
        }
        return array_values(array_unique($out));
    }

    public static function passwordOk(string $given): bool
    {
        if (!self::configured()) {
            return false;
        }
        return hash_equals(self::password(), $given);
    }

    public static function emailIsAdmin(string $email): bool
    {
        if (!self::configured()) {
            return false;
        }
        return in_array(strtolower(trim($email)), self::emails(), true);
    }
}
