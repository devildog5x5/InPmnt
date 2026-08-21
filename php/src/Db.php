<?php
declare(strict_types=1);

final class Db
{
    public const ADMIN_EMAIL = 'admin@inpmnt.app';
    public const ADMIN_NAME = 'Admin';
    public const ADMIN_PASSWORD = 'LifeMadeUSMCForged100!';
    public const DEMO_EMAIL = 'demouser@inpmnt.app';
    public const DEMO_NAME = 'Demo User';
    public const DEMO_PASSWORD = 'Demo';
    public const RESERVED_SIGNUP = [
        'admin@inpmnt.app',
        'demouser@inpmnt.app',
        'trialuser@inpmnt.app',
        'robert@inpmnt.app',
    ];

    public static function connect(string $path): PDO
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException("Cannot create database folder {$dir}. Make the data/ directory writable in File Manager.");
        }
        if (is_dir($dir) && !is_writable($dir)) {
            @chmod($dir, 0777);
        }
        if (!is_writable($dir)) {
            throw new RuntimeException("Database folder is not writable: {$dir}. In File Manager, set data/ permissions to 0755 or 0777.");
        }
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        return $pdo;
    }

    public static function init(PDO $db): void
    {
        $db->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL UNIQUE,
                name TEXT NOT NULL,
                password_hash TEXT NOT NULL,
                workspace_id INTEGER,
                role TEXT NOT NULL DEFAULT 'user',
                created_at TEXT NOT NULL
            );
            CREATE TABLE IF NOT EXISTS settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                owner_user_id INTEGER,
                business_name TEXT NOT NULL,
                owner_name TEXT NOT NULL,
                email TEXT NOT NULL,
                phone TEXT,
                website TEXT,
                currency TEXT NOT NULL DEFAULT 'USD',
                reminder_offsets TEXT NOT NULL,
                default_channel TEXT NOT NULL DEFAULT 'email',
                smtp_enabled INTEGER NOT NULL DEFAULT 0,
                trial_ends_on TEXT,
                plan TEXT NOT NULL DEFAULT 'trial',
                stripe_customer_id TEXT,
                stripe_subscription_id TEXT
            );
            CREATE TABLE IF NOT EXISTS clients (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                workspace_id INTEGER NOT NULL DEFAULT 1,
                name TEXT NOT NULL,
                company TEXT,
                email TEXT,
                phone TEXT,
                notes TEXT,
                created_at TEXT NOT NULL
            );
            CREATE TABLE IF NOT EXISTS invoices (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                workspace_id INTEGER NOT NULL DEFAULT 1,
                number TEXT NOT NULL,
                client_id INTEGER NOT NULL REFERENCES clients(id) ON DELETE CASCADE,
                title TEXT NOT NULL,
                amount REAL NOT NULL,
                amount_paid REAL NOT NULL DEFAULT 0,
                currency TEXT NOT NULL DEFAULT 'USD',
                issue_date TEXT NOT NULL,
                due_date TEXT NOT NULL,
                status TEXT NOT NULL,
                notes TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                UNIQUE (workspace_id, number)
            );
            CREATE TABLE IF NOT EXISTS payments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                invoice_id INTEGER NOT NULL REFERENCES invoices(id) ON DELETE CASCADE,
                amount REAL NOT NULL,
                method TEXT,
                paid_at TEXT NOT NULL,
                note TEXT,
                created_at TEXT NOT NULL
            );
            CREATE TABLE IF NOT EXISTS templates (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                workspace_id INTEGER NOT NULL DEFAULT 1,
                name TEXT NOT NULL,
                channel TEXT NOT NULL,
                subject TEXT,
                body TEXT NOT NULL,
                is_default INTEGER NOT NULL DEFAULT 0
            );
            CREATE TABLE IF NOT EXISTS reminders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                invoice_id INTEGER NOT NULL REFERENCES invoices(id) ON DELETE CASCADE,
                channel TEXT NOT NULL,
                scheduled_for TEXT NOT NULL,
                status TEXT NOT NULL,
                subject TEXT,
                body TEXT NOT NULL,
                sent_at TEXT,
                created_at TEXT NOT NULL
            );
            CREATE TABLE IF NOT EXISTS activity (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                workspace_id INTEGER NOT NULL DEFAULT 1,
                kind TEXT NOT NULL,
                message TEXT NOT NULL,
                entity_type TEXT,
                entity_id INTEGER,
                created_at TEXT NOT NULL
            );
        SQL);
        $exists = $db->query('SELECT id FROM settings LIMIT 1')->fetch();
        if (!$exists) {
            self::seed($db);
        } else {
            self::ensureSystemAccounts($db);
        }
    }

    public static function now(): string
    {
        return gmdate('Y-m-d\TH:i:s\Z');
    }

    public static function log(
        PDO $db,
        string $kind,
        string $message,
        ?string $entityType = null,
        ?int $entityId = null,
        ?int $workspaceId = null
    ): void {
        $wid = $workspaceId ?? 1;
        $st = $db->prepare(
            'INSERT INTO activity (workspace_id, kind, message, entity_type, entity_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $st->execute([$wid, $kind, $message, $entityType, $entityId, self::now()]);
    }

    public static function insertDefaultTemplates(PDO $db, int $workspaceId): void
    {
        $defs = [
            ['Friendly nudge', 'email', 'Quick reminder about invoice {{number}}',
                "Hi {{client_name}},\n\nJust a friendly reminder that invoice {{number}} for {{amount_due}} is due on {{due_date}}.\n\nYou can reply to this email if you have any questions.\n\nThanks,\n{{business_name}}", 1],
            ['Due today', 'email', 'Invoice {{number}} is due today',
                "Hi {{client_name}},\n\nInvoice {{number}} for {{amount_due}} is due today. Please let us know if payment is already on the way.\n\nAppreciate you,\n{{business_name}}", 0],
            ['Overdue follow-up', 'email', 'Past due: invoice {{number}}',
                "Hi {{client_name}},\n\nInvoice {{number}} for {{amount_due}} was due on {{due_date}} and remains unpaid.\n\nPlease arrange payment at your earliest convenience, or reply so we can help.\n\n{{business_name}}", 0],
            ['SMS short', 'sms', null,
                'Hi {{client_name}} — invoice {{number}} ({{amount_due}}) is {{status}}. Reply STOP to opt out. — {{business_name}}', 1],
            ['Final notice', 'email', 'Final notice: invoice {{number}}',
                "Hi {{client_name}},\n\nThis is a final notice regarding unpaid invoice {{number}} ({{amount_due}}), originally due {{due_date}}.\n\nPlease remit payment promptly to avoid further collection steps.\n\n{{business_name}}", 0],
        ];
        $st = $db->prepare(
            'INSERT INTO templates (workspace_id, name, channel, subject, body, is_default) VALUES (?, ?, ?, ?, ?, ?)'
        );
        foreach ($defs as $d) {
            $st->execute([$workspaceId, $d[0], $d[1], $d[2], $d[3], $d[4]]);
        }
    }

    public static function createWorkspace(
        PDO $db,
        string $email,
        string $name,
        string $passwordHash,
        ?string $businessName = null,
        string $role = 'user'
    ): array {
        $now = self::now();
        $offsets = json_encode([-3, 0, 3, 7, 14]);
        $biz = trim($businessName ?: ($name . "'s business")) ?: 'My business';
        $userRole = strtolower($role) === 'admin' ? 'admin' : 'user';
        $trial = (new DateTimeImmutable('today'))->modify('+14 days')->format('Y-m-d');
        $db->prepare(
            'INSERT INTO settings (business_name, owner_name, email, phone, website, currency,
                reminder_offsets, default_channel, smtp_enabled, trial_ends_on, plan)
             VALUES (?, ?, ?, NULL, NULL, \'USD\', ?, \'email\', 0, ?, \'trial\')'
        )->execute([$biz, $name, $email, $offsets, $trial]);
        $wid = (int) $db->lastInsertId();
        $db->prepare(
            'INSERT INTO users (email, name, password_hash, workspace_id, role, created_at)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$email, $name, $passwordHash, $wid, $userRole, $now]);
        $uid = (int) $db->lastInsertId();
        $db->prepare('UPDATE settings SET owner_user_id = ? WHERE id = ?')->execute([$uid, $wid]);
        self::insertDefaultTemplates($db, $wid);
        self::log($db, 'system', "Workspace created for {$name}", 'settings', $wid, $wid);
        return [$uid, $wid];
    }

    public static function nextInvoiceNumber(PDO $db, int $workspaceId): string
    {
        $st = $db->prepare('SELECT number FROM invoices WHERE workspace_id=? ORDER BY id DESC LIMIT 1');
        $st->execute([$workspaceId]);
        $row = $st->fetch();
        if (!$row) {
            return 'INV-1001';
        }
        $parts = explode('-', (string) $row['number']);
        $n = (int) end($parts) + 1;
        if ($n < 2) {
            $c = (int) $db->query('SELECT COUNT(*) FROM invoices WHERE workspace_id=' . (int) $workspaceId)->fetchColumn();
            $n = $c + 1001;
        }
        return 'INV-' . $n;
    }

    public static function refreshInvoiceStatus(PDO $db, int $invoiceId): void
    {
        $st = $db->prepare('SELECT * FROM invoices WHERE id = ?');
        $st->execute([$invoiceId]);
        $inv = $st->fetch();
        if (!$inv || $inv['status'] === 'draft') {
            return;
        }
        $balance = round((float) $inv['amount'] - (float) $inv['amount_paid'], 2);
        $today = date('Y-m-d');
        if ($balance <= 0) {
            $status = 'paid';
        } elseif ((float) $inv['amount_paid'] > 0) {
            $status = $inv['due_date'] >= $today ? 'partial' : 'overdue';
        } elseif ($inv['due_date'] < $today) {
            $status = 'overdue';
        } else {
            $status = 'sent';
        }
        $db->prepare('UPDATE invoices SET status = ?, updated_at = ? WHERE id = ?')
            ->execute([$status, self::now(), $invoiceId]);
    }

    public static function money(float $n): string
    {
        return '$' . number_format($n, 2);
    }

    public static function renderVars(?string $text, array $ctx): string
    {
        if ($text === null || $text === '') {
            return '';
        }
        $out = $text;
        foreach ($ctx as $k => $v) {
            $out = str_replace('{{' . $k . '}}', (string) $v, $out);
        }
        return $out;
    }

    public static function invoiceBalance(array $inv): float
    {
        return round((float) $inv['amount'] - (float) $inv['amount_paid'], 2);
    }

    private static function ensureSystemAccounts(PDO $db): void
    {
        $demo = $db->prepare('SELECT id, password_hash FROM users WHERE lower(email) = ?');
        $demo->execute([self::DEMO_EMAIL]);
        $row = $demo->fetch();
        if ($row) {
            if (!password_verify(self::DEMO_PASSWORD, $row['password_hash'])) {
                $db->prepare('UPDATE users SET password_hash=?, name=?, role=? WHERE id=?')
                    ->execute([password_hash(self::DEMO_PASSWORD, PASSWORD_DEFAULT), self::DEMO_NAME, 'user', $row['id']]);
            }
        } else {
            self::createWorkspace(
                $db,
                self::DEMO_EMAIL,
                self::DEMO_NAME,
                password_hash(self::DEMO_PASSWORD, PASSWORD_DEFAULT),
                'Foster Field Services',
                'user'
            );
        }
        $admin = $db->prepare('SELECT id, password_hash FROM users WHERE lower(email) = ?');
        $admin->execute([self::ADMIN_EMAIL]);
        $arow = $admin->fetch();
        if ($arow) {
            if (!password_verify(self::ADMIN_PASSWORD, $arow['password_hash'])) {
                $db->prepare('UPDATE users SET password_hash=?, name=?, role=? WHERE id=?')
                    ->execute([password_hash(self::ADMIN_PASSWORD, PASSWORD_DEFAULT), self::ADMIN_NAME, 'admin', $arow['id']]);
            }
        } else {
            self::createWorkspace(
                $db,
                self::ADMIN_EMAIL,
                self::ADMIN_NAME,
                password_hash(self::ADMIN_PASSWORD, PASSWORD_DEFAULT),
                'InPmnt Admin',
                'admin'
            );
        }
    }

    private static function seed(PDO $db): void
    {
        $now = self::now();
        $today = new DateTimeImmutable('today');
        self::createWorkspace(
            $db,
            self::ADMIN_EMAIL,
            self::ADMIN_NAME,
            password_hash(self::ADMIN_PASSWORD, PASSWORD_DEFAULT),
            'InPmnt Admin',
            'admin'
        );
        [, $wid] = self::createWorkspace(
            $db,
            self::DEMO_EMAIL,
            self::DEMO_NAME,
            password_hash(self::DEMO_PASSWORD, PASSWORD_DEFAULT),
            'Foster Field Services',
            'user'
        );
        $db->prepare('UPDATE settings SET phone=?, website=? WHERE id=?')
            ->execute(['(555) 014-2200', 'https://inpmnt.app', $wid]);

        $clients = [
            ['Maya Chen', 'Chen Landscape Co.', 'maya@chenlandscape.com', '(555) 201-8841', 'Prefers email'],
            ['Jake Ortiz', 'Ortiz Plumbing', 'jake@ortizplumbing.com', '(555) 441-9022', 'Pays by check'],
            ['Priya Shah', 'Studio North Photo', 'priya@studionorth.co', '(555) 778-1104', ''],
            ['Tom Reeves', 'Reeves Consulting', 'tom@reeves.co', '(555) 332-6710', 'Net 15'],
        ];
        $ids = [];
        $ins = $db->prepare(
            'INSERT INTO clients (workspace_id, name, company, email, phone, notes, created_at) VALUES (?,?,?,?,?,?,?)'
        );
        foreach ($clients as $c) {
            $ins->execute([$wid, $c[0], $c[1], $c[2], $c[3], $c[4], $now]);
            $ids[] = (int) $db->lastInsertId();
        }

        $invoices = [
            ['INV-1001', $ids[0], 'Spring irrigation tune-up', 1850.00, 0, $today->modify('-20 days'), $today->modify('-5 days'), 'overdue'],
            ['INV-1002', $ids[1], 'Water heater install', 2460.00, 1000.00, $today->modify('-12 days'), $today->modify('+2 days'), 'partial'],
            ['INV-1003', $ids[2], 'Brand shoot — May', 3200.00, 0, $today->modify('-3 days'), $today->modify('+11 days'), 'sent'],
            ['INV-1004', $ids[3], 'Ops retainer — July', 1500.00, 1500.00, $today->modify('-40 days'), $today->modify('-25 days'), 'paid'],
            ['INV-1005', $ids[0], 'Patio lighting package', 980.00, 0, $today, $today->modify('+14 days'), 'draft'],
            ['INV-1006', $ids[1], 'Emergency leak repair', 640.00, 0, $today->modify('-18 days'), $today->modify('-11 days'), 'overdue'],
        ];
        $ii = $db->prepare(
            'INSERT INTO invoices (workspace_id, number, client_id, title, amount, amount_paid, currency,
                issue_date, due_date, status, notes, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, \'USD\', ?, ?, ?, \'\', ?, ?)'
        );
        foreach ($invoices as $inv) {
            $ii->execute([
                $wid, $inv[0], $inv[1], $inv[2], $inv[3], $inv[4],
                $inv[5]->format('Y-m-d'), $inv[6]->format('Y-m-d'), $inv[7], $now, $now,
            ]);
        }

        $st = $db->prepare('SELECT id FROM invoices WHERE workspace_id=? AND number=?');
        $st->execute([$wid, 'INV-1004']);
        $inv = $st->fetch();
        if ($inv) {
            $db->prepare(
                'INSERT INTO payments (invoice_id, amount, method, paid_at, note, created_at) VALUES (?,?,?,?,?,?)'
            )->execute([$inv['id'], 1500.00, 'ACH', $today->modify('-24 days')->format('Y-m-d'), 'Paid in full', $now]);
        }
        $st->execute([$wid, 'INV-1002']);
        $inv2 = $st->fetch();
        if ($inv2) {
            $db->prepare(
                'INSERT INTO payments (invoice_id, amount, method, paid_at, note, created_at) VALUES (?,?,?,?,?,?)'
            )->execute([$inv2['id'], 1000.00, 'Card', $today->modify('-10 days')->format('Y-m-d'), 'Deposit', $now]);
        }

        $open = $db->prepare(
            "SELECT * FROM invoices WHERE workspace_id=? AND status IN ('sent','partial','overdue')"
        );
        $open->execute([$wid]);
        $offsets = [-3, 0, 3, 7, 14];
        $ri = $db->prepare(
            'INSERT INTO reminders (invoice_id, channel, scheduled_for, status, subject, body, sent_at, created_at)
             VALUES (?, \'email\', ?, ?, ?, ?, ?, ?)'
        );
        foreach ($open->fetchAll() as $invRow) {
            $due = new DateTimeImmutable($invRow['due_date']);
            foreach ($offsets as $offset) {
                $scheduled = $due->modify(($offset >= 0 ? '+' : '') . $offset . ' days');
                $status = 'pending';
                $sentAt = null;
                if ($scheduled < $today) {
                    $status = 'sent';
                    $sentAt = $scheduled->format('Y-m-d') . 'T09:00:00Z';
                } elseif ($scheduled == $today) {
                    $status = 'due';
                }
                $bal = (float) $invRow['amount'] - (float) $invRow['amount_paid'];
                $body = sprintf('Reminder for %s: $%s due %s.', $invRow['number'], number_format($bal, 2), $invRow['due_date']);
                $ri->execute([
                    $invRow['id'], $scheduled->format('Y-m-d'), $status,
                    'Reminder: ' . $invRow['number'], $body, $sentAt, $now,
                ]);
            }
        }
        self::log($db, 'invoice', 'Demo invoices and reminder schedule loaded', 'invoice', null, $wid);
    }
}
