<?php
declare(strict_types=1);

final class View
{
    public static function render(string $name, array $vars = []): never
    {
        $vars['site_home'] = $vars['site_home'] ?? rtrim(Env::get('OURCIRCLE_SITE_URL', Env::get('BASE_URL', 'https://familyshieldpro.com')), '/');
        $vars['core_rule'] = $vars['core_rule'] ?? Analyze::CORE_RULE;
        $vars['disclaimer'] = $vars['disclaimer'] ?? Analyze::DISCLAIMER;
        $vars['flashes'] = $vars['flashes'] ?? [];
        $vars['user_name'] = $vars['user_name'] ?? '';
        $vars['path'] = Http::path();
        $vars['robots'] = $vars['robots'] ?? 'index,follow';
        extract($vars, EXTR_SKIP);
        require dirname(__DIR__) . '/views/' . $name . '.php';
        exit;
    }

    public static function flashesHtml(array $flashes): string
    {
        $html = '';
        foreach ($flashes as $item) {
            $cat = Http::e((string) ($item[0] ?? 'ok'));
            $msg = Http::e((string) ($item[1] ?? ''));
            $html .= '<div class="flash ' . $cat . '">' . $msg . '</div>';
        }
        return $html;
    }

    public static function brand(string $siteHome, string $name = 'OurCircle', string $sub = 'Family Shield Pro'): string
    {
        return '<a class="brand" href="' . Http::e($siteHome) . '" title="Family Shield Pro">'
            . '<img src="/static/img/logo.png" alt="Family Shield Pro" />'
            . '<div><strong>' . Http::e($name) . '</strong><span>' . Http::e($sub) . '</span></div></a>';
    }

    public static function start(string $title, string $siteHome, string $robots, string $path): void
    {
        $canonical = $siteHome . ($path === '/' ? '/' : $path);
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8" />'
            . '<meta name="viewport" content="width=device-width, initial-scale=1" />'
            . '<title>' . Http::e($title) . '</title>'
            . '<meta name="robots" content="' . Http::e($robots) . '" />'
            . '<link rel="canonical" href="' . Http::e($canonical) . '" />'
            . '<link rel="sitemap" type="application/xml" title="Sitemap" href="' . Http::e($siteHome) . '/sitemap.xml" />'
            . '<link rel="icon" href="/static/img/logo.png" />'
            . '<link rel="preconnect" href="https://fonts.googleapis.com" />'
            . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />'
            . '<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;600;700&family=Source+Serif+4:opsz,wght@8..60,600;8..60,700&display=swap" rel="stylesheet" />'
            . '<link rel="stylesheet" href="/static/css/app.css" />'
            . '</head><body>';
    }

    public static function appOpen(array $vars): void
    {
        self::start(
            (string) ($vars['title'] ?? 'OurCircle'),
            (string) $vars['site_home'],
            (string) ($vars['robots'] ?? 'noindex,nofollow'),
            (string) ($vars['path'] ?? '/home')
        );
        $name = (string) ($vars['user_name'] ?? '');
        echo '<div class="wrap"><header class="app-header">'
            . self::brand((string) $vars['site_home'], 'OurCircle', $name !== '' ? $name : 'Family Shield Pro')
            . '<nav class="nav">'
            . '<a href="/home">Check</a>'
            . '<a href="/circle">Circle</a>'
            . '<a href="/trusted">Trusted list</a>'
            . '<a href="/report">Report</a>'
            . '<a href="/billing">Plans</a>'
            . '<a href="/account">Account</a>'
            . '<a class="btn ghost" href="/logout">Sign out</a>'
            . '</nav></header>'
            . '<p class="core-rule">' . Http::e((string) ($vars['core_rule'] ?? Analyze::CORE_RULE)) . '</p>'
            . self::flashesHtml($vars['flashes'] ?? [])
            . '<main class="app-main">';
    }

    public static function appClose(): void
    {
        echo '</main></div></body></html>';
    }
}
