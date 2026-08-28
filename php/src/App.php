<?php
declare(strict_types=1);

final class App
{
    public function __construct(private PDO $db)
    {
    }

    public function run(): void
    {
        $this->loadUser();
        $method = Http::method();
        $path = Http::path();

        if ($method === 'GET' && $path === '/') {
            $this->landing();
        } elseif ($path === '/login') {
            $this->login();
        } elseif ($path === '/signup') {
            $this->signup();
        } elseif ($method === 'GET' && $path === '/logout') {
            $_SESSION = [];
            session_destroy();
            Http::redirect('/');
        } elseif ($method === 'GET' && ($path === '/app' || str_starts_with($path, '/app/'))) {
            $this->requireLogin();
            $this->view('app', ['user' => $GLOBALS['inpmnt_user']]);
        } elseif ($method === 'GET' && $path === '/billing/success') {
            $this->billingSuccess();
        } elseif ($method === 'POST' && $path === '/api/billing/webhook') {
            $this->stripeWebhook();
        } elseif (str_starts_with($path, '/api/')) {
            $this->api($method, $path);
        } else {
            http_response_code(404);
            echo 'Not found';
        }
    }

    private function loadUser(): void
    {
        $GLOBALS['inpmnt_user'] = null;
        $uid = $_SESSION['user_id'] ?? null;
        if (!$uid) {
            return;
        }
        $st = $this->db->prepare('SELECT id, email, name, workspace_id FROM users WHERE id = ?');
        $st->execute([(int) $uid]);
        $row = $st->fetch();
        $GLOBALS['inpmnt_user'] = $row ?: null;
        if (!$row) {
            unset($_SESSION['user_id']);
        }
    }

    private function requireLogin(bool $api = false): void
    {
        if (!empty($GLOBALS['inpmnt_user'])) {
            return;
        }
        if ($api || str_starts_with(Http::path(), '/api/')) {
            Http::json(['error' => 'Unauthorized'], 401);
        }
        Http::redirect('/login');
    }

    private function showDemoLogin(): bool
    {
        return Env::truthy('SHOW_DEMO_LOGIN');
    }

    private function view(string $name, array $vars = []): void
    {
        extract($vars, EXTR_SKIP);
        $root = dirname(__DIR__);
        require $root . '/views/' . $name . '.php';
        exit;
    }

    private function landing(): void
    {
        if (!empty($_SESSION['user_id'])) {
            Http::redirect('/app');
        }
        $cfg = Billing::config();
        $this->view('landing', [
            'stripe_enabled' => $cfg['enabled'],
            'show_demo_login' => $this->showDemoLogin(),
        ]);
    }

    private function login(): void
    {
        $next = Http::safeNext($_GET['next'] ?? $_POST['next'] ?? null);
        if (!empty($_SESSION['user_id'])) {
            Http::redirect($next ?: '/app');
        }
        $error = null;
        if (Http::method() === 'POST') {
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $password = (string) ($_POST['password'] ?? '');
            $st = $this->db->prepare('SELECT * FROM users WHERE lower(email) = ?');
            $st->execute([$email]);
            $user = $st->fetch();
            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = (int) $user['id'];
                Http::redirect($next ?: '/app');
            }
            $error = 'Invalid email or password.';
        }
        $this->view('login', [
            'error' => $error,
            'next' => $next ?: '',
            'show_demo_login' => $this->showDemoLogin(),
        ]);
    }

    private function signup(): void
    {
        if (!empty($_SESSION['user_id'])) {
            Http::redirect('/app');
        }
        $error = null;
        if (Http::method() === 'POST') {
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $name = trim((string) ($_POST['name'] ?? ''));
            $business = trim((string) ($_POST['business_name'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            if ($email === '' || !str_contains($email, '@')) {
                $error = 'Enter a valid email.';
            } elseif (in_array($email, Db::RESERVED_SIGNUP, true)) {
                $error = 'That email is reserved. Choose another or log in.';
            } elseif (strlen($password) < 8) {
                $error = 'Password must be at least 8 characters.';
            } elseif ($name === '') {
                $error = 'Name is required.';
            } else {
                try {
                    $this->db->beginTransaction();
                    $st = $this->db->prepare('SELECT id FROM users WHERE lower(email)=?');
                    $st->execute([$email]);
                    if ($st->fetch()) {
                        $this->db->rollBack();
                        $error = 'That email is already registered. Log in instead.';
                    } else {
                        [$uid] = Db::createWorkspace(
                            $this->db,
                            $email,
                            $name,
                            password_hash($password, PASSWORD_DEFAULT),
                            $business !== '' ? $business : null
                        );
                        $this->db->commit();
                        $_SESSION['user_id'] = $uid;
                        Http::redirect('/app');
                    }
                } catch (Throwable $e) {
                    if ($this->db->inTransaction()) {
                        $this->db->rollBack();
                    }
                    $error = $e->getMessage();
                }
            }
        }
        $this->view('signup', ['error' => $error]);
    }

    private function api(string $method, string $path): void
    {
        $this->requireLogin(true);
        $wid = Workspace::requireId();

        if ($method === 'GET' && $path === '/api/me') {
            $settings = Workspace::settings($this->db, $wid);
            Http::json(['user' => $GLOBALS['inpmnt_user'], 'settings' => $settings]);
        }
        if ($method === 'GET' && $path === '/api/dashboard') {
            $this->apiDashboard($wid);
        }
        if ($method === 'GET' && $path === '/api/clients') {
            $this->apiClients($wid);
        }
        if ($method === 'POST' && $path === '/api/clients') {
            $this->apiCreateClient($wid);
        }
        if (preg_match('#^/api/clients/(\d+)$#', $path, $m)) {
            if ($method === 'PUT') {
                $this->apiUpdateClient($wid, (int) $m[1]);
            }
            if ($method === 'DELETE') {
                $this->apiDeleteClient($wid, (int) $m[1]);
            }
        }
        if ($method === 'GET' && $path === '/api/invoices') {
            $this->apiInvoices($wid);
        }
        if ($method === 'POST' && $path === '/api/invoices') {
            $this->apiCreateInvoice($wid);
        }
        if (preg_match('#^/api/invoices/(\d+)$#', $path, $m)) {
            if ($method === 'GET') {
                $this->apiInvoice($wid, (int) $m[1]);
            }
            if ($method === 'PUT') {
                $this->apiUpdateInvoice($wid, (int) $m[1]);
            }
            if ($method === 'DELETE') {
                $this->apiDeleteInvoice($wid, (int) $m[1]);
            }
        }
        if (preg_match('#^/api/invoices/(\d+)/pdf$#', $path, $m) && $method === 'GET') {
            $this->apiInvoicePdf($wid, (int) $m[1]);
        }
        if (preg_match('#^/api/invoices/(\d+)/send$#', $path, $m) && $method === 'POST') {
            $this->apiSendInvoice($wid, (int) $m[1]);
        }
        if (preg_match('#^/api/invoices/(\d+)/payments$#', $path, $m) && $method === 'POST') {
            $this->apiRecordPayment($wid, (int) $m[1]);
        }
        if (preg_match('#^/api/invoices/(\d+)/final-notice$#', $path, $m) && $method === 'POST') {
            $this->apiFinalNotice($wid, (int) $m[1]);
        }
        if ($method === 'GET' && $path === '/api/reminders') {
            $this->apiReminders($wid);
        }
        if (preg_match('#^/api/reminders/(\d+)/send$#', $path, $m) && $method === 'POST') {
            $this->apiSendReminder($wid, (int) $m[1]);
        }
        if ($method === 'POST' && $path === '/api/reminders/send-due') {
            $this->apiSendDue($wid);
        }
        if ($method === 'GET' && $path === '/api/templates') {
            $st = $this->db->prepare('SELECT * FROM templates WHERE workspace_id=? ORDER BY channel, name');
            $st->execute([$wid]);
            Http::json($st->fetchAll());
        }
        if (preg_match('#^/api/templates/(\d+)$#', $path, $m) && $method === 'PUT') {
            $this->apiUpdateTemplate($wid, (int) $m[1]);
        }
        if ($path === '/api/settings') {
            if ($method === 'GET') {
                $this->apiGetSettings($wid);
            }
            if ($method === 'PUT') {
                $this->apiPutSettings($wid);
            }
        }
        if ($method === 'GET' && $path === '/api/activity') {
            $st = $this->db->prepare('SELECT * FROM activity WHERE workspace_id=? ORDER BY id DESC LIMIT 50');
            $st->execute([$wid]);
            Http::json($st->fetchAll());
        }
        if ($method === 'GET' && $path === '/api/billing/status') {
            $this->apiBillingStatus($wid);
        }
        if ($method === 'POST' && $path === '/api/billing/checkout') {
            $this->apiBillingCheckout($wid);
        }
        if ($method === 'POST' && $path === '/api/billing/portal') {
            $this->apiBillingPortal($wid);
        }
        if ($method === 'GET' && $path === '/api/mail/status') {
            Http::json(Mailer::status());
        }
        if ($method === 'POST' && $path === '/api/mail/test') {
            $this->apiMailTest($wid);
        }
        Http::json(['error' => 'Not found'], 404);
    }

    private function apiDashboard(int $wid): void
    {
        $today = date('Y-m-d');
        $st = $this->db->prepare(
            "SELECT i.*, c.name AS client_name FROM invoices i
             JOIN clients c ON c.id = i.client_id
             WHERE i.workspace_id = ? AND i.status IN ('sent','partial','overdue')
             ORDER BY i.due_date"
        );
        $st->execute([$wid]);
        $openInv = $st->fetchAll();
        foreach ($openInv as &$inv) {
            $inv['balance'] = Db::invoiceBalance($inv);
        }
        unset($inv);
        $overdue = array_values(array_filter(
            $openInv,
            fn ($i) => $i['status'] === 'overdue' || $i['due_date'] < $today
        ));
        $soonEnd = (new DateTimeImmutable('today'))->modify('+7 days')->format('Y-m-d');
        $dueSoon = array_values(array_filter(
            $openInv,
            fn ($i) => $today <= $i['due_date'] && $i['due_date'] <= $soonEnd
        ));
        $paidSt = $this->db->prepare(
            'SELECT COALESCE(SUM(p.amount), 0) FROM payments p
             JOIN invoices i ON i.id = p.invoice_id
             WHERE i.workspace_id = ? AND p.paid_at >= ?'
        );
        $paidSt->execute([$wid, (new DateTimeImmutable('today'))->modify('-30 days')->format('Y-m-d')]);
        $paid30 = (float) $paidSt->fetchColumn();
        $aging = ['current' => 0.0, 'd1_30' => 0.0, 'd31_60' => 0.0, 'd60_plus' => 0.0];
        $todayDt = new DateTimeImmutable('today');
        foreach ($openInv as $inv) {
            $due = new DateTimeImmutable($inv['due_date']);
            $days = (int) $due->diff($todayDt)->format('%r%a');
            $bal = $inv['balance'];
            if ($days <= 0) {
                $aging['current'] += $bal;
            } elseif ($days <= 30) {
                $aging['d1_30'] += $bal;
            } elseif ($days <= 60) {
                $aging['d31_60'] += $bal;
            } else {
                $aging['d60_plus'] += $bal;
            }
        }
        $dr = $this->db->prepare(
            "SELECT r.*, i.number AS invoice_number, i.amount, i.amount_paid, c.name AS client_name
             FROM reminders r
             JOIN invoices i ON i.id = r.invoice_id
             JOIN clients c ON c.id = i.client_id
             WHERE i.workspace_id = ? AND r.status IN ('due','pending') AND r.scheduled_for <= ?
             ORDER BY r.scheduled_for LIMIT 12"
        );
        $dr->execute([$wid, $today]);
        $dueReminders = $dr->fetchAll();
        foreach ($dueReminders as &$r) {
            $r['balance'] = round((float) $r['amount'] - (float) $r['amount_paid'], 2);
            if ($r['scheduled_for'] < $today && $r['status'] === 'pending') {
                $r['severity'] = 'critical';
            } elseif ($r['scheduled_for'] <= $today) {
                $r['severity'] = 'warning';
            } else {
                $r['severity'] = 'normal';
            }
        }
        unset($r);
        $act = $this->db->prepare('SELECT * FROM activity WHERE workspace_id = ? ORDER BY id DESC LIMIT 10');
        $act->execute([$wid]);
        $rec = $this->db->prepare(
            "SELECT COUNT(*) FROM invoices WHERE workspace_id = ? AND status = 'paid' AND updated_at >= ?"
        );
        $rec->execute([$wid, (new DateTimeImmutable('today'))->modify('-30 days')->format('Y-m-d')]);
        Http::json([
            'kpis' => [
                'overdue_total' => array_sum(array_column($overdue, 'balance')),
                'overdue_count' => count($overdue),
                'open_total' => array_sum(array_column($openInv, 'balance')),
                'open_count' => count($openInv),
                'due_soon_count' => count($dueSoon),
                'collected_30' => $paid30,
                'recovered_invoices_30' => (int) $rec->fetchColumn(),
            ],
            'aging' => $aging,
            'open_invoices' => array_slice($openInv, 0, 8),
            'due_reminders' => $dueReminders,
            'activity' => $act->fetchAll(),
        ]);
    }

    private function apiClients(int $wid): void
    {
        $q = strtolower(trim((string) ($_GET['q'] ?? '')));
        $st = $this->db->prepare(
            "SELECT c.*,
              (SELECT COUNT(*) FROM invoices i WHERE i.client_id = c.id) AS invoice_count,
              (SELECT COALESCE(SUM(i.amount - i.amount_paid), 0) FROM invoices i
                WHERE i.client_id = c.id AND i.status IN ('sent','partial','overdue')) AS open_balance
             FROM clients c WHERE c.workspace_id = ? ORDER BY c.name"
        );
        $st->execute([$wid]);
        $rows = $st->fetchAll();
        if ($q !== '') {
            $rows = array_values(array_filter($rows, function ($r) use ($q) {
                return str_contains(strtolower((string) ($r['name'] ?? '')), $q)
                    || str_contains(strtolower((string) ($r['company'] ?? '')), $q)
                    || str_contains(strtolower((string) ($r['email'] ?? '')), $q);
            }));
        }
        Http::json($rows);
    }

    private function apiCreateClient(int $wid): void
    {
        $data = Http::bodyJson();
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            Http::json(['error' => 'Name is required'], 400);
        }
        $this->db->prepare(
            'INSERT INTO clients (workspace_id, name, company, email, phone, notes, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $wid, $name,
            trim((string) ($data['company'] ?? '')) ?: null,
            trim((string) ($data['email'] ?? '')) ?: null,
            trim((string) ($data['phone'] ?? '')) ?: null,
            trim((string) ($data['notes'] ?? '')) ?: null,
            Db::now(),
        ]);
        $id = (int) $this->db->lastInsertId();
        Db::log($this->db, 'client', "Added client {$name}", 'client', $id, $wid);
        $st = $this->db->prepare('SELECT * FROM clients WHERE id = ? AND workspace_id = ?');
        $st->execute([$id, $wid]);
        Http::json($st->fetch(), 201);
    }

    private function apiUpdateClient(int $wid, int $id): void
    {
        $data = Http::bodyJson();
        $st = $this->db->prepare('SELECT * FROM clients WHERE id = ? AND workspace_id = ?');
        $st->execute([$id, $wid]);
        $existing = $st->fetch();
        if (!$existing) {
            Http::json(['error' => 'Not found'], 404);
        }
        $name = trim((string) ($data['name'] ?? $existing['name']));
        $this->db->prepare(
            'UPDATE clients SET name=?, company=?, email=?, phone=?, notes=? WHERE id=? AND workspace_id=?'
        )->execute([
            $name,
            array_key_exists('company', $data) ? $data['company'] : $existing['company'],
            array_key_exists('email', $data) ? $data['email'] : $existing['email'],
            array_key_exists('phone', $data) ? $data['phone'] : $existing['phone'],
            array_key_exists('notes', $data) ? $data['notes'] : $existing['notes'],
            $id, $wid,
        ]);
        Db::log($this->db, 'client', "Updated client {$name}", 'client', $id, $wid);
        $st->execute([$id, $wid]);
        Http::json($st->fetch());
    }

    private function apiDeleteClient(int $wid, int $id): void
    {
        $st = $this->db->prepare('SELECT * FROM clients WHERE id = ? AND workspace_id = ?');
        $st->execute([$id, $wid]);
        $row = $st->fetch();
        if (!$row) {
            Http::json(['error' => 'Not found'], 404);
        }
        $this->db->prepare('DELETE FROM clients WHERE id = ? AND workspace_id = ?')->execute([$id, $wid]);
        Db::log($this->db, 'client', "Deleted client {$row['name']}", 'client', $id, $wid);
        Http::json(['ok' => true]);
    }

    private function invoiceDetail(int $invoiceId, int $wid): ?array
    {
        $st = $this->db->prepare(
            'SELECT i.*, c.name AS client_name, c.company AS client_company,
                    c.email AS client_email, c.phone AS client_phone
             FROM invoices i JOIN clients c ON c.id = i.client_id
             WHERE i.id = ? AND i.workspace_id = ?'
        );
        $st->execute([$invoiceId, $wid]);
        $data = $st->fetch();
        if (!$data) {
            return null;
        }
        $data['balance'] = Db::invoiceBalance($data);
        $p = $this->db->prepare('SELECT * FROM payments WHERE invoice_id = ? ORDER BY paid_at DESC, id DESC');
        $p->execute([$invoiceId]);
        $data['payments'] = $p->fetchAll();
        $r = $this->db->prepare('SELECT * FROM reminders WHERE invoice_id = ? ORDER BY scheduled_for');
        $r->execute([$invoiceId]);
        $data['reminders'] = $r->fetchAll();
        return $data;
    }

    private function scheduleReminders(int $invoiceId, string $dueDate, bool $force = false): int
    {
        $st = $this->db->prepare('SELECT workspace_id FROM invoices WHERE id = ?');
        $st->execute([$invoiceId]);
        $inv0 = $st->fetch();
        if (!$inv0) {
            return 0;
        }
        $wid = (int) $inv0['workspace_id'];
        $settings = Workspace::settings($this->db, $wid);
        $offsets = json_decode($settings['reminder_offsets'] ?? '[-3,0,3,7,14]', true) ?: [-3, 0, 3, 7, 14];
        $channel = $settings['default_channel'] ?? 'email';
        $due = new DateTimeImmutable($dueDate);
        $today = date('Y-m-d');
        $now = Db::now();
        if ($force) {
            $this->db->prepare(
                "DELETE FROM reminders WHERE invoice_id = ? AND status IN ('pending','due')"
            )->execute([$invoiceId]);
        }
        $ex = $this->db->prepare('SELECT scheduled_for FROM reminders WHERE invoice_id = ?');
        $ex->execute([$invoiceId]);
        $existing = array_column($ex->fetchAll(), 'scheduled_for');
        $tmpl = $this->db->prepare(
            'SELECT * FROM templates WHERE workspace_id = ? AND channel = ? AND is_default = 1 ORDER BY id LIMIT 1'
        );
        $tmpl->execute([$wid, $channel]);
        $template = $tmpl->fetch();
        $invSt = $this->db->prepare(
            'SELECT i.*, c.name AS client_name FROM invoices i JOIN clients c ON c.id = i.client_id
             WHERE i.id = ? AND i.workspace_id = ?'
        );
        $invSt->execute([$invoiceId, $wid]);
        $inv = $invSt->fetch();
        if (!$inv) {
            return 0;
        }
        $ctx = [
            'number' => $inv['number'],
            'client_name' => $inv['client_name'],
            'title' => $inv['title'] ?? '',
            'amount_due' => Db::money(Db::invoiceBalance($inv)),
            'due_date' => $inv['due_date'],
            'status' => $inv['status'],
            'business_name' => $settings['business_name'] ?? 'InPmnt',
        ];
        $created = 0;
        $ins = $this->db->prepare(
            'INSERT INTO reminders (invoice_id, channel, scheduled_for, status, subject, body, sent_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NULL, ?)'
        );
        foreach ($offsets as $offset) {
            $scheduled = $due->modify(((int) $offset >= 0 ? '+' : '') . (int) $offset . ' days')->format('Y-m-d');
            if (in_array($scheduled, $existing, true)) {
                continue;
            }
            if ($scheduled < $today) {
                continue;
            }
            $status = $scheduled === $today ? 'due' : 'pending';
            $subject = Db::renderVars($template['subject'] ?? ('Reminder: ' . $inv['number']), $ctx);
            $body = Db::renderVars($template['body'] ?? ('Reminder for invoice ' . $inv['number'] . '.'), $ctx);
            $ins->execute([$invoiceId, $channel, $scheduled, $status, $subject, $body, $now]);
            $created++;
        }
        return $created;
    }

    private function apiInvoices(int $wid): void
    {
        $st = $this->db->prepare("SELECT id FROM invoices WHERE workspace_id=? AND status IN ('sent','partial')");
        $st->execute([$wid]);
        foreach ($st->fetchAll() as $inv) {
            Db::refreshInvoiceStatus($this->db, (int) $inv['id']);
        }
        $sql = 'SELECT i.*, c.name AS client_name, c.company AS client_company
                FROM invoices i JOIN clients c ON c.id = i.client_id WHERE i.workspace_id = ?';
        $params = [$wid];
        $status = strtolower(trim((string) ($_GET['status'] ?? '')));
        if ($status !== '' && $status !== 'all') {
            $sql .= ' AND i.status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY i.due_date DESC, i.id DESC';
        $q = $this->db->prepare($sql);
        $q->execute($params);
        $rows = $q->fetchAll();
        foreach ($rows as &$r) {
            $r['balance'] = Db::invoiceBalance($r);
        }
        Http::json($rows);
    }

    private function apiInvoice(int $wid, int $id): void
    {
        $st = $this->db->prepare('SELECT id FROM invoices WHERE id=? AND workspace_id=?');
        $st->execute([$id, $wid]);
        if (!$st->fetch()) {
            Http::json(['error' => 'Not found'], 404);
        }
        Db::refreshInvoiceStatus($this->db, $id);
        $data = $this->invoiceDetail($id, $wid);
        if (!$data) {
            Http::json(['error' => 'Not found'], 404);
        }
        Http::json($data);
    }

    private function apiCreateInvoice(int $wid): void
    {
        $data = Http::bodyJson();
        $clientId = $data['client_id'] ?? null;
        $title = trim((string) ($data['title'] ?? ''));
        $amount = (float) ($data['amount'] ?? 0);
        if (!$clientId || $title === '' || $amount <= 0) {
            Http::json(['error' => 'Client, title, and amount are required'], 400);
        }
        $issue = $data['issue_date'] ?? date('Y-m-d');
        $due = $data['due_date'] ?? (new DateTimeImmutable('today'))->modify('+14 days')->format('Y-m-d');
        $status = $data['status'] ?? 'draft';
        if (!in_array($status, ['draft', 'sent', 'partial', 'overdue', 'paid'], true)) {
            $status = 'draft';
        }
        $wantSend = $status === 'sent';
        $insertStatus = $wantSend ? 'draft' : $status;
        $now = Db::now();
        $c = $this->db->prepare('SELECT id FROM clients WHERE id=? AND workspace_id=?');
        $c->execute([(int) $clientId, $wid]);
        if (!$c->fetch()) {
            Http::json(['error' => 'Client not found'], 404);
        }
        $settings = Workspace::settings($this->db, $wid);
        if (in_array($insertStatus, ['sent', 'partial', 'overdue'], true) || $wantSend) {
            $blocked = Workspace::assertCanAddOpen($this->db, $wid, $settings);
            if ($blocked) {
                Http::json(['error' => $blocked], 403);
            }
        }
        $number = trim((string) ($data['number'] ?? '')) ?: Db::nextInvoiceNumber($this->db, $wid);
        $this->db->prepare(
            'INSERT INTO invoices (workspace_id, number, client_id, title, amount, amount_paid, currency,
                issue_date, due_date, status, notes, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $wid, $number, (int) $clientId, $title, $amount,
            $data['currency'] ?? 'USD', $issue, $due, $insertStatus,
            trim((string) ($data['notes'] ?? '')), $now, $now,
        ]);
        $invoiceId = (int) $this->db->lastInsertId();
        $emailed = false;
        if ($wantSend) {
            $this->deliverInvoice($wid, $invoiceId);
            $emailed = true;
        } elseif (in_array($insertStatus, ['sent', 'partial', 'overdue'], true)) {
            $this->scheduleReminders($invoiceId, $due, true);
        }
        Db::log($this->db, 'invoice', "Created invoice {$number}", 'invoice', $invoiceId, $wid);
        $out = $this->invoiceDetail($invoiceId, $wid);
        if (is_array($out)) {
            $out['emailed'] = $emailed;
        }
        Http::json($out, 201);
    }

    private function apiUpdateInvoice(int $wid, int $id): void
    {
        $data = Http::bodyJson();
        $st = $this->db->prepare('SELECT * FROM invoices WHERE id=? AND workspace_id=?');
        $st->execute([$id, $wid]);
        $existing = $st->fetch();
        if (!$existing) {
            Http::json(['error' => 'Not found'], 404);
        }
        $wantSend = filter_var($data['send'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $title = array_key_exists('title', $data) ? trim((string) $data['title']) : (string) $existing['title'];
        if ($title === '') {
            Http::json(['error' => 'Title is required'], 400);
        }
        if (array_key_exists('amount', $data)) {
            $amount = (float) $data['amount'];
        } else {
            $amount = (float) $existing['amount'];
        }
        if ($amount <= 0) {
            Http::json(['error' => 'Amount must be greater than zero'], 400);
        }
        $paid = (float) ($existing['amount_paid'] ?? 0);
        if ($amount < $paid) {
            Http::json(['error' => 'Amount cannot be less than what is already paid'], 400);
        }
        $status = $data['status'] ?? $existing['status'];
        if (!in_array($status, ['draft', 'sent', 'partial', 'overdue', 'paid'], true)) {
            $status = $existing['status'];
        }
        $notes = array_key_exists('notes', $data)
            ? trim((string) $data['notes'])
            : (string) ($existing['notes'] ?? '');
        $fields = [
            'title' => $title,
            'amount' => $amount,
            'issue_date' => $data['issue_date'] ?? $existing['issue_date'],
            'due_date' => $data['due_date'] ?? $existing['due_date'],
            'notes' => $notes,
            'client_id' => (int) ($data['client_id'] ?? $existing['client_id']),
            'status' => $status,
        ];
        $c = $this->db->prepare('SELECT id FROM clients WHERE id=? AND workspace_id=?');
        $c->execute([$fields['client_id'], $wid]);
        if (!$c->fetch()) {
            Http::json(['error' => 'Client not found'], 404);
        }
        $becomingOpen = in_array($fields['status'], ['sent', 'partial', 'overdue'], true)
            && $existing['status'] === 'draft';
        if ($becomingOpen) {
            $blocked = Workspace::assertCanAddOpen($this->db, $wid, Workspace::settings($this->db, $wid));
            if ($blocked) {
                Http::json(['error' => $blocked], 403);
            }
        }
        $this->db->prepare(
            'UPDATE invoices SET title=?, amount=?, issue_date=?, due_date=?, notes=?, client_id=?, status=?, updated_at=?
             WHERE id=? AND workspace_id=?'
        )->execute([
            $fields['title'], $fields['amount'], $fields['issue_date'], $fields['due_date'],
            $fields['notes'], $fields['client_id'], $fields['status'], Db::now(), $id, $wid,
        ]);
        if ($becomingOpen || ($fields['due_date'] !== $existing['due_date'] && $fields['status'] !== 'draft')) {
            $this->scheduleReminders($id, $fields['due_date'], true);
        }
        Db::refreshInvoiceStatus($this->db, $id);
        Db::log($this->db, 'invoice', "Updated invoice {$existing['number']}", 'invoice', $id, $wid);
        $emailed = false;
        if ($wantSend) {
            $this->deliverInvoice($wid, $id);
            $emailed = true;
        }
        $out = $this->invoiceDetail($id, $wid);
        if (is_array($out)) {
            $out['emailed'] = $emailed;
        }
        Http::json($out);
    }

    private function apiSendInvoice(int $wid, int $id): void
    {
        $this->deliverInvoice($wid, $id);
        $out = $this->invoiceDetail($id, $wid);
        if (is_array($out)) {
            $out['emailed'] = true;
        }
        Http::json($out);
    }

    private function apiInvoicePdf(int $wid, int $id): void
    {
        $st = $this->db->prepare(
            'SELECT i.*, c.name AS client_name, c.email AS client_email,
                    c.company AS client_company, c.phone AS client_phone
             FROM invoices i JOIN clients c ON c.id = i.client_id
             WHERE i.id=? AND i.workspace_id=?'
        );
        $st->execute([$id, $wid]);
        $inv = $st->fetch();
        if (!$inv) {
            Http::json(['error' => 'Not found'], 404);
        }
        $settings = Workspace::settings($this->db, $wid) ?? [];
        $pdf = InvoicePdf::build(InvoicePdf::payload($inv, $settings));
        Http::pdf($pdf, InvoicePdf::filename((string) ($inv['number'] ?? 'invoice')));
    }

    /** Email the Invoice template with a PDF attachment. Drafts are marked sent and reminders are scheduled. */
    private function deliverInvoice(int $wid, int $id): void
    {
        $st = $this->db->prepare(
            'SELECT i.*, c.name AS client_name, c.email AS client_email,
                    c.company AS client_company, c.phone AS client_phone
             FROM invoices i JOIN clients c ON c.id = i.client_id
             WHERE i.id=? AND i.workspace_id=?'
        );
        $st->execute([$id, $wid]);
        $inv = $st->fetch();
        if (!$inv) {
            Http::json(['error' => 'Not found'], 404);
        }
        $to = trim((string) ($inv['client_email'] ?? ''));
        if ($to === '' || !str_contains($to, '@')) {
            Http::json(['error' => 'Client has no email address. Add one on the client, then send again.'], 400);
        }
        $settings = Workspace::settings($this->db, $wid);
        $wasDraft = ($inv['status'] ?? '') === 'draft';
        if ($wasDraft) {
            $blocked = Workspace::assertCanAddOpen($this->db, $wid, $settings);
            if ($blocked) {
                Http::json(['error' => $blocked], 403);
            }
        }
        $t = $this->db->prepare("SELECT * FROM templates WHERE workspace_id=? AND name='Invoice' LIMIT 1");
        $t->execute([$wid]);
        $tmpl = $t->fetch();
        $ctx = [
            'number' => $inv['number'],
            'client_name' => $inv['client_name'],
            'title' => $inv['title'] ?? '',
            'amount_due' => Db::money(Db::invoiceBalance($inv)),
            'amount' => Db::money((float) $inv['amount']),
            'due_date' => $inv['due_date'],
            'issue_date' => $inv['issue_date'],
            'status' => $inv['status'],
            'notes' => $inv['notes'] ?? '',
            'business_name' => $settings['business_name'] ?? 'InPmnt',
        ];
        $subject = Db::renderVars($tmpl['subject'] ?? 'Invoice {{number}} from {{business_name}}', $ctx);
        $body = InvoicePdf::mentionAttachment(Db::renderVars(
            $tmpl['body'] ?? "Hi {{client_name}},\n\nInvoice {{number}} is ready for {{title}}.\n\nAmount due: {{amount_due}}\nDue date: {{due_date}}\n\nA PDF copy of this invoice is attached.\n\nPlease reply to this email if you have any questions.\n\nThanks,\n{{business_name}}",
            $ctx
        ));
        $pdf = InvoicePdf::build(InvoicePdf::payload($inv, $settings ?? []));
        $filename = InvoicePdf::filename((string) ($inv['number'] ?? 'invoice'));
        $attachments = [['filename' => $filename, 'content' => $pdf, 'mime' => 'application/pdf']];
        if (!Mailer::configured()) {
            if (!Env::truthy('ALLOW_FAKE_EMAIL')) {
                $this->emailNotConfigured();
            }
        } else {
            try {
                Mailer::send($to, $subject, $body, $settings['business_name'] ?? null, $attachments);
            } catch (Throwable $e) {
                Http::json(['error' => $e->getMessage()], 502);
            }
        }
        $now = Db::now();
        $today = date('Y-m-d');
        if ($wasDraft) {
            $status = ((float) $inv['amount_paid'] >= (float) $inv['amount'])
                ? 'paid'
                : (((float) $inv['amount_paid'] > 0) ? 'partial' : 'sent');
            if ($inv['due_date'] < $today && $status !== 'paid') {
                $status = ((float) $inv['amount_paid'] == 0.0) ? 'overdue' : 'partial';
            }
            $this->db->prepare('UPDATE invoices SET status=?, updated_at=? WHERE id=? AND workspace_id=?')
                ->execute([$status, $now, $id, $wid]);
            $this->scheduleReminders($id, $inv['due_date'], true);
        }
        $this->db->prepare(
            "INSERT INTO reminders (invoice_id, channel, scheduled_for, status, subject, body, sent_at, created_at)
             VALUES (?, 'email', ?, 'sent', ?, ?, ?, ?)"
        )->execute([$id, $today, $subject, $body, $now, $now]);
        $rid = (int) $this->db->lastInsertId();
        Db::log($this->db, 'invoice', "Emailed invoice {$inv['number']} to {$to}", 'invoice', $id, $wid);
        Db::log($this->db, 'reminder', "Sent INVOICE email for {$inv['number']} to {$to}", 'reminder', $rid, $wid);
    }

    private function apiRecordPayment(int $wid, int $id): void
    {
        $data = Http::bodyJson();
        $amount = (float) ($data['amount'] ?? 0);
        if ($amount <= 0) {
            Http::json(['error' => 'Amount must be positive'], 400);
        }
        $st = $this->db->prepare('SELECT * FROM invoices WHERE id=? AND workspace_id=?');
        $st->execute([$id, $wid]);
        $inv = $st->fetch();
        if (!$inv) {
            Http::json(['error' => 'Not found'], 404);
        }
        $now = Db::now();
        $this->db->prepare(
            'INSERT INTO payments (invoice_id, amount, method, paid_at, note, created_at) VALUES (?,?,?,?,?,?)'
        )->execute([
            $id, $amount, trim((string) ($data['method'] ?? 'Other')),
            $data['paid_at'] ?? date('Y-m-d'), trim((string) ($data['note'] ?? '')), $now,
        ]);
        $newPaid = round((float) $inv['amount_paid'] + $amount, 2);
        $this->db->prepare('UPDATE invoices SET amount_paid=?, updated_at=? WHERE id=? AND workspace_id=?')
            ->execute([$newPaid, $now, $id, $wid]);
        Db::refreshInvoiceStatus($this->db, $id);
        $st->execute([$id, $wid]);
        $refreshed = $st->fetch();
        if ($refreshed && $refreshed['status'] === 'paid') {
            $this->db->prepare(
                "UPDATE reminders SET status='cancelled' WHERE invoice_id=? AND status IN ('pending','due')"
            )->execute([$id]);
        }
        Db::log($this->db, 'payment', 'Recorded ' . Db::money($amount) . " on {$inv['number']}", 'invoice', $id, $wid);
        Http::json($this->invoiceDetail($id, $wid));
    }

    private function apiDeleteInvoice(int $wid, int $id): void
    {
        $st = $this->db->prepare('SELECT * FROM invoices WHERE id=? AND workspace_id=?');
        $st->execute([$id, $wid]);
        $inv = $st->fetch();
        if (!$inv) {
            Http::json(['error' => 'Not found'], 404);
        }
        $this->db->prepare('DELETE FROM invoices WHERE id=? AND workspace_id=?')->execute([$id, $wid]);
        Db::log($this->db, 'invoice', "Deleted invoice {$inv['number']}", 'invoice', $id, $wid);
        Http::json(['ok' => true]);
    }

    private function emailNotConfigured(): never
    {
        Http::json([
            'error' => 'Email is not configured. Set RESEND_API_KEY or SMTP_HOST/SMTP_USER/MAIL_FROM in .env (or ALLOW_FAKE_EMAIL=1 for local testing).',
            'status' => Mailer::status(),
        ], 503);
    }

    private function apiMailTest(int $wid): void
    {
        $user = $GLOBALS['inpmnt_user'] ?? [];
        $settings = Workspace::settings($this->db, $wid) ?: [];
        $to = trim((string) ($user['email'] ?? ''));
        if ($to === '') {
            $to = trim((string) ($settings['email'] ?? ''));
        }
        if ($to === '' || !str_contains($to, '@')) {
            Http::json(['error' => 'No mailbox to send a test to. Add an email on this account or in Settings.'], 400);
        }
        if (!Mailer::configured()) {
            if (!Env::truthy('ALLOW_FAKE_EMAIL')) {
                $this->emailNotConfigured();
            }
            Http::json(['ok' => true, 'provider' => 'fake', 'to' => $to, 'status' => Mailer::status()]);
        }
        try {
            $result = Mailer::send(
                $to,
                'InPmnt test email',
                "This is a test from InPmnt.\n\nIf you received this, outbound mail is working.\n",
                $settings['business_name'] ?? null
            );
            Db::log($this->db, 'reminder', "Sent test email to {$to}", 'settings', $wid, $wid);
            Http::json(['ok' => true, 'to' => $to, 'status' => Mailer::status()] + $result);
        } catch (Throwable $e) {
            Http::json(['error' => $e->getMessage(), 'status' => Mailer::status()], 502);
        }
    }

    private function apiReminders(int $wid): void
    {
        $today = date('Y-m-d');
        $this->db->prepare(
            "UPDATE reminders SET status='due'
             WHERE status='pending' AND scheduled_for <= ?
               AND invoice_id IN (SELECT id FROM invoices WHERE workspace_id=?)"
        )->execute([$today, $wid]);
        $sql = "SELECT r.*, i.number AS invoice_number, i.amount, i.amount_paid, i.due_date,
                       c.name AS client_name, c.email AS client_email, c.phone AS client_phone
                FROM reminders r
                JOIN invoices i ON i.id = r.invoice_id
                JOIN clients c ON c.id = i.client_id
                WHERE i.workspace_id = ?";
        $status = trim((string) ($_GET['status'] ?? 'queue'));
        if ($status === 'queue') {
            $sql .= " AND r.status IN ('due','pending') ORDER BY r.scheduled_for, r.id";
        } elseif ($status === 'sent') {
            $sql .= " AND r.status = 'sent' ORDER BY r.sent_at DESC, r.id DESC LIMIT 100";
        } else {
            $sql .= ' ORDER BY r.scheduled_for DESC LIMIT 200';
        }
        $st = $this->db->prepare($sql);
        $st->execute([$wid]);
        $rows = $st->fetchAll();
        foreach ($rows as &$r) {
            $r['balance'] = round((float) $r['amount'] - (float) $r['amount_paid'], 2);
            if (in_array($r['status'], ['due', 'pending'], true) && $r['scheduled_for'] < $today) {
                $r['severity'] = 'critical';
            } elseif ($r['status'] === 'due' || $r['scheduled_for'] === $today) {
                $r['severity'] = 'warning';
            } else {
                $r['severity'] = 'normal';
            }
        }
        Http::json($rows);
    }

    private function apiSendReminder(int $wid, int $id): void
    {
        $st = $this->db->prepare(
            'SELECT r.*, i.number, i.workspace_id, c.name AS client_name, c.email, c.phone
             FROM reminders r
             JOIN invoices i ON i.id = r.invoice_id
             JOIN clients c ON c.id = i.client_id
             WHERE r.id = ? AND i.workspace_id = ?'
        );
        $st->execute([$id, $wid]);
        $r = $st->fetch();
        if (!$r) {
            Http::json(['error' => 'Not found'], 404);
        }
        if ($r['status'] === 'cancelled') {
            Http::json(['error' => 'Reminder was cancelled'], 400);
        }
        $settings = Workspace::settings($this->db, $wid);
        $channel = strtolower((string) ($r['channel'] ?? 'email'));
        $now = Db::now();
        if ($channel === 'sms') {
            if (!Workspace::allowsSms(Workspace::effectivePlan($settings))) {
                Http::json(['error' => 'SMS reminders require the Pro plan.'], 403);
            }
            Db::log($this->db, 'reminder', "SMS stub (not wired yet) for {$r['number']} to " . ($r['phone'] ?: $r['client_name']), 'reminder', $id, $wid);
            $this->db->prepare('UPDATE reminders SET status=?, sent_at=? WHERE id=?')->execute(['sent', $now, $id]);
        } elseif ($channel === 'email') {
            if (!Mailer::configured()) {
                if (!Env::truthy('ALLOW_FAKE_EMAIL')) {
                    $this->emailNotConfigured();
                }
            } else {
                try {
                    Mailer::send((string) ($r['email'] ?? ''), (string) ($r['subject'] ?? ''), (string) ($r['body'] ?? ''), $settings['business_name'] ?? null);
                } catch (Throwable $e) {
                    Http::json(['error' => $e->getMessage()], 502);
                }
            }
            $this->db->prepare('UPDATE reminders SET status=?, sent_at=? WHERE id=?')->execute(['sent', $now, $id]);
            Db::log($this->db, 'reminder', "Sent EMAIL reminder for {$r['number']} to " . ($r['email'] ?: $r['client_name']), 'reminder', $id, $wid);
        } else {
            Http::json(['error' => "Unknown channel: {$channel}"], 400);
        }
        $out = $this->db->prepare('SELECT * FROM reminders WHERE id = ?');
        $out->execute([$id]);
        Http::json($out->fetch());
    }

    private function apiSendDue(int $wid): void
    {
        $today = date('Y-m-d');
        $now = Db::now();
        $settings = Workspace::settings($this->db, $wid);
        $st = $this->db->prepare(
            "SELECT r.id, r.channel, r.subject, r.body, i.number, c.name AS client_name, c.email, c.phone
             FROM reminders r
             JOIN invoices i ON i.id = r.invoice_id
             JOIN clients c ON c.id = i.client_id
             WHERE i.workspace_id = ? AND r.status IN ('due','pending') AND r.scheduled_for <= ?"
        );
        $st->execute([$wid, $today]);
        $rows = $st->fetchAll();
        $needsEmail = false;
        foreach ($rows as $r) {
            if (strtolower((string) ($r['channel'] ?? '')) === 'email') {
                $needsEmail = true;
            }
        }
        if ($needsEmail && !Mailer::configured() && !Env::truthy('ALLOW_FAKE_EMAIL')) {
            $this->emailNotConfigured();
        }
        $sent = 0;
        foreach ($rows as $r) {
            $channel = strtolower((string) ($r['channel'] ?? 'email'));
            if ($channel === 'sms') {
                if (!Workspace::allowsSms(Workspace::effectivePlan($settings))) {
                    continue;
                }
                Db::log($this->db, 'reminder', "SMS stub (not wired yet) for {$r['number']} to " . ($r['phone'] ?: $r['client_name']), 'reminder', (int) $r['id'], $wid);
            } elseif ($channel === 'email') {
                if (Mailer::configured()) {
                    try {
                        Mailer::send((string) ($r['email'] ?? ''), (string) ($r['subject'] ?? ''), (string) ($r['body'] ?? ''), $settings['business_name'] ?? null);
                    } catch (Throwable $e) {
                        Http::json(['error' => $e->getMessage(), 'sent' => $sent], 502);
                    }
                }
                Db::log($this->db, 'reminder', "Sent EMAIL reminder for {$r['number']} to " . ($r['email'] ?: $r['client_name']), 'reminder', (int) $r['id'], $wid);
            } else {
                continue;
            }
            $this->db->prepare('UPDATE reminders SET status=?, sent_at=? WHERE id=?')->execute(['sent', $now, $r['id']]);
            $sent++;
        }
        Http::json(['sent' => $sent]);
    }

    private function apiFinalNotice(int $wid, int $id): void
    {
        $st = $this->db->prepare(
            'SELECT i.*, c.name AS client_name, c.email AS client_email
             FROM invoices i JOIN clients c ON c.id = i.client_id
             WHERE i.id = ? AND i.workspace_id = ?'
        );
        $st->execute([$id, $wid]);
        $inv = $st->fetch();
        if (!$inv) {
            Http::json(['error' => 'Not found'], 404);
        }
        $settings = Workspace::settings($this->db, $wid);
        $t = $this->db->prepare("SELECT * FROM templates WHERE workspace_id=? AND name='Final notice' LIMIT 1");
        $t->execute([$wid]);
        $tmpl = $t->fetch();
        $ctx = [
            'number' => $inv['number'],
            'client_name' => $inv['client_name'],
            'title' => $inv['title'] ?? '',
            'amount_due' => Db::money(Db::invoiceBalance($inv)),
            'due_date' => $inv['due_date'],
            'status' => $inv['status'],
            'business_name' => $settings['business_name'] ?? 'InPmnt',
        ];
        $subject = Db::renderVars($tmpl['subject'] ?? 'Final notice', $ctx);
        $body = Db::renderVars($tmpl['body'] ?? 'Final notice', $ctx);
        if (!Mailer::configured()) {
            if (!Env::truthy('ALLOW_FAKE_EMAIL')) {
                $this->emailNotConfigured();
            }
        } else {
            try {
                Mailer::send((string) ($inv['client_email'] ?? ''), $subject, $body, $settings['business_name'] ?? null);
            } catch (Throwable $e) {
                Http::json(['error' => $e->getMessage()], 502);
            }
        }
        $now = Db::now();
        $this->db->prepare(
            "INSERT INTO reminders (invoice_id, channel, scheduled_for, status, subject, body, sent_at, created_at)
             VALUES (?, 'email', ?, 'sent', ?, ?, ?, ?)"
        )->execute([$id, date('Y-m-d'), $subject, $body, $now, $now]);
        $rid = (int) $this->db->lastInsertId();
        Db::log($this->db, 'reminder', "Sent final notice for {$inv['number']}", 'reminder', $rid, $wid);
        Http::json(['ok' => true, 'subject' => $subject]);
    }

    private function apiUpdateTemplate(int $wid, int $id): void
    {
        $data = Http::bodyJson();
        $st = $this->db->prepare('SELECT * FROM templates WHERE id=? AND workspace_id=?');
        $st->execute([$id, $wid]);
        $existing = $st->fetch();
        if (!$existing) {
            Http::json(['error' => 'Not found'], 404);
        }
        $this->db->prepare(
            'UPDATE templates SET name=?, channel=?, subject=?, body=?, is_default=? WHERE id=? AND workspace_id=?'
        )->execute([
            $data['name'] ?? $existing['name'],
            $data['channel'] ?? $existing['channel'],
            $data['subject'] ?? $existing['subject'],
            $data['body'] ?? $existing['body'],
            !empty($data['is_default'] ?? $existing['is_default']) ? 1 : 0,
            $id, $wid,
        ]);
        $st->execute([$id, $wid]);
        Http::json($st->fetch());
    }

    private function apiGetSettings(int $wid): void
    {
        $settings = Workspace::settings($this->db, $wid);
        if ($settings) {
            $settings['reminder_offsets'] = json_decode($settings['reminder_offsets'] ?: '[]', true);
            $settings['plan'] = $settings['plan'] ?: 'trial';
        }
        Http::json($settings);
    }

    private function apiPutSettings(int $wid): void
    {
        $data = Http::bodyJson();
        $offsets = $data['reminder_offsets'] ?? [-3, 0, 3, 7, 14];
        if (is_string($offsets)) {
            $offsets = array_values(array_filter(array_map('intval', preg_split('/[,\s]+/', $offsets) ?: [])));
        }
        $this->db->prepare(
            'UPDATE settings SET business_name=?, owner_name=?, email=?, phone=?, website=?,
                currency=?, reminder_offsets=?, default_channel=?, smtp_enabled=? WHERE id=?'
        )->execute([
            $data['business_name'] ?? null,
            $data['owner_name'] ?? null,
            $data['email'] ?? null,
            $data['phone'] ?? null,
            $data['website'] ?? null,
            $data['currency'] ?? 'USD',
            json_encode($offsets),
            $data['default_channel'] ?? 'email',
            !empty($data['smtp_enabled']) ? 1 : 0,
            $wid,
        ]);
        Db::log($this->db, 'settings', 'Updated workspace settings', 'settings', $wid, $wid);
        $this->apiGetSettings($wid);
    }

    private function apiBillingStatus(int $wid): void
    {
        $cfg = Billing::config();
        $settings = Workspace::settings($this->db, $wid);
        $plans = [];
        foreach (Billing::PLANS as $key => $meta) {
            $plans[$key] = ['name' => $meta['name'], 'amount_label' => $meta['amount_label']];
        }
        Http::json([
            'enabled' => $cfg['enabled'],
            'publishable_key' => $cfg['publishable_key'],
            'plan' => $settings['plan'] ?? 'trial',
            'trial_ends_on' => $settings['trial_ends_on'] ?? null,
            'has_customer' => !empty($settings['stripe_customer_id']),
            'plans' => $plans,
        ]);
    }

    private function apiBillingCheckout(int $wid): void
    {
        $data = Http::bodyJson();
        $plan = strtolower(trim((string) ($data['plan'] ?? '')));
        if (!isset(Billing::PLANS[$plan])) {
            Http::json(['error' => 'Unknown plan. Use starter, pro, or annual.'], 400);
        }
        $cfg = Billing::config();
        if (!$cfg['enabled']) {
            Http::json(['error' => 'Stripe is not configured. Add keys and price IDs to .env (see .env.example).', 'demo' => true], 503);
        }
        $settings = Workspace::settings($this->db, $wid);
        $user = $this->db->prepare('SELECT * FROM users WHERE id = ?');
        $user->execute([(int) $_SESSION['user_id']]);
        $u = $user->fetch();
        try {
            $sess = Billing::createCheckout([
                'plan' => $plan,
                'customer_email' => $u['email'],
                'client_reference_id' => (string) $u['id'],
                'customer_id' => $settings['stripe_customer_id'] ?? null,
                'workspace_id' => $wid,
            ]);
        } catch (Throwable $e) {
            Http::json(['error' => $e->getMessage()], 400);
        }
        Db::log($this->db, 'billing', "Started Stripe checkout for {$plan}", 'settings', $wid, $wid);
        Http::json(['url' => $sess['url'] ?? null, 'id' => $sess['id'] ?? null]);
    }

    private function apiBillingPortal(int $wid): void
    {
        $cfg = Billing::config();
        if (!$cfg['enabled']) {
            Http::json(['error' => 'Stripe is not configured.'], 503);
        }
        $settings = Workspace::settings($this->db, $wid);
        if (!$settings || empty($settings['stripe_customer_id'])) {
            Http::json(['error' => 'No Stripe customer yet — subscribe first.'], 400);
        }
        try {
            $sess = Billing::createPortal($settings['stripe_customer_id']);
        } catch (Throwable $e) {
            Http::json(['error' => $e->getMessage()], 400);
        }
        Http::json(['url' => $sess['url'] ?? null]);
    }

    private function billingSuccess(): void
    {
        $this->requireLogin();
        $sessionId = $_GET['session_id'] ?? '';
        $cfg = Billing::config();
        if ($sessionId && $cfg['enabled']) {
            try {
                $checkout = Billing::retrieveCheckout($sessionId);
                $this->applyCheckoutSession($checkout);
            } catch (Throwable) {
            }
        }
        Http::redirect('/app#/settings');
    }

    private function stripeWebhook(): void
    {
        $cfg = Billing::config();
        $payload = file_get_contents('php://input') ?: '';
        $sig = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        if ($cfg['secret_key'] === '') {
            Http::json(['error' => 'Stripe not configured'], 503);
        }
        $wh = $cfg['webhook_secret'];
        if ($wh === '' || str_contains($wh, '...') || strlen($wh) < 20) {
            Http::json(['error' => 'STRIPE_WEBHOOK_SECRET is required'], 503);
        }
        try {
            $event = Billing::constructEvent($payload, $sig, $wh);
        } catch (Throwable $e) {
            Http::json(['error' => $e->getMessage()], 400);
        }
        $etype = $event['type'] ?? '';
        $obj = $event['data']['object'] ?? [];
        if ($etype === 'checkout.session.completed') {
            $this->applyCheckoutSession($obj);
        } elseif (in_array($etype, ['customer.subscription.updated', 'customer.subscription.created'], true)) {
            $this->applySubscription($obj);
        } elseif ($etype === 'customer.subscription.deleted') {
            $this->db->prepare(
                "UPDATE settings SET plan='trial', stripe_subscription_id=NULL WHERE stripe_customer_id=?"
            )->execute([$obj['customer'] ?? null]);
            Db::log($this->db, 'billing', 'Subscription cancelled — back to trial', 'settings', 1);
        }
        Http::json(['received' => true]);
    }

    private function resolveSettingsId(?string $workspaceId, ?string $userId, mixed $customer): ?int
    {
        if ($workspaceId !== null && trim($workspaceId) !== '') {
            return (int) $workspaceId;
        }
        if ($userId !== null && trim($userId) !== '') {
            $st = $this->db->prepare('SELECT workspace_id FROM users WHERE id = ?');
            $st->execute([(int) $userId]);
            $row = $st->fetch();
            if ($row && $row['workspace_id'] !== null) {
                return (int) $row['workspace_id'];
            }
        }
        if ($customer) {
            $st = $this->db->prepare('SELECT id FROM settings WHERE stripe_customer_id = ?');
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
        $subscription = $checkout['subscription'] ?? null;
        $subId = is_string($subscription) ? $subscription : ($subscription['id'] ?? null);
        $meta = $checkout['metadata'] ?? [];
        $plan = is_array($meta) ? ($meta['plan'] ?? null) : null;
        if (!$plan && is_array($subscription)) {
            $plan = $this->planFromSubscription($subscription);
        }
        $plan = $plan ?: 'starter';
        $userId = is_array($meta) ? ($meta['user_id'] ?? null) : null;
        $workspaceId = is_array($meta) ? ($meta['workspace_id'] ?? null) : null;
        if (!$userId) {
            $userId = $checkout['client_reference_id'] ?? null;
        }
        $sid = $this->resolveSettingsId(
            $workspaceId !== null ? (string) $workspaceId : null,
            $userId !== null ? (string) $userId : null,
            $customer
        );
        if ($sid === null) {
            return;
        }
        $this->db->prepare(
            'UPDATE settings SET plan=?, stripe_customer_id=COALESCE(?, stripe_customer_id),
                stripe_subscription_id=COALESCE(?, stripe_subscription_id) WHERE id=?'
        )->execute([$plan, $customer, $subId, $sid]);
        Db::log($this->db, 'billing', "Subscribed to {$plan} via Stripe", 'settings', $sid, $sid);
    }

    private function applySubscription(array $sub): void
    {
        $customer = $sub['customer'] ?? null;
        $subId = $sub['id'] ?? null;
        $plan = $this->planFromSubscription($sub) ?: 'starter';
        $status = $sub['status'] ?? '';
        $meta = $sub['metadata'] ?? [];
        $userId = is_array($meta) ? ($meta['user_id'] ?? null) : null;
        $workspaceId = is_array($meta) ? ($meta['workspace_id'] ?? null) : null;
        if (in_array($status, ['canceled', 'unpaid', 'incomplete_expired'], true)) {
            $this->db->prepare(
                "UPDATE settings SET plan='trial', stripe_subscription_id=NULL WHERE stripe_customer_id=?"
            )->execute([$customer]);
        } else {
            $sid = $this->resolveSettingsId(
                $workspaceId !== null ? (string) $workspaceId : null,
                $userId !== null ? (string) $userId : null,
                $customer
            );
            if ($sid === null) {
                return;
            }
            $this->db->prepare(
                'UPDATE settings SET plan=?, stripe_customer_id=?, stripe_subscription_id=? WHERE id=?'
            )->execute([$plan, $customer, $subId, $sid]);
        }
        Db::log($this->db, 'billing', "Subscription updated → {$plan} ({$status})", 'settings', 1, $workspaceId ? (int) $workspaceId : 1);
    }

    private function planFromSubscription(array $sub): ?string
    {
        $data = $sub['items']['data'] ?? null;
        if (!$data) {
            return is_array($sub['metadata'] ?? null) ? ($sub['metadata']['plan'] ?? null) : null;
        }
        $price = $data[0]['price'] ?? null;
        $priceId = is_string($price) ? $price : ($price['id'] ?? null);
        return Billing::planFromPriceId($priceId) ?: (is_array($sub['metadata'] ?? null) ? ($sub['metadata']['plan'] ?? null) : null);
    }
}
