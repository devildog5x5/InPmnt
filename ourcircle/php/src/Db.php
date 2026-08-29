<?php
declare(strict_types=1);

final class Db
{
    public static function connect(string $path): PDO
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        @chmod($dir, 0775);
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        if (is_file($path)) {
            @chmod($path, 0660);
        }
        return $pdo;
    }

    public static function now(): string
    {
        return gmdate('Y-m-d\TH:i:s\Z');
    }

    public static function init(PDO $db): void
    {
        $db->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS households (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                plan TEXT NOT NULL DEFAULT 'yearly',
                founding INTEGER NOT NULL DEFAULT 0,
                stripe_customer_id TEXT,
                stripe_subscription_id TEXT,
                stripe_status TEXT,
                created_at TEXT NOT NULL
            );
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                household_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT 'owner',
                totp_secret TEXT,
                totp_enabled INTEGER NOT NULL DEFAULT 0,
                recovery_codes TEXT,
                created_at TEXT NOT NULL,
                FOREIGN KEY (household_id) REFERENCES households(id)
            );
            CREATE TABLE IF NOT EXISTS invitations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                household_id INTEGER NOT NULL,
                email TEXT NOT NULL,
                name TEXT,
                token TEXT NOT NULL UNIQUE,
                status TEXT NOT NULL DEFAULT 'pending',
                created_at TEXT NOT NULL,
                FOREIGN KEY (household_id) REFERENCES households(id)
            );
            CREATE TABLE IF NOT EXISTS trusted_contacts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                household_id INTEGER NOT NULL,
                kind TEXT NOT NULL,
                name TEXT NOT NULL,
                phone TEXT,
                website TEXT,
                notes TEXT,
                created_at TEXT NOT NULL,
                FOREIGN KEY (household_id) REFERENCES households(id)
            );
            CREATE TABLE IF NOT EXISTS checks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                household_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                kind TEXT NOT NULL,
                raw_text TEXT,
                phone TEXT,
                url TEXT,
                screenshot TEXT,
                risk TEXT NOT NULL,
                report_json TEXT NOT NULL,
                created_at TEXT NOT NULL,
                FOREIGN KEY (household_id) REFERENCES households(id),
                FOREIGN KEY (user_id) REFERENCES users(id)
            );
            CREATE TABLE IF NOT EXISTS reviews (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                check_id INTEGER NOT NULL,
                household_id INTEGER NOT NULL,
                requester_id INTEGER NOT NULL,
                comment TEXT,
                status TEXT NOT NULL DEFAULT 'asked',
                created_at TEXT NOT NULL,
                FOREIGN KEY (check_id) REFERENCES checks(id)
            );
            CREATE TABLE IF NOT EXISTS alerts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                check_id INTEGER,
                household_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                message TEXT NOT NULL,
                created_at TEXT NOT NULL,
                FOREIGN KEY (household_id) REFERENCES households(id)
            );
            CREATE TABLE IF NOT EXISTS password_resets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                token_hash TEXT NOT NULL UNIQUE,
                expires_at TEXT NOT NULL,
                created_at TEXT NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id)
            );
            CREATE TABLE IF NOT EXISTS reservations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                product TEXT NOT NULL,
                name TEXT NOT NULL,
                email TEXT NOT NULL,
                offer TEXT NOT NULL,
                note TEXT,
                created_at TEXT NOT NULL
            );
        SQL);
        self::migrateHouseholdStripe($db);
        self::migrateUserAuth($db);
        $n = (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
        if ($n === 0) {
            self::seed($db);
        }
    }

    public static function migrateHouseholdStripe(PDO $db): void
    {
        $cols = $db->query('PRAGMA table_info(households)')->fetchAll();
        $names = [];
        foreach ($cols as $col) {
            $names[] = (string) ($col['name'] ?? '');
        }
        $add = [
            'stripe_customer_id' => 'TEXT',
            'stripe_subscription_id' => 'TEXT',
            'stripe_status' => 'TEXT',
        ];
        foreach ($add as $col => $type) {
            if (!in_array($col, $names, true)) {
                $db->exec("ALTER TABLE households ADD COLUMN {$col} {$type}");
            }
        }
    }

    public static function migrateUserAuth(PDO $db): void
    {
        $cols = $db->query('PRAGMA table_info(users)')->fetchAll();
        $names = [];
        foreach ($cols as $col) {
            $names[] = (string) ($col['name'] ?? '');
        }
        $add = [
            'totp_secret' => 'TEXT',
            'totp_enabled' => 'INTEGER NOT NULL DEFAULT 0',
            'recovery_codes' => 'TEXT',
        ];
        foreach ($add as $col => $type) {
            if (!in_array($col, $names, true)) {
                $db->exec("ALTER TABLE users ADD COLUMN {$col} {$type}");
            }
        }
        $db->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS password_resets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                token_hash TEXT NOT NULL UNIQUE,
                expires_at TEXT NOT NULL,
                created_at TEXT NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id)
            );
        SQL);
    }

    public static function seed(PDO $db): void
    {
        $ts = self::now();
        $db->prepare('INSERT INTO households (name, plan, founding, created_at) VALUES (?,?,?,?)')
            ->execute(['Foster family circle', 'yearly', 0, $ts]);
        $hid = (int) $db->lastInsertId();
        $db->prepare(
            'INSERT INTO users (household_id, name, email, password_hash, role, created_at) VALUES (?,?,?,?,?,?)'
        )->execute([$hid, 'Pat Foster', 'family@ourcircle.app', password_hash('password123', PASSWORD_DEFAULT), 'owner', $ts]);
        $samples = [
            ['bank', 'First National (example)', '8005550100', 'https://example-bank.invalid', 'Use the number on the back of the debit card.'],
            ['doctor', 'Family clinic', '8005550142', '', 'Ask for the nurse line, not a callback from a text.'],
            ['utility', 'City power company', '8005550199', '', 'Printed on the monthly bill.'],
            ['family', 'Jordan (adult child)', '5550108888', '', 'Call before any unexpected payment request.'],
        ];
        $st = $db->prepare(
            'INSERT INTO trusted_contacts (household_id, kind, name, phone, website, notes, created_at) VALUES (?,?,?,?,?,?,?)'
        );
        foreach ($samples as $row) {
            $st->execute([$hid, $row[0], $row[1], $row[2], $row[3], $row[4], $ts]);
        }
    }

    public static function createHousehold(PDO $db, string $name, string $ownerName, string $email, string $password): int
    {
        $ts = self::now();
        $db->prepare('INSERT INTO households (name, plan, founding, created_at) VALUES (?,?,?,?)')
            ->execute([$name !== '' ? $name : ($ownerName . "'s circle"), 'yearly', 0, $ts]);
        $hid = (int) $db->lastInsertId();
        $db->prepare(
            'INSERT INTO users (household_id, name, email, password_hash, role, created_at) VALUES (?,?,?,?,?,?)'
        )->execute([$hid, $ownerName, strtolower(trim($email)), password_hash($password, PASSWORD_DEFAULT), 'owner', $ts]);
        return $hid;
    }

    public static function authenticate(PDO $db, string $email, string $password): ?array
    {
        $st = $db->prepare('SELECT * FROM users WHERE lower(email)=?');
        $st->execute([strtolower(trim($email))]);
        $row = $st->fetch();
        if (!$row || !password_verify($password, $row['password_hash'])) {
            return null;
        }
        return $row;
    }

    public static function members(PDO $db, int $hid): array
    {
        $st = $db->prepare('SELECT id, name, email, role FROM users WHERE household_id=? ORDER BY id');
        $st->execute([$hid]);
        return $st->fetchAll();
    }

    public static function pending(PDO $db, int $hid): array
    {
        $st = $db->prepare("SELECT id, email, name, status, token FROM invitations WHERE household_id=? AND status='pending' ORDER BY id");
        $st->execute([$hid]);
        return $st->fetchAll();
    }

    public static function trusted(PDO $db, int $hid): array
    {
        $st = $db->prepare('SELECT * FROM trusted_contacts WHERE household_id=? ORDER BY kind, name');
        $st->execute([$hid]);
        return $st->fetchAll();
    }

    public static function invite(PDO $db, int $hid, string $email, string $name = ''): array
    {
        $users = self::members($db, $hid);
        $pending = self::pending($db, $hid);
        if (count($users) + count($pending) >= 5) {
            throw new RuntimeException('The family plan includes up to five people. Remove someone or upgrade with us later.');
        }
        $email = strtolower(trim($email));
        $token = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
        $db->prepare(
            "INSERT INTO invitations (household_id, email, name, token, status, created_at) VALUES (?,?,?,?,'pending',?)"
        )->execute([$hid, $email, trim($name), $token, self::now()]);
        return ['email' => $email, 'token' => $token];
    }

    public static function acceptInvite(PDO $db, string $token, string $name, string $password): array
    {
        $st = $db->prepare("SELECT * FROM invitations WHERE token=? AND status='pending'");
        $st->execute([$token]);
        $inv = $st->fetch();
        if (!$inv) {
            throw new RuntimeException('That invite is not valid anymore.');
        }
        $ex = $db->prepare('SELECT id FROM users WHERE lower(email)=?');
        $ex->execute([$inv['email']]);
        if ($ex->fetch()) {
            throw new RuntimeException('That email already has an OurCircle login. Sign in instead.');
        }
        $db->prepare(
            "INSERT INTO users (household_id, name, email, password_hash, role, created_at) VALUES (?,?,?,?, 'member', ?)"
        )->execute([$inv['household_id'], trim($name), $inv['email'], password_hash($password, PASSWORD_DEFAULT), self::now()]);
        $db->prepare('UPDATE invitations SET status=? WHERE id=?')->execute(['accepted', $inv['id']]);
        $u = $db->prepare('SELECT * FROM users WHERE lower(email)=?');
        $u->execute([$inv['email']]);
        return $u->fetch();
    }
}
