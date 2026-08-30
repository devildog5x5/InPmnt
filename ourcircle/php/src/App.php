<?php
declare(strict_types=1);

final class App
{
    public const PLANS = [
        [
            'id' => 'monthly',
            'name' => 'Family monthly',
            'price' => '$14.99/month',
            'detail' => 'Up to five people in one circle. Pause, trusted list, and call-me-before-I-pay.',
            'featured' => false,
        ],
        [
            'id' => 'yearly',
            'name' => 'Family yearly',
            'price' => '$119.99/year',
            'detail' => 'Same circle. Pay once a year — about $10 a month. Best for families.',
            'featured' => true,
        ],
    ];

    public const PRIVATE = ['/home', '/circle', '/trusted', '/checks', '/uploads', '/join', '/billing', '/report', '/account', '/logout', '/support', '/sms', '/admin'];

    public function __construct(private PDO $db, private string $root)
    {
    }

    public function run(): void
    {
        $method = Http::method();
        $path = Http::path();

        if ($method === 'GET' && $path === '/robots.txt') {
            $this->robots();
        } elseif ($method === 'GET' && $path === '/sitemap.xml') {
            $this->sitemap();
        } elseif ($method === 'GET' && $path === '/healthz') {
            Http::json([
                'ok' => true,
                'service' => 'familyshieldpro',
                'product' => Product::NAME,
                'app' => Product::APP,
                'version' => Product::version(),
                'channel' => Product::CHANNEL,
                'not' => 'InPmnt',
                'stripe' => Billing::config()['enabled'],
                'mail' => Mailer::configured(),
                'sms' => Sms::configured(),
                'openai' => SupportChat::openaiConfigured(),
                'admin' => Admin::configured(),
            ]);
        } elseif ($method === 'POST' && $path === '/sms/inbound') {
            $this->smsInbound();
        } elseif ($method === 'POST' && $path === '/support/chat') {
            $this->supportChat();
        } elseif ($method === 'GET' && $path === '/') {
            $this->landing();
        } elseif ($path === '/signup') {
            $this->signup();
        } elseif ($path === '/login/2fa') {
            $this->loginTwoFactor();
        } elseif ($path === '/login') {
            $this->login();
        } elseif ($path === '/forgot/code') {
            $this->forgotCode();
        } elseif ($path === '/forgot') {
            $this->forgot();
        } elseif (preg_match('#^/reset/([^/]+)$#', $path, $m)) {
            $this->resetPassword($m[1]);
        } elseif ($path === '/account/password' && $method === 'POST') {
            $this->accountPassword();
        } elseif ($path === '/account/phone' && $method === 'POST') {
            $this->accountPhone();
        } elseif ($path === '/account/2fa/setup') {
            $this->account2faSetup();
        } elseif ($path === '/account/2fa/enable' && $method === 'POST') {
            $this->account2faEnable();
        } elseif ($path === '/account/2fa/disable' && $method === 'POST') {
            $this->account2faDisable();
        } elseif ($path === '/account/2fa/recovery' && $method === 'POST') {
            $this->account2faRecovery();
        } elseif ($method === 'GET' && $path === '/account') {
            $this->account();
        } elseif ($method === 'GET' && $path === '/logout') {
            $_SESSION = [];
            session_destroy();
            Http::redirect('/');
        } elseif ($method === 'GET' && $path === '/home') {
            $this->home();
        } elseif ($method === 'POST' && $path === '/check') {
            $this->createCheck();
        } elseif ($method === 'GET' && preg_match('#^/checks/(\d+)$#', $path, $m)) {
            $this->showCheck((int) $m[1]);
        } elseif ($method === 'POST' && preg_match('#^/checks/(\d+)/review$#', $path, $m)) {
            $this->askReview((int) $m[1]);
        } elseif ($method === 'POST' && preg_match('#^/checks/(\d+)/review/reply$#', $path, $m)) {
            $this->replyReview((int) $m[1]);
        } elseif ($method === 'POST' && preg_match('#^/checks/(\d+)/alert$#', $path, $m)) {
            $this->sendAlert((int) $m[1]);
        } elseif ($method === 'GET' && preg_match('#^/uploads/(.+)$#', $path, $m)) {
            $this->upload($m[1]);
        } elseif ($path === '/circle') {
            $this->circle();
        } elseif ($path === '/circle/resend' && $method === 'POST') {
            $this->circleResend();
        } elseif (preg_match('#^/join/([^/]+)$#', $path, $m)) {
            $this->join($m[1]);
        } elseif ($path === '/trusted') {
            $this->trusted();
        } elseif ($method === 'POST' && preg_match('#^/trusted/(\d+)/delete$#', $path, $m)) {
            $this->deleteTrusted((int) $m[1]);
        } elseif ($method === 'GET' && $path === '/report') {
            $this->requireLogin();
            $this->page('report', ['title' => 'Report & recover']);
        } elseif ($method === 'POST' && $path === '/billing/webhook') {
            $this->stripeWebhook();
        } elseif ($method === 'GET' && $path === '/billing') {
            $this->billing();
        } elseif ($method === 'POST' && $path === '/billing/choose') {
            $this->choosePlan();
        } elseif ($method === 'POST' && $path === '/billing/portal') {
            $this->billingPortal();
        } elseif ($method === 'GET' && $path === '/billing/success') {
            $this->billingSuccess();
        } elseif (str_starts_with($path, '/admin')) {
            $this->adminDispatch($method, $path);
        } else {
            http_response_code(404);
            echo 'Not found';
        }
    }

    private function siteHome(): string
    {
        return rtrim(Env::get('OURCIRCLE_SITE_URL', Env::get('BASE_URL', 'https://familyshieldpro.com')), '/');
    }

    private function joinUrl(string $token): string
    {
        return $this->siteHome() . '/join/' . rawurlencode($token);
    }

    public static function inviteEmailBody(string $join): string
    {
        return "Someone invited you to a Family Shield Pro (OurCircle) family circle.\n\n"
            . "Open this link to join. It is only for this email:\n{$join}\n\n"
            . "If you did not expect this, ignore the message.\n\n"
            . Analyze::GUIDANCE . "\n";
    }

    private function parsePhoneField(string $raw): string
    {
        $text = trim($raw);
        if ($text === '') {
            return '';
        }
        $phone = Sms::normalizePhone($text);
        if ($phone === '') {
            throw new RuntimeException('That mobile number does not look like a US or international number.');
        }
        return $phone;
    }

    /** @param array<string,mixed> $inv */
    private function notifyInvite(array $inv, string $inviter): array
    {
        $join = $this->joinUrl((string) $inv['token']);
        $share = 'Share this join link: ' . $join;
        $emailed = false;
        $texted = false;
        $mailError = false;
        $mailFailDetail = '';
        $smsError = false;
        if (Mailer::configured()) {
            try {
                Mailer::send(
                    (string) $inv['email'],
                    'Join your family circle on Family Shield Pro',
                    self::inviteEmailBody($join)
                );
                $emailed = true;
            } catch (Throwable $e) {
                $mailError = true;
                $mailFailDetail = Mailer::lastStatus() !== '' ? Mailer::lastStatus() : $e->getMessage();
            }
        }
        $phone = trim((string) ($inv['phone'] ?? ''));
        if ($phone !== '' && Sms::configured()) {
            try {
                Sms::send($phone, Sms::inviteBody($join, $inviter));
                $texted = true;
            } catch (Throwable) {
                $smsError = true;
            }
        }
        if ($emailed) {
            Db::markInviteSent($this->db, (int) $inv['id']);
        }
        if ($texted) {
            Db::markInviteSmsSent($this->db, (int) $inv['id']);
        }
        $bits = [];
        if ($emailed) {
            $bits[] = 'emailed to ' . $inv['email'];
        }
        if ($texted) {
            $bits[] = 'texted to ' . $phone;
        }
        if ($bits) {
            $msg = 'Invite ' . implode(' and ', $bits) . '. If they do not see it, ' . lcfirst($share);
            $cat = ($mailError || $smsError) ? 'error' : 'ok';
            if ($mailError) {
                $msg .= ' Email did not send' . ($mailFailDetail !== '' ? ' (' . $mailFailDetail . ').' : '.');
            }
            if ($smsError) {
                $msg .= ' Text did not send.';
            }
            return [$msg, $cat];
        }
        if ($mailError || $smsError) {
            $why = $mailFailDetail !== '' ? ' Email: ' . $mailFailDetail . '.' : '';
            return ['Could not send the invite.' . $why . ' ' . $share, 'error'];
        }
        $extra = [];
        if (!Mailer::configured()) {
            $extra[] = 'Mail is not set up yet';
        }
        if ($phone !== '' && !Sms::configured()) {
            $extra[] = 'SMS is not set up yet';
        }
        if ($extra) {
            return ['Invite created for ' . $inv['email'] . '. ' . implode(' and ', $extra) . ', so ' . lcfirst($share), 'ok'];
        }
        return ['Invite created for ' . $inv['email'] . '. ' . $share, 'ok'];
    }

    private function user(): ?array
    {
        $uid = $_SESSION['user_id'] ?? null;
        $hid = $_SESSION['household_id'] ?? null;
        if (!$uid || !$hid) {
            return null;
        }
        return [
            'id' => (int) $uid,
            'household_id' => (int) $hid,
            'name' => (string) ($_SESSION['name'] ?? ''),
            'email' => (string) ($_SESSION['email'] ?? ''),
        ];
    }

    private function requireLogin(): array
    {
        $u = $this->user();
        if ($u) {
            if (!empty($_SESSION['just_joined'])) {
                unset($_SESSION['just_joined']);
                return $u;
            }
            Db::touchLastAccess($this->db, $u['id']);
            return $u;
        }
        $next = Http::path();
        Http::redirect('/login?next=' . rawurlencode($next));
    }

    private function flash(string $msg, string $cat = 'ok'): void
    {
        $_SESSION['flash'][] = [$cat, $msg];
    }

    private function takeFlashes(): array
    {
        $f = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return is_array($f) ? $f : [];
    }

    private function loginUser(array $user): void
    {
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['household_id'] = (int) $user['household_id'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['email'] = $user['email'];
    }

    private function page(string $view, array $vars = []): void
    {
        $this->maybeElevateAdmin();
        $vars['flashes'] = $vars['flashes'] ?? $this->takeFlashes();
        $vars['user_name'] = $this->user()['name'] ?? '';
        $vars['site_home'] = $this->siteHome();
        $vars['robots'] = $vars['robots'] ?? 'noindex,nofollow';
        $vars['admin_ok'] = !empty($_SESSION['admin_ok']) && Admin::configured();
        $vars['admin_configured'] = Admin::configured();
        $vars['mail_configured'] = Mailer::configured();
        View::render($view, $vars);
    }

    private function maybeElevateAdmin(): void
    {
        if (!empty($_SESSION['admin_ok']) || !Admin::configured()) {
            return;
        }
        $email = (string) ($_SESSION['email'] ?? '');
        if (Admin::emailIsAdmin($email)) {
            $_SESSION['admin_ok'] = true;
        }
    }

    private function adminNotFound(): never
    {
        http_response_code(404);
        echo 'Not found';
        exit;
    }

    private function requireAdmin(): void
    {
        if (!Admin::configured()) {
            $this->adminNotFound();
        }
        $this->maybeElevateAdmin();
        if (empty($_SESSION['admin_ok'])) {
            Http::redirect('/admin/login');
        }
    }

    private function adminDispatch(string $method, string $path): void
    {
        if (!Admin::configured()) {
            $this->adminNotFound();
        }
        if ($path === '/admin/login') {
            $this->adminLogin();
            return;
        }
        if ($path === '/admin/logout') {
            unset($_SESSION['admin_ok']);
            Http::redirect('/admin/login');
        }
        $this->requireAdmin();
        if ($path === '/admin' && $method === 'GET') {
            $this->adminHome();
        } elseif ($path === '/admin/users/create' && $method === 'POST') {
            $this->adminUserCreate();
        } elseif ($path === '/admin/users/delete' && $method === 'POST') {
            $this->adminUserDelete();
        } elseif ($path === '/admin/users/disable-2fa' && $method === 'POST') {
            $this->adminUserDisable2fa();
        } elseif (preg_match('#^/admin/users/(\d+)$#', $path, $m)) {
            $this->adminUser((int) $m[1]);
        } elseif ($path === '/admin/households/create' && $method === 'POST') {
            $this->adminHouseholdCreate();
        } elseif ($path === '/admin/households/delete' && $method === 'POST') {
            $this->adminHouseholdDelete();
        } elseif (preg_match('#^/admin/households/(\d+)$#', $path, $m)) {
            $this->adminHousehold((int) $m[1]);
        } elseif ($path === '/admin/invites/create' && $method === 'POST') {
            $this->adminInviteCreate();
        } elseif ($path === '/admin/invites/resend' && $method === 'POST') {
            $this->adminInviteResend();
        } elseif ($path === '/admin/invites/delete' && $method === 'POST') {
            $this->adminInviteDelete();
        } elseif ($path === '/admin/trusted/create' && $method === 'POST') {
            $this->adminTrustedCreate();
        } elseif ($path === '/admin/trusted/delete' && $method === 'POST') {
            $this->adminTrustedDelete();
        } elseif ($path === '/admin/checks/delete' && $method === 'POST') {
            $this->adminCheckDelete();
        } else {
            $this->adminNotFound();
        }
    }

    private function adminLogin(): void
    {
        $this->maybeElevateAdmin();
        if (!empty($_SESSION['admin_ok'])) {
            Http::redirect('/admin');
        }
        if (Http::method() === 'POST') {
            if (Admin::passwordOk((string) ($_POST['password'] ?? ''))) {
                $_SESSION['admin_ok'] = true;
                Http::redirect('/admin');
            }
            $this->flash('Operator password did not match.', 'error');
        }
        $this->page('admin_login', ['title' => 'Operator console · Family Shield Pro']);
    }

    private function adminHome(): void
    {
        $q = trim((string) ($_GET['q'] ?? ''));
        $this->page('admin', [
            'title' => 'Operator console · Family Shield Pro',
            'counts' => Db::adminCounts($this->db),
            'users' => Db::adminListUsers($this->db, $q),
            'invites' => Db::adminListInvites($this->db, $q),
            'households' => Db::adminListHouseholds($this->db, $q),
            'checks' => Db::adminListChecks($this->db, null, 20),
            'q' => $q,
            'mail_last_error' => Mailer::lastStatus(),
        ]);
    }

    private function adminBack(): never
    {
        $hid = (int) ($_POST['return_household'] ?? 0);
        if ($hid > 0) {
            Http::redirect('/admin/households/' . $hid);
        }
        $uid = (int) ($_POST['return_user'] ?? 0);
        if ($uid > 0) {
            Http::redirect('/admin/users/' . $uid);
        }
        Http::redirect('/admin');
    }

    private function adminUser(int $userId): void
    {
        if (Http::method() === 'POST') {
            try {
                $phone = $this->parsePhoneField((string) ($_POST['phone'] ?? ''));
                $hidRaw = trim((string) ($_POST['household_id'] ?? ''));
                $person = Db::adminUpdateUser(
                    $this->db,
                    $userId,
                    (string) ($_POST['name'] ?? ''),
                    (string) ($_POST['email'] ?? ''),
                    $phone,
                    (string) ($_POST['sms_opt_out'] ?? '') === '1',
                    (string) ($_POST['password'] ?? ''),
                    (string) ($_POST['role'] ?? '') !== '' ? (string) $_POST['role'] : null,
                    $hidRaw !== '' ? (int) $hidRaw : null
                );
                if ((int) ($_SESSION['user_id'] ?? 0) === (int) $person['id']) {
                    $_SESSION['name'] = $person['name'];
                    $_SESSION['email'] = $person['email'];
                    $_SESSION['household_id'] = $person['household_id'];
                }
                $this->flash('Login saved.', 'ok');
            } catch (RuntimeException $e) {
                $this->flash($e->getMessage(), 'error');
            }
            Http::redirect('/admin/users/' . $userId);
        }
        $person = Db::adminGetUser($this->db, $userId);
        if (!$person) {
            $this->flash('That login is not in Family Shield Pro.', 'error');
            Http::redirect('/admin');
        }
        $this->page('admin_user', [
            'title' => 'Edit ' . (string) $person['name'] . ' · Console',
            'person' => $person,
            'households' => Db::adminListHouseholds($this->db),
        ]);
    }

    private function adminUserCreate(): void
    {
        try {
            $phone = $this->parsePhoneField((string) ($_POST['phone'] ?? ''));
            $person = Db::adminCreateUser(
                $this->db,
                (int) ($_POST['household_id'] ?? 0),
                (string) ($_POST['name'] ?? ''),
                (string) ($_POST['email'] ?? ''),
                (string) ($_POST['password'] ?? ''),
                (string) ($_POST['role'] ?? 'member'),
                $phone
            );
            $this->flash('Login added: ' . $person['email'] . '.', 'ok');
        } catch (RuntimeException $e) {
            $this->flash($e->getMessage(), 'error');
        }
        $this->adminBack();
    }

    private function adminUserDelete(): void
    {
        $uid = (int) ($_POST['user_id'] ?? 0);
        try {
            Db::adminDeleteUser($this->db, $uid);
            if ((int) ($_SESSION['user_id'] ?? 0) === $uid) {
                unset($_SESSION['user_id'], $_SESSION['household_id'], $_SESSION['name'], $_SESSION['email']);
            }
            $this->flash('Login deleted.', 'ok');
        } catch (RuntimeException $e) {
            $this->flash($e->getMessage(), 'error');
        }
        $this->adminBack();
    }

    private function adminUserDisable2fa(): void
    {
        $uid = (int) ($_POST['user_id'] ?? 0);
        try {
            Db::adminDisable2fa($this->db, $uid);
            $this->flash('2FA turned off for that login.', 'ok');
        } catch (RuntimeException $e) {
            $this->flash($e->getMessage(), 'error');
        }
        Http::redirect($uid > 0 ? '/admin/users/' . $uid : '/admin');
    }

    private function adminHousehold(int $hid): void
    {
        if (Http::method() === 'POST') {
            try {
                Db::adminUpdateHousehold(
                    $this->db,
                    $hid,
                    (string) ($_POST['name'] ?? ''),
                    (string) ($_POST['plan'] ?? '')
                );
                $this->flash('Circle saved. Plan flag only — no Stripe charge.', 'ok');
            } catch (RuntimeException $e) {
                $this->flash($e->getMessage(), 'error');
            }
            $returnUser = (int) ($_POST['return_user'] ?? 0);
            if ($returnUser > 0) {
                Http::redirect('/admin/users/' . $returnUser);
            }
            Http::redirect('/admin/households/' . $hid);
        }
        $household = Db::adminHouseholdDetail($this->db, $hid);
        if (!$household) {
            $this->flash('That circle is not in Family Shield Pro.', 'error');
            Http::redirect('/admin');
        }
        [$members, $pending] = Db::decorateCircleStatus(Db::members($this->db, $hid), Db::pending($this->db, $hid));
        $this->page('admin_household', [
            'title' => (string) $household['name'] . ' · Console',
            'household' => $household,
            'members' => $members,
            'pending' => $pending,
            'trusted' => Db::trusted($this->db, $hid),
            'checks' => Db::adminListChecks($this->db, $hid),
        ]);
    }

    private function adminHouseholdCreate(): void
    {
        try {
            $phone = $this->parsePhoneField((string) ($_POST['phone'] ?? ''));
            $house = Db::adminCreateHousehold(
                $this->db,
                (string) ($_POST['name'] ?? ''),
                (string) ($_POST['plan'] ?? 'yearly'),
                (string) ($_POST['owner_name'] ?? ''),
                (string) ($_POST['owner_email'] ?? ''),
                (string) ($_POST['owner_password'] ?? ''),
                $phone
            );
            $this->flash('Circle added.', 'ok');
            Http::redirect('/admin/households/' . (int) $house['id']);
        } catch (RuntimeException $e) {
            $this->flash($e->getMessage(), 'error');
            Http::redirect('/admin');
        }
    }

    private function adminHouseholdDelete(): void
    {
        $hid = (int) ($_POST['household_id'] ?? 0);
        try {
            if ((int) ($_SESSION['household_id'] ?? 0) === $hid) {
                unset($_SESSION['user_id'], $_SESSION['household_id'], $_SESSION['name'], $_SESSION['email']);
            }
            Db::adminDeleteHousehold($this->db, $hid);
            $this->flash('Circle deleted.', 'ok');
        } catch (RuntimeException $e) {
            $this->flash($e->getMessage(), 'error');
        }
        Http::redirect('/admin');
    }

    private function adminInviteCreate(): void
    {
        try {
            $phone = $this->parsePhoneField((string) ($_POST['phone'] ?? ''));
            $inv = Db::adminCreateInvite(
                $this->db,
                (int) ($_POST['household_id'] ?? 0),
                (string) ($_POST['email'] ?? ''),
                (string) ($_POST['name'] ?? ''),
                $phone
            );
            [$msg, $cat] = $this->notifyInvite($inv, 'Family Shield Pro');
            $this->flash($msg, $cat);
        } catch (RuntimeException $e) {
            $this->flash($e->getMessage(), 'error');
        }
        $this->adminBack();
    }

    private function adminInviteResend(): void
    {
        $inviteId = (int) ($_POST['invite_id'] ?? 0);
        $inv = Db::pendingInviteById($this->db, $inviteId);
        if (!$inv) {
            $this->flash('That invite is not waiting anymore.', 'error');
            $this->adminBack();
        }
        [$msg, $cat] = $this->notifyInvite($inv, 'Family Shield Pro');
        $this->flash($msg, $cat);
        $this->adminBack();
    }

    private function adminInviteDelete(): void
    {
        $inviteId = (int) ($_POST['invite_id'] ?? 0);
        if (Db::cancelPendingInvite($this->db, $inviteId)) {
            $this->flash('Pending invite deleted.', 'ok');
        } else {
            $this->flash('That invite is not waiting anymore.', 'error');
        }
        $this->adminBack();
    }

    private function adminTrustedCreate(): void
    {
        try {
            Db::adminAddTrusted(
                $this->db,
                (int) ($_POST['household_id'] ?? 0),
                (string) ($_POST['kind'] ?? 'other'),
                (string) ($_POST['name'] ?? ''),
                (string) ($_POST['phone'] ?? ''),
                (string) ($_POST['website'] ?? ''),
                (string) ($_POST['notes'] ?? '')
            );
            $this->flash('Trusted contact saved.', 'ok');
        } catch (RuntimeException $e) {
            $this->flash($e->getMessage(), 'error');
        }
        $this->adminBack();
    }

    private function adminTrustedDelete(): void
    {
        $gone = Db::adminDeleteTrusted($this->db, (int) ($_POST['contact_id'] ?? 0));
        $this->flash($gone ? 'Trusted contact removed.' : 'That contact was already gone.', $gone ? 'ok' : 'error');
        $this->adminBack();
    }

    private function adminCheckDelete(): void
    {
        $gone = Db::adminDeleteCheck($this->db, (int) ($_POST['check_id'] ?? 0));
        $this->flash($gone ? 'Check deleted.' : 'That check was already gone.', $gone ? 'ok' : 'error');
        $this->adminBack();
    }

    private function robots(): never
    {
        $host = preg_replace('#^https?://#', '', $this->siteHome()) ?? 'familyshieldpro.com';
        $lines = ["User-agent: *", "Allow: /", "Allow: /signup", "Allow: /login", "Allow: /forgot"];
        foreach (self::PRIVATE as $p) {
            $lines[] = "Disallow: {$p}";
        }
        $lines[] = '';
        $lines[] = "Host: {$host}";
        $lines[] = 'Sitemap: ' . $this->siteHome() . '/sitemap.xml';
        $lines[] = '';
        header('Content-Type: text/plain; charset=utf-8');
        echo implode("\n", $lines);
        exit;
    }

    private function sitemap(): never
    {
        $last = gmdate('Y-m-d');
        $base = $this->siteHome();
        $urls = [
            ['/', 'weekly', '1.0'],
            ['/signup', 'weekly', '0.9'],
            ['/login', 'monthly', '0.6'],
            ['/forgot', 'monthly', '0.5'],
        ];
        $body = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as [$path, $freq, $pri]) {
            $loc = $path === '/' ? $base . '/' : $base . $path;
            $body .= "  <url>\n    <loc>{$loc}</loc>\n    <lastmod>{$last}</lastmod>\n"
                . "    <changefreq>{$freq}</changefreq>\n    <priority>{$pri}</priority>\n  </url>\n";
        }
        $body .= "</urlset>\n";
        header('Content-Type: application/xml; charset=utf-8');
        echo $body;
        exit;
    }

    private function supportChat(): never
    {
        $raw = file_get_contents('php://input') ?: '';
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            $data = [];
        }
        $message = trim((string) ($data['message'] ?? $_POST['message'] ?? ''));
        $history = $data['history'] ?? [];
        if (!is_array($history)) {
            $history = [];
        }
        $n = (int) ($_SESSION['support_chat_n'] ?? 0);
        $started = (int) ($_SESSION['support_chat_t'] ?? 0);
        $now = time();
        if ($started && ($now - $started) > 3600) {
            $n = 0;
            $started = $now;
        }
        if (!$started) {
            $started = $now;
        }
        if ($n >= 30) {
            Http::json([
                'reply' => 'Please email CustomerService@FamilyShieldPro.com — this chat has a short hourly limit.',
                'source' => 'limit',
            ], 429);
        }
        $_SESSION['support_chat_n'] = $n + 1;
        $_SESSION['support_chat_t'] = $started;
        Http::json(SupportChat::reply($message, $history));
    }

    private function landing(): void
    {
        if ($this->user()) {
            Http::redirect('/home');
        }
        $this->page('landing', [
            'title' => 'OurCircle — Pause. Ask family. Then pay.',
            'robots' => 'index,follow',
            'plans' => self::PLANS,
            'stripe_enabled' => Billing::config()['enabled'],
            'flashes' => $this->takeFlashes(),
        ]);
    }

    private function signup(): void
    {
        if (Http::method() === 'GET') {
            $this->page('signup', [
                'title' => 'Start a circle · OurCircle',
                'robots' => 'index,follow',
                'stripe_enabled' => Billing::config()['enabled'],
                'flashes' => $this->takeFlashes(),
            ]);
        }
        $name = trim((string) ($_POST['name'] ?? ''));
        $household = trim((string) ($_POST['household'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        if ($name === '' || !str_contains($email, '@') || strlen($password) < 8) {
            $this->flash('Name, email, and an 8+ character password are required.', 'error');
            $this->page('signup', [
                'title' => 'Start a circle · OurCircle',
                'robots' => 'index,follow',
                'stripe_enabled' => Billing::config()['enabled'],
                'flashes' => $this->takeFlashes(),
            ]);
        }
        try {
            $phone = $this->parsePhoneField((string) ($_POST['phone'] ?? ''));
        } catch (RuntimeException $e) {
            $this->flash($e->getMessage(), 'error');
            $this->page('signup', [
                'title' => 'Start a circle · OurCircle',
                'robots' => 'index,follow',
                'stripe_enabled' => Billing::config()['enabled'],
                'flashes' => $this->takeFlashes(),
            ]);
        }
        $st = $this->db->prepare('SELECT id FROM users WHERE lower(email)=?');
        $st->execute([$email]);
        if ($st->fetch()) {
            $this->flash('That email already has a login. Sign in instead.', 'error');
            Http::redirect('/login');
        }
        $hid = Db::createHousehold($this->db, $household !== '' ? $household : ($name . "'s circle"), $name, $email, $password, $phone);
        $u = $this->db->prepare('SELECT * FROM users WHERE lower(email)=?');
        $u->execute([$email]);
        $this->loginUser($u->fetch());
        $this->flash('Welcome. Add two trusted contacts, then invite someone who will pick up the phone.', 'ok');
        Http::redirect('/home');
    }

    private function login(): void
    {
        $next = Http::safeNext($_GET['next'] ?? $_POST['next'] ?? null) ?: '/home';
        if (Http::method() === 'GET') {
            $this->page('login', ['title' => 'Sign in · OurCircle', 'robots' => 'index,follow', 'next' => $next, 'flashes' => $this->takeFlashes()]);
        }
        $user = Db::authenticate($this->db, (string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''));
        if (!$user) {
            $this->flash('Email or password did not match.', 'error');
            $this->page('login', ['title' => 'Sign in · OurCircle', 'robots' => 'index,follow', 'next' => $next, 'flashes' => $this->takeFlashes()]);
        }
        if (Auth::totpOn($user)) {
            $_SESSION['pending_2fa'] = (int) $user['id'];
            $_SESSION['pending_2fa_tries'] = 0;
            $_SESSION['pending_next'] = $next;
            Http::redirect('/login/2fa');
        }
        $this->loginUser($user);
        Http::redirect($next);
    }

    private function userById(int $id): ?array
    {
        $st = $this->db->prepare('SELECT * FROM users WHERE id=?');
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    private function verifySecondFactor(array $user, string $code, string $recovery): bool
    {
        $secret = (string) ($user['totp_secret'] ?? '');
        if ($code !== '' && $secret !== '' && Auth::verifyTotp($secret, $code)) {
            return true;
        }
        if ($recovery === '') {
            return false;
        }
        $next = Auth::consumeRecovery((string) ($user['recovery_codes'] ?? ''), $recovery);
        if ($next === null) {
            return false;
        }
        $this->db->prepare('UPDATE users SET recovery_codes=? WHERE id=?')->execute([$next, $user['id']]);
        return true;
    }

    private function loginTwoFactor(): void
    {
        $uid = (int) ($_SESSION['pending_2fa'] ?? 0);
        if ($uid < 1) {
            Http::redirect('/login');
        }
        if (Http::method() === 'GET') {
            $this->page('login_2fa', ['title' => 'Two-factor · OurCircle', 'flashes' => $this->takeFlashes()]);
        }
        $user = $this->userById($uid);
        if (!$user) {
            unset($_SESSION['pending_2fa'], $_SESSION['pending_2fa_tries']);
            Http::redirect('/login');
        }
        $tries = (int) ($_SESSION['pending_2fa_tries'] ?? 0);
        if ($tries >= 8) {
            unset($_SESSION['pending_2fa'], $_SESSION['pending_2fa_tries'], $_SESSION['pending_next']);
            $this->flash('Too many codes. Sign in again.', 'error');
            Http::redirect('/login');
        }
        $code = trim((string) ($_POST['code'] ?? ''));
        $recovery = trim((string) ($_POST['recovery_code'] ?? ''));
        if (!$this->verifySecondFactor($user, $code, $recovery)) {
            $_SESSION['pending_2fa_tries'] = $tries + 1;
            $this->flash('That code did not match.', 'error');
            Http::redirect('/login/2fa');
        }
        $next = Http::safeNext((string) ($_SESSION['pending_next'] ?? '')) ?: '/home';
        unset($_SESSION['pending_2fa'], $_SESSION['pending_2fa_tries'], $_SESSION['pending_next']);
        $this->loginUser($user);
        Http::redirect($next);
    }

    private function forgot(): void
    {
        $generic = 'If that email is on a circle, we sent reset instructions. Check spam. You can also use a recovery code on this page.';
        if (Http::method() === 'GET') {
            $this->page('forgot', ['title' => 'Forgot password · OurCircle', 'robots' => 'index,follow', 'flashes' => $this->takeFlashes()]);
        }
        if (!Mailer::configured()) {
            $this->flash(Mailer::notSetupMessage(), 'error');
            Http::redirect('/forgot');
        }
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        if (str_contains($email, '@')) {
            $st = $this->db->prepare('SELECT * FROM users WHERE lower(email)=?');
            $st->execute([$email]);
            $user = $st->fetch();
            if ($user) {
                $tok = Auth::newResetToken();
                $this->db->prepare('DELETE FROM password_resets WHERE user_id=?')->execute([$user['id']]);
                $this->db->prepare(
                    'INSERT INTO password_resets (user_id, token_hash, expires_at, created_at) VALUES (?,?,?,?)'
                )->execute([$user['id'], $tok['hash'], gmdate('Y-m-d\TH:i:s\Z', time() + 3600), Db::now()]);
                $link = $this->siteHome() . '/reset/' . rawurlencode($tok['token']);
                $body = "Someone asked to reset the Family Shield Pro password for this email.\n\n"
                    . "Open this link within one hour:\n{$link}\n\n"
                    . "If you did not ask, ignore this message.\n";
                try {
                    Mailer::send((string) $user['email'], 'Reset your Family Shield Pro password', $body);
                } catch (Throwable $e) {
                    $detail = Mailer::lastStatus() !== '' ? Mailer::lastStatus() : $e->getMessage();
                    $this->flash(Mailer::sendFailedMessage($detail), 'error');
                    Http::redirect('/forgot');
                }
            }
        }
        $this->flash($generic, 'ok');
        Http::redirect('/forgot');
    }

    private function forgotCode(): void
    {
        if (Http::method() !== 'POST') {
            Http::redirect('/forgot');
        }
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $recovery = trim((string) ($_POST['recovery_code'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $generic = 'If that email and recovery code matched, the password is updated. Sign in.';
        if (str_contains($email, '@') && $recovery !== '' && strlen($password) >= 8) {
            $st = $this->db->prepare('SELECT * FROM users WHERE lower(email)=?');
            $st->execute([$email]);
            $user = $st->fetch();
            if ($user) {
                $next = Auth::consumeRecovery((string) ($user['recovery_codes'] ?? ''), $recovery);
                if ($next !== null) {
                    $this->db->prepare('UPDATE users SET password_hash=?, recovery_codes=? WHERE id=?')
                        ->execute([password_hash($password, PASSWORD_DEFAULT), $next, $user['id']]);
                    $this->db->prepare('DELETE FROM password_resets WHERE user_id=?')->execute([$user['id']]);
                }
            }
        }
        $this->flash($generic, 'ok');
        Http::redirect('/login');
    }

    private function resetPassword(string $token): void
    {
        $hash = hash('sha256', $token);
        $st = $this->db->prepare('SELECT * FROM password_resets WHERE token_hash=? AND expires_at >= ?');
        $st->execute([$hash, Db::now()]);
        $row = $st->fetch();
        if (!$row) {
            $this->flash('That reset link is invalid or expired. Request a new one.', 'error');
            Http::redirect('/forgot');
        }
        if (Http::method() === 'GET') {
            $this->page('reset', ['title' => 'Choose a new password · OurCircle', 'token' => $token, 'flashes' => $this->takeFlashes()]);
        }
        $password = (string) ($_POST['password'] ?? '');
        if (strlen($password) < 8) {
            $this->flash('Use at least 8 characters.', 'error');
            Http::redirect('/reset/' . rawurlencode($token));
        }
        $this->db->prepare('UPDATE users SET password_hash=? WHERE id=?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $row['user_id']]);
        $this->db->prepare('DELETE FROM password_resets WHERE user_id=?')->execute([$row['user_id']]);
        $this->flash('Password saved. Sign in. If 2FA is on, you still need the authenticator.', 'ok');
        Http::redirect('/login');
    }

    private function account(): void
    {
        $u = $this->requireLogin();
        $user = $this->userById((int) $u['id']);
        $codes = $_SESSION['show_recovery'] ?? [];
        unset($_SESSION['show_recovery']);
        $this->page('account', [
            'title' => 'Account',
            'totp_on' => Auth::totpOn($user),
            'recovery_codes' => is_array($codes) ? $codes : [],
            'phone' => (string) ($user['phone'] ?? ''),
            'sms_opt_out' => !empty($user['sms_opt_out']),
        ]);
    }

    private function accountPhone(): void
    {
        $u = $this->requireLogin();
        try {
            $phone = $this->parsePhoneField((string) ($_POST['phone'] ?? ''));
            Db::setUserPhone($this->db, (int) $u['id'], $phone, ((string) ($_POST['sms_opt_out'] ?? '')) === '1');
        } catch (RuntimeException $e) {
            $this->flash($e->getMessage(), 'error');
            Http::redirect('/account');
        }
        if ($phone !== '') {
            $this->flash('Mobile number saved. We can text invites and call-me alerts to this number.', 'ok');
        } else {
            $this->flash('Mobile number cleared.', 'ok');
        }
        Http::redirect('/account');
    }

    private function accountPassword(): void
    {
        $u = $this->requireLogin();
        $user = $this->userById((int) $u['id']);
        $current = (string) ($_POST['current_password'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        if (!$user || !password_verify($current, (string) $user['password_hash'])) {
            $this->flash('Current password did not match.', 'error');
            Http::redirect('/account');
        }
        if (strlen($password) < 8) {
            $this->flash('Use at least 8 characters.', 'error');
            Http::redirect('/account');
        }
        $this->db->prepare('UPDATE users SET password_hash=? WHERE id=?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        $this->flash('Password updated.', 'ok');
        Http::redirect('/account');
    }

    private function account2faSetup(): void
    {
        $u = $this->requireLogin();
        $user = $this->userById((int) $u['id']);
        if (Auth::totpOn($user)) {
            Http::redirect('/account');
        }
        if (Http::method() === 'POST' && (string) ($_POST['new_key'] ?? '') === '1') {
            unset($_SESSION['totp_pending_secret']);
        }
        if (empty($_SESSION['totp_pending_secret'])) {
            $_SESSION['totp_pending_secret'] = Auth::newSecret();
        }
        $secret = (string) $_SESSION['totp_pending_secret'];
        $this->page('account_2fa_setup', [
            'title' => 'Turn on 2FA',
            'secret_grouped' => Auth::groupSecret($secret),
            'otpauth' => Auth::otpauthUri((string) $u['email'], $secret),
        ]);
    }

    private function account2faEnable(): void
    {
        $u = $this->requireLogin();
        $secret = (string) ($_SESSION['totp_pending_secret'] ?? '');
        $code = trim((string) ($_POST['code'] ?? ''));
        if ($secret === '' || !Auth::verifyTotp($secret, $code)) {
            $this->flash('That code did not match. Scan the key again and retry.', 'error');
            Http::redirect('/account/2fa/setup');
        }
        $codes = Auth::newRecoveryCodes();
        $this->db->prepare('UPDATE users SET totp_secret=?, totp_enabled=1, recovery_codes=? WHERE id=?')
            ->execute([$secret, Auth::hashList($codes), $u['id']]);
        unset($_SESSION['totp_pending_secret']);
        $_SESSION['show_recovery'] = $codes;
        $this->flash('Two-factor authentication is on. Save the recovery codes.', 'ok');
        Http::redirect('/account');
    }

    private function account2faDisable(): void
    {
        $u = $this->requireLogin();
        $user = $this->userById((int) $u['id']);
        $code = trim((string) ($_POST['code'] ?? ''));
        if (!$user || !$this->verifySecondFactor($user, $code, $code)) {
            $this->flash('That code did not match.', 'error');
            Http::redirect('/account');
        }
        $this->db->prepare('UPDATE users SET totp_secret=NULL, totp_enabled=0, recovery_codes=NULL WHERE id=?')
            ->execute([$u['id']]);
        $this->flash('Two-factor authentication is off.', 'ok');
        Http::redirect('/account');
    }

    private function account2faRecovery(): void
    {
        $u = $this->requireLogin();
        $user = $this->userById((int) $u['id']);
        $code = trim((string) ($_POST['code'] ?? ''));
        $secret = (string) ($user['totp_secret'] ?? '');
        if (!$user || $secret === '' || !Auth::verifyTotp($secret, $code)) {
            $this->flash('That authenticator code did not match.', 'error');
            Http::redirect('/account');
        }
        $codes = Auth::newRecoveryCodes();
        $this->db->prepare('UPDATE users SET recovery_codes=? WHERE id=?')->execute([Auth::hashList($codes), $u['id']]);
        $_SESSION['show_recovery'] = $codes;
        $this->flash('New recovery codes — save them now.', 'ok');
        Http::redirect('/account');
    }

    private function home(): void
    {
        $u = $this->requireLogin();
        $hid = $u['household_id'];
        $checks = $this->db->prepare(
            'SELECT id, kind, risk, created_at, raw_text, phone, url FROM checks WHERE household_id=? ORDER BY id DESC LIMIT 8'
        );
        $checks->execute([$hid]);
        $alerts = $this->db->prepare('SELECT * FROM alerts WHERE household_id=? ORDER BY id DESC LIMIT 5');
        $alerts->execute([$hid]);
        [$members, $pending] = Db::decorateCircleStatus(
            Db::members($this->db, $hid),
            Db::pending($this->db, $hid)
        );
        $this->page('home', [
            'title' => 'Check this',
            'members' => $members,
            'pending' => $pending,
            'trusted' => Db::trusted($this->db, $hid),
            'checks' => $checks->fetchAll(),
            'alerts' => $alerts->fetchAll(),
        ]);
    }

    private function createCheck(): void
    {
        $u = $this->requireLogin();
        $text = trim((string) ($_POST['text'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $url = trim((string) ($_POST['url'] ?? ''));
        $shot = '';
        $file = $_FILES['screenshot'] ?? null;
        if (is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && ($file['name'] ?? '') !== '') {
            $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif'], true)) {
                $this->flash('Please upload a PNG, JPG, WEBP, or GIF screenshot.', 'error');
                Http::redirect('/home');
            }
            $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', (string) $file['name']) ?: 'shot';
            $shot = $u['household_id'] . '-' . $u['id'] . '-' . str_replace(':', '', Db::now()) . '-' . $safe;
            $destDir = $this->root . '/data/uploads';
            if (!is_dir($destDir)) {
                mkdir($destDir, 0775, true);
            }
            @chmod($destDir, 0775);
            move_uploaded_file($file['tmp_name'], $destDir . '/' . $shot);
            if ($text === '') {
                $text = '(Screenshot uploaded — describe what it says if you can.)';
            }
        }
        if ($text === '' && $phone === '' && $url === '') {
            $this->flash('Paste the message, a phone number, a website, or upload a screenshot.', 'error');
            Http::redirect('/home');
        }
        $report = Analyze::analyze($text, $phone, $url, Db::trusted($this->db, $u['household_id']));
        $kind = $shot !== '' ? 'screenshot' : (($phone !== '' && $text === '') ? 'phone' : 'message');
        $this->db->prepare(
            'INSERT INTO checks (household_id, user_id, kind, raw_text, phone, url, screenshot, risk, report_json, created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $u['household_id'], $u['id'], $kind, $text, $phone, $url, $shot, $report['level'],
            json_encode($report, JSON_UNESCAPED_SLASHES), Db::now(),
        ]);
        Http::redirect('/checks/' . $this->db->lastInsertId());
    }

    private function loadCheck(int $id, int $hid): array
    {
        $st = $this->db->prepare('SELECT * FROM checks WHERE id=? AND household_id=?');
        $st->execute([$id, $hid]);
        $row = $st->fetch();
        if (!$row) {
            http_response_code(404);
            echo 'Not found';
            exit;
        }
        return $row;
    }

    private function showCheck(int $id): void
    {
        $u = $this->requireLogin();
        $row = $this->loadCheck($id, $u['household_id']);
        $rev = $this->db->prepare('SELECT * FROM reviews WHERE check_id=? ORDER BY id DESC');
        $rev->execute([$id]);
        $this->page('check', [
            'title' => 'Check ' . $id,
            'item' => $row,
            'report' => json_decode((string) $row['report_json'], true) ?: [],
            'members' => Db::members($this->db, $u['household_id']),
            'reviews' => $rev->fetchAll(),
        ]);
    }

    private function askReview(int $id): void
    {
        $u = $this->requireLogin();
        $this->loadCheck($id, $u['household_id']);
        $comment = trim((string) ($_POST['comment'] ?? 'Please look at this with me before I do anything.'));
        $this->db->prepare(
            "INSERT INTO reviews (check_id, household_id, requester_id, comment, status, created_at) VALUES (?,?,?,?, 'asked', ?)"
        )->execute([$id, $u['household_id'], $u['id'], $comment, Db::now()]);
        $this->flash('Your circle can see this review request. Call them too if it feels urgent.', 'ok');
        Http::redirect('/checks/' . $id);
    }

    private function replyReview(int $id): void
    {
        $u = $this->requireLogin();
        $this->loadCheck($id, $u['household_id']);
        $comment = trim((string) ($_POST['reply'] ?? ''));
        $status = (string) ($_POST['status'] ?? 'looked');
        if (!in_array($status, ['looked', 'scam_likely', 'wait', 'call_me'], true)) {
            $status = 'looked';
        }
        if ($comment === '') {
            $this->flash('Add a short note for your family member.', 'error');
            Http::redirect('/checks/' . $id);
        }
        $this->db->prepare(
            'INSERT INTO reviews (check_id, household_id, requester_id, comment, status, created_at) VALUES (?,?,?,?,?,?)'
        )->execute([$id, $u['household_id'], $u['id'], $comment, $status, Db::now()]);
        $this->flash('Your note is on this check for the whole circle.', 'ok');
        Http::redirect('/checks/' . $id);
    }

    private function sendAlert(int $id): void
    {
        $u = $this->requireLogin();
        $this->loadCheck($id, $u['household_id']);
        $names = implode(', ', array_map(fn ($m) => $m['name'], Db::members($this->db, $u['household_id'])));
        $msg = 'PLEASE CALL ' . $u['name'] . ' BEFORE THEY PAY. They asked the circle (' . $names
            . ') to stop a payment or information request. Open OurCircle and look at check #' . $id . '.';
        $this->db->prepare(
            'INSERT INTO alerts (check_id, household_id, user_id, message, created_at) VALUES (?,?,?,?,?)'
        )->execute([$id, $u['household_id'], $u['id'], $msg, Db::now()]);
        $texted = 0;
        if (Sms::configured()) {
            $checkLink = $this->siteHome() . '/checks/' . $id;
            $body = Sms::alertBody($u['name'], $checkLink);
            foreach (Db::members($this->db, $u['household_id']) as $member) {
                if ((int) ($member['id'] ?? 0) === (int) $u['id']) {
                    continue;
                }
                $dest = trim((string) ($member['phone'] ?? ''));
                if ($dest === '' || !empty($member['sms_opt_out'])) {
                    continue;
                }
                try {
                    Sms::send($dest, $body);
                    $texted++;
                } catch (Throwable) {
                }
            }
        }
        $note = 'Urgent alert is on the circle home. Call them by voice if you can — do not rely on a banner alone.';
        if ($texted > 0) {
            $note .= ' Texted ' . $texted . ' circle member' . ($texted === 1 ? '' : 's') . '.';
        } elseif (Sms::configured()) {
            $note .= ' Nobody else in the circle has a mobile number on Account yet (or they opted out).';
        }
        $this->flash($note, 'ok');
        Http::redirect('/checks/' . $id);
    }

    private function upload(string $name): void
    {
        $this->requireLogin();
        $base = basename(str_replace('..', '', $name));
        $file = $this->root . '/data/uploads/' . $base;
        if (!is_file($file)) {
            http_response_code(404);
            echo 'Not found';
            exit;
        }
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $types = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'gif' => 'image/gif'];
        header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
        readfile($file);
        exit;
    }

    private function circle(): void
    {
        $u = $this->requireLogin();
        if (Http::method() === 'POST') {
            try {
                $requested = $this->parsePhoneField((string) ($_POST['phone'] ?? ''));
                $inv = Db::invite(
                    $this->db,
                    $u['household_id'],
                    (string) ($_POST['email'] ?? ''),
                    (string) ($_POST['name'] ?? ''),
                    $requested
                );
                [$msg, $cat] = $this->notifyInvite($inv, $u['name']);
                if ($requested !== '' && trim((string) ($inv['phone'] ?? '')) === '') {
                    $msg .= ' We did not attach that mobile — it is already on a Family Shield Pro login. They can add theirs on Account after they join.';
                }
                $this->flash($msg, $cat);
            } catch (RuntimeException $e) {
                $this->flash($e->getMessage(), 'error');
            }
            Http::redirect('/circle');
        }
        $alerts = $this->db->prepare('SELECT * FROM alerts WHERE household_id=? ORDER BY id DESC LIMIT 12');
        $alerts->execute([$u['household_id']]);
        [$members, $pending] = Db::decorateCircleStatus(
            Db::members($this->db, $u['household_id']),
            Db::pending($this->db, $u['household_id'])
        );
        $this->page('circle', [
            'title' => 'Family circle',
            'members' => $members,
            'pending' => $pending,
            'alerts' => $alerts->fetchAll(),
        ]);
    }

    private function circleResend(): void
    {
        $u = $this->requireLogin();
        $inviteId = (int) ($_POST['invite_id'] ?? 0);
        $inv = Db::pendingInvite($this->db, $u['household_id'], $inviteId);
        if (!$inv) {
            $this->flash('That invite is not waiting anymore.', 'error');
            Http::redirect('/circle');
        }
        [$msg, $cat] = $this->notifyInvite($inv, $u['name']);
        $this->flash($msg, $cat);
        Http::redirect('/circle');
    }

    private function smsInbound(): never
    {
        if (!Sms::configured()) {
            Http::xml(Sms::twiml('SMS is not configured.'), 503);
        }
        $params = [];
        foreach ($_POST as $k => $v) {
            if (is_string($k) && is_string($v)) {
                $params[$k] = $v;
            }
        }
        $sig = (string) ($_SERVER['HTTP_X_TWILIO_SIGNATURE'] ?? '');
        if (!Sms::validSignature($this->siteHome() . '/sms/inbound', $params, $sig)) {
            Http::xml(Sms::twiml('Forbidden'), 403);
        }
        $from = Sms::normalizePhone((string) ($_POST['From'] ?? ''));
        $body = trim((string) ($_POST['Body'] ?? ''));
        $action = Sms::classifyInbound($body);
        $auto = Sms::inboundAutoReply($action);
        $user = $from !== '' ? Db::userByPhone($this->db, $from) : null;
        $pending = $user ? null : Db::pendingByPhone($this->db, $from);
        if ($action === 'stop') {
            if ($user) {
                Db::setSmsOptOut($this->db, (int) $user['id'], true);
            }
            Http::xml(Sms::twiml($auto));
        }
        if ($action === 'start') {
            if ($user) {
                Db::setSmsOptOut($this->db, (int) $user['id'], false);
            } elseif (!$pending) {
                $auto = 'Family Shield Pro: this number is not in a circle yet. Ask a family member to invite you from Circle (email plus this phone). ' . Analyze::CORE_RULE . ' Reply STOP to opt out.';
            }
            Http::xml(Sms::twiml($auto));
        }
        if ($action === 'help') {
            Http::xml(Sms::twiml($auto));
        }
        if (!$user) {
            if ($pending) {
                $join = $this->joinUrl((string) $pending['token']);
                Http::xml(Sms::twiml(
                    'Family Shield Pro: join your circle first: ' . $join
                    . ' Then you can forward a sketchy text here. ' . Analyze::CORE_RULE . ' Reply STOP to opt out.'
                ));
            }
            Http::xml(Sms::twiml(
                'Family Shield Pro: this number is not in a circle yet. Ask a family member to invite you from Circle (email plus this phone). '
                . Analyze::CORE_RULE . ' Reply STOP to opt out.'
            ));
        }
        if (!empty($user['sms_opt_out'])) {
            Http::xml(Sms::twiml(Sms::inboundAutoReply('stop')));
        }
        $report = Analyze::analyze($body, '', '', Db::trusted($this->db, (int) $user['household_id']));
        $this->db->prepare(
            'INSERT INTO checks (household_id, user_id, kind, raw_text, phone, url, screenshot, risk, report_json, created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $user['household_id'],
            $user['id'],
            'sms',
            $body,
            $from,
            '',
            '',
            $report['level'],
            json_encode($report),
            Db::now(),
        ]);
        $cid = (int) $this->db->lastInsertId();
        $title = (string) ($report['title'] ?? 'Pause with your circle.');
        Http::xml(Sms::twiml(Sms::checkBody($title, $this->siteHome() . '/checks/' . $cid)));
    }

    private function join(string $token): void
    {
        if (Http::method() === 'GET') {
            $st = $this->db->prepare("SELECT * FROM invitations WHERE token=? AND status='pending'");
            $st->execute([$token]);
            $inv = $st->fetch();
            if (!$inv) {
                $this->flash('That invite is expired or already used.', 'error');
                Http::redirect('/login');
            }
            $this->page('join', [
                'title' => 'Join a circle · OurCircle',
                'robots' => 'noindex,nofollow',
                'invite' => $inv,
                'token' => $token,
                'flashes' => $this->takeFlashes(),
            ]);
        }
        $name = trim((string) ($_POST['name'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        if ($name === '' || strlen($password) < 8) {
            $this->flash('Name and an 8+ character password are required.', 'error');
            $this->page('join', [
                'title' => 'Join a circle · OurCircle',
                'robots' => 'noindex,nofollow',
                'invite' => ['email' => '', 'name' => ''],
                'token' => $token,
                'flashes' => $this->takeFlashes(),
            ]);
        }
        try {
            $phone = $this->parsePhoneField((string) ($_POST['phone'] ?? ''));
            $user = Db::acceptInvite($this->db, $token, $name, $password, $phone);
        } catch (RuntimeException $e) {
            $this->flash($e->getMessage(), 'error');
            Http::redirect('/login');
        }
        $_SESSION['just_joined'] = 1;
        $this->loginUser($user);
        $this->flash('You are in the circle. If someone asks you to look, pause with them — do not rush.', 'ok');
        Http::redirect('/home');
    }

    private function trusted(): void
    {
        $u = $this->requireLogin();
        if (Http::method() === 'POST') {
            $kind = trim((string) ($_POST['kind'] ?? 'other'));
            if (!in_array($kind, ['bank', 'doctor', 'insurer', 'utility', 'family', 'other'], true)) {
                $kind = 'other';
            }
            $name = trim((string) ($_POST['name'] ?? ''));
            if ($name === '') {
                $this->flash('Give this contact a name you will recognize.', 'error');
                Http::redirect('/trusted');
            }
            $this->db->prepare(
                'INSERT INTO trusted_contacts (household_id, kind, name, phone, website, notes, created_at) VALUES (?,?,?,?,?,?,?)'
            )->execute([
                $u['household_id'], $kind, $name,
                trim((string) ($_POST['phone'] ?? '')),
                trim((string) ($_POST['website'] ?? '')),
                trim((string) ($_POST['notes'] ?? '')),
                Db::now(),
            ]);
            $this->flash('Saved on your protected list. Prefer numbers from statements and cards, not from unexpected texts.', 'ok');
            Http::redirect('/trusted');
        }
        $this->page('trusted', ['title' => 'Trusted list', 'rows' => Db::trusted($this->db, $u['household_id'])]);
    }

    private function deleteTrusted(int $id): void
    {
        $u = $this->requireLogin();
        $this->db->prepare('DELETE FROM trusted_contacts WHERE id=? AND household_id=?')->execute([$id, $u['household_id']]);
        $this->flash('Removed from the trusted list.', 'ok');
        Http::redirect('/trusted');
    }

    private function billing(): void
    {
        $u = $this->requireLogin();
        $st = $this->db->prepare('SELECT * FROM households WHERE id=?');
        $st->execute([$u['household_id']]);
        $this->page('billing', [
            'title' => 'Plans',
            'plans' => self::PLANS,
            'household' => $st->fetch() ?: [],
            'stripe_enabled' => Billing::config()['enabled'],
        ]);
    }

    private function choosePlan(): void
    {
        $u = $this->requireLogin();
        $plan = trim((string) ($_POST['plan'] ?? ''));
        if (!in_array($plan, ['monthly', 'yearly'], true)) {
            $this->flash('Choose Family monthly or Family yearly.', 'error');
            Http::redirect('/billing');
        }
        $cfg = Billing::config();
        if ($cfg['enabled']) {
            $st = $this->db->prepare('SELECT * FROM households WHERE id=?');
            $st->execute([$u['household_id']]);
            $hh = $st->fetch() ?: [];
            try {
                $sess = Billing::createCheckout([
                    'plan' => $plan,
                    'customer_email' => $u['email'],
                    'household_id' => $u['household_id'],
                    'user_id' => $u['id'],
                    'customer_id' => $hh['stripe_customer_id'] ?? null,
                ]);
            } catch (Throwable $e) {
                $this->flash('Stripe could not start checkout: ' . $e->getMessage(), 'error');
                Http::redirect('/billing');
            }
            $url = $sess['url'] ?? '';
            if ($url === '') {
                $this->flash('Stripe did not return a checkout URL.', 'error');
                Http::redirect('/billing');
            }
            Http::redirect($url);
        }
        $this->db->prepare('UPDATE households SET plan=?, founding=0 WHERE id=?')->execute([$plan, $u['household_id']]);
        $label = $plan === 'monthly' ? '$14.99/month' : '$119.99/year';
        $this->flash("This circle is on Family {$plan} ({$label}). Add Stripe keys to .env to charge a card.", 'ok');
        Http::redirect('/billing');
    }

    private function billingPortal(): void
    {
        $u = $this->requireLogin();
        $cfg = Billing::config();
        if (!$cfg['enabled']) {
            $this->flash('Stripe is not configured yet. Add keys to .env (see STRIPE.md).', 'error');
            Http::redirect('/billing');
        }
        $st = $this->db->prepare('SELECT * FROM households WHERE id=?');
        $st->execute([$u['household_id']]);
        $hh = $st->fetch() ?: [];
        $cid = (string) ($hh['stripe_customer_id'] ?? '');
        if ($cid === '') {
            $this->flash('No Stripe customer yet — choose a plan and pay first.', 'error');
            Http::redirect('/billing');
        }
        try {
            $sess = Billing::createPortal($cid);
        } catch (Throwable $e) {
            $this->flash('Stripe portal: ' . $e->getMessage(), 'error');
            Http::redirect('/billing');
        }
        $url = $sess['url'] ?? '';
        if ($url === '') {
            $this->flash('Stripe did not return a portal URL. Enable Customer portal in the Stripe Dashboard.', 'error');
            Http::redirect('/billing');
        }
        Http::redirect($url);
    }

    private function billingSuccess(): void
    {
        $this->requireLogin();
        $sessionId = trim((string) ($_GET['session_id'] ?? ''));
        $cfg = Billing::config();
        if ($sessionId !== '' && $cfg['enabled']) {
            try {
                $checkout = Billing::retrieveCheckout($sessionId);
                $this->applyCheckoutSession($checkout);
                $this->flash('Payment received. This circle is on a paid Family plan.', 'ok');
            } catch (Throwable $e) {
                $this->flash('Paid, but we could not read the Stripe session yet. The webhook will finish this.', 'error');
            }
        }
        Http::redirect('/billing');
    }

    private function stripeWebhook(): void
    {
        $cfg = Billing::config();
        $payload = file_get_contents('php://input') ?: '';
        $sig = (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');
        if ($cfg['secret_key'] === '' || !Billing::configuredValue($cfg['secret_key'], 'sk_', 20)) {
            Http::json(['error' => 'Stripe not configured'], 503);
        }
        $wh = $cfg['webhook_secret'];
        if (!Billing::configuredValue($wh, 'whsec_', 20)) {
            Http::json(['error' => 'STRIPE_WEBHOOK_SECRET is required'], 503);
        }
        try {
            $event = Billing::constructEvent($payload, $sig, $wh);
        } catch (Throwable $e) {
            Http::json(['error' => $e->getMessage()], 400);
        }
        $etype = (string) ($event['type'] ?? '');
        $obj = is_array($event['data']['object'] ?? null) ? $event['data']['object'] : [];
        if ($etype === 'checkout.session.completed') {
            $this->applyCheckoutSession($obj);
        } elseif (in_array($etype, ['customer.subscription.updated', 'customer.subscription.created'], true)) {
            $this->applySubscription($obj);
        } elseif ($etype === 'customer.subscription.deleted') {
            $this->db->prepare(
                "UPDATE households SET stripe_subscription_id=NULL, stripe_status='canceled' WHERE stripe_customer_id=?"
            )->execute([$obj['customer'] ?? null]);
        }
        Http::json(['received' => true]);
    }

    private function resolveHouseholdId(array $meta, mixed $reference, mixed $customer): ?int
    {
        $hid = $meta['household_id'] ?? null;
        if ($hid !== null && trim((string) $hid) !== '') {
            return (int) $hid;
        }
        if ($reference !== null && trim((string) $reference) !== '') {
            return (int) $reference;
        }
        if ($customer) {
            $st = $this->db->prepare('SELECT id FROM households WHERE stripe_customer_id=?');
            $st->execute([(string) $customer]);
            $row = $st->fetch();
            if ($row) {
                return (int) $row['id'];
            }
        }
        return null;
    }

    private function applyCheckoutSession(array $checkout): void
    {
        $customer = $checkout['customer'] ?? null;
        if (is_array($customer)) {
            $customer = $customer['id'] ?? null;
        }
        $subscription = $checkout['subscription'] ?? null;
        $subId = is_string($subscription) ? $subscription : (is_array($subscription) ? ($subscription['id'] ?? null) : null);
        $meta = is_array($checkout['metadata'] ?? null) ? $checkout['metadata'] : [];
        $plan = is_array($meta) ? ($meta['plan'] ?? null) : null;
        if (!$plan && is_array($subscription)) {
            $plan = $this->planFromSubscription($subscription);
        }
        if (!in_array($plan, ['monthly', 'yearly'], true)) {
            $plan = 'yearly';
        }
        $hid = $this->resolveHouseholdId($meta, $checkout['client_reference_id'] ?? null, $customer);
        if ($hid === null) {
            return;
        }
        $this->db->prepare(
            'UPDATE households SET plan=?, founding=0, stripe_customer_id=COALESCE(?, stripe_customer_id),
                stripe_subscription_id=COALESCE(?, stripe_subscription_id), stripe_status=? WHERE id=?'
        )->execute([$plan, $customer, $subId, 'active', $hid]);
    }

    private function applySubscription(array $sub): void
    {
        $customer = $sub['customer'] ?? null;
        if (is_array($customer)) {
            $customer = $customer['id'] ?? null;
        }
        $subId = $sub['id'] ?? null;
        $plan = $this->planFromSubscription($sub);
        if (!in_array($plan, ['monthly', 'yearly'], true)) {
            $plan = 'yearly';
        }
        $status = (string) ($sub['status'] ?? '');
        $meta = is_array($sub['metadata'] ?? null) ? $sub['metadata'] : [];
        if (in_array($status, ['canceled', 'unpaid', 'incomplete_expired'], true)) {
            $this->db->prepare(
                "UPDATE households SET stripe_subscription_id=NULL, stripe_status=? WHERE stripe_customer_id=?"
            )->execute([$status, $customer]);
            return;
        }
        $hid = $this->resolveHouseholdId($meta, $meta['household_id'] ?? null, $customer);
        if ($hid === null) {
            return;
        }
        $this->db->prepare(
            'UPDATE households SET plan=?, stripe_customer_id=?, stripe_subscription_id=?, stripe_status=? WHERE id=?'
        )->execute([$plan, $customer, $subId, $status !== '' ? $status : 'active', $hid]);
    }

    private function planFromSubscription(array $sub): ?string
    {
        $data = $sub['items']['data'] ?? null;
        if (is_array($data) && $data) {
            $price = $data[0]['price'] ?? null;
            $priceId = is_string($price) ? $price : (is_array($price) ? ($price['id'] ?? null) : null);
            $fromPrice = Billing::planFromPriceId($priceId);
            if ($fromPrice) {
                return $fromPrice;
            }
        }
        $meta = is_array($sub['metadata'] ?? null) ? $sub['metadata'] : [];
        $plan = $meta['plan'] ?? null;
        return is_string($plan) ? $plan : null;
    }
}
