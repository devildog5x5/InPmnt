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

    public const PRIVATE = ['/home', '/circle', '/trusted', '/checks', '/uploads', '/join', '/billing', '/report', '/logout'];

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
            ]);
        } elseif ($method === 'GET' && $path === '/') {
            $this->landing();
        } elseif ($path === '/offers') {
            $this->offers();
        } elseif ($path === '/signup') {
            $this->signup();
        } elseif ($path === '/login') {
            $this->login();
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
        } elseif (preg_match('#^/join/([^/]+)$#', $path, $m)) {
            $this->join($m[1]);
        } elseif ($path === '/trusted') {
            $this->trusted();
        } elseif ($method === 'POST' && preg_match('#^/trusted/(\d+)/delete$#', $path, $m)) {
            $this->deleteTrusted((int) $m[1]);
        } elseif ($method === 'GET' && $path === '/report') {
            $this->requireLogin();
            $this->page('report', ['title' => 'Report & recover']);
        } elseif ($method === 'GET' && $path === '/billing') {
            $this->billing();
        } elseif ($method === 'POST' && $path === '/billing/choose') {
            $this->choosePlan();
        } else {
            http_response_code(404);
            echo 'Not found';
        }
    }

    private function siteHome(): string
    {
        return rtrim(Env::get('OURCIRCLE_SITE_URL', Env::get('BASE_URL', 'https://familyshieldpro.com')), '/');
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
        $vars['flashes'] = $vars['flashes'] ?? $this->takeFlashes();
        $vars['user_name'] = $this->user()['name'] ?? '';
        $vars['site_home'] = $this->siteHome();
        $vars['robots'] = $vars['robots'] ?? 'noindex,nofollow';
        View::render($view, $vars);
    }

    private function robots(): never
    {
        $host = preg_replace('#^https?://#', '', $this->siteHome()) ?? 'familyshieldpro.com';
        $lines = ["User-agent: *", "Allow: /", "Allow: /signup", "Allow: /login", "Allow: /offers"];
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
            ['/offers', 'weekly', '0.8'],
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

    private function landing(): void
    {
        if ($this->user()) {
            Http::redirect('/home');
        }
        $this->page('landing', [
            'title' => 'OurCircle — Pause. Ask family. Then pay.',
            'robots' => 'index,follow',
            'plans' => self::PLANS,
            'flashes' => $this->takeFlashes(),
        ]);
    }

    private function offers(): void
    {
        if (Http::method() === 'POST') {
            $product = trim((string) ($_POST['product'] ?? ''));
            $name = trim((string) ($_POST['name'] ?? ''));
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $offer = trim((string) ($_POST['offer'] ?? ''));
            $note = trim((string) ($_POST['note'] ?? ''));
            if (!in_array($product, ['inpmnt', 'vendorready', 'ourcircle'], true) || $name === '' || !str_contains($email, '@')) {
                $this->flash('Please choose a product and leave a real name and email.', 'error');
                Http::redirect('/offers');
            }
            $this->db->prepare(
                'INSERT INTO reservations (product, name, email, offer, note, created_at) VALUES (?,?,?,?,?,?)'
            )->execute([$product, $name, $email, $offer !== '' ? $offer : 'family year', $note, Db::now()]);
            $this->flash('Reservation saved. This is a refundable hold, not a charge. We will email you before anything is billed.', 'ok');
            Http::redirect('/offers');
        }
        $this->page('offers', [
            'title' => 'Seven-day paid validation · Foster',
            'robots' => 'index,follow',
            'flashes' => $this->takeFlashes(),
        ]);
    }

    private function signup(): void
    {
        if (Http::method() === 'GET') {
            $this->page('signup', ['title' => 'Start a circle · OurCircle', 'robots' => 'index,follow', 'flashes' => $this->takeFlashes()]);
        }
        $name = trim((string) ($_POST['name'] ?? ''));
        $household = trim((string) ($_POST['household'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        if ($name === '' || !str_contains($email, '@') || strlen($password) < 8) {
            $this->flash('Name, email, and an 8+ character password are required.', 'error');
            $this->page('signup', ['title' => 'Start a circle · OurCircle', 'robots' => 'index,follow', 'flashes' => $this->takeFlashes()]);
        }
        $st = $this->db->prepare('SELECT id FROM users WHERE lower(email)=?');
        $st->execute([$email]);
        if ($st->fetch()) {
            $this->flash('That email already has a login. Sign in instead.', 'error');
            Http::redirect('/login');
        }
        $hid = Db::createHousehold($this->db, $household !== '' ? $household : ($name . "'s circle"), $name, $email, $password);
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
        $this->loginUser($user);
        Http::redirect($next);
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
        $this->page('home', [
            'title' => 'Check this',
            'members' => Db::members($this->db, $hid),
            'pending' => Db::pending($this->db, $hid),
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
        $this->flash('Urgent alert is on the circle home. Call them by voice if you can — do not rely on a banner alone.', 'ok');
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
                $inv = Db::invite($this->db, $u['household_id'], (string) ($_POST['email'] ?? ''), (string) ($_POST['name'] ?? ''));
                $join = $this->siteHome() . '/join/' . rawurlencode($inv['token']);
                $this->flash('Invite created for ' . $inv['email'] . '. Share this join link: ' . $join, 'ok');
            } catch (RuntimeException $e) {
                $this->flash($e->getMessage(), 'error');
            }
            Http::redirect('/circle');
        }
        $alerts = $this->db->prepare('SELECT * FROM alerts WHERE household_id=? ORDER BY id DESC LIMIT 12');
        $alerts->execute([$u['household_id']]);
        $this->page('circle', [
            'title' => 'Family circle',
            'members' => Db::members($this->db, $u['household_id']),
            'pending' => Db::pending($this->db, $u['household_id']),
            'alerts' => $alerts->fetchAll(),
        ]);
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
            $user = Db::acceptInvite($this->db, $token, $name, $password);
        } catch (RuntimeException $e) {
            $this->flash($e->getMessage(), 'error');
            Http::redirect('/login');
        }
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
        $this->db->prepare('UPDATE households SET plan=?, founding=0 WHERE id=?')->execute([$plan, $u['household_id']]);
        $label = $plan === 'monthly' ? '$14.99/month' : '$119.99/year';
        $this->flash("This circle is on Family {$plan} ({$label}). No card is charged in this build — this is the plan flag.", 'ok');
        Http::redirect('/billing');
    }
}
