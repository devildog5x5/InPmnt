<?php
declare(strict_types=1);

final class Product
{
    public const NAME = 'Family Shield Pro';
    public const APP = 'OurCircle';
    public const SITE = 'https://familyshieldpro.com';
    public const CHANNEL = 'Hostinger-PHP';

    public static function version(): string
    {
        $path = dirname(__DIR__) . '/VERSION';
        if (is_file($path)) {
            $v = trim((string) file_get_contents($path));
            if ($v !== '') {
                return $v;
            }
        }
        return '1.2.22';
    }

    public static function label(): string
    {
        return self::NAME . ' ' . self::APP . ' v' . self::version();
    }
}
