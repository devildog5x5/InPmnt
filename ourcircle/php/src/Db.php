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
                last_access_at TEXT,
                phone TEXT,
                sms_opt_out INTEGER NOT NULL DEFAULT 0,
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
                email_sent_at TEXT,
                accepted_at TEXT,
                phone TEXT,
                sms_sent_at TEXT,
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
        self::migrateCircleStatus($db);
        self::migrateSms($db);
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

    public static function migrateCircleStatus(PDO $db): void
    {
        $userCols = [];
        foreach ($db->query('PRAGMA table_info(users)')->fetchAll() as $col) {
            $userCols[] = (string) ($col['name'] ?? '');
        }
        if (!in_array('last_access_at', $userCols, true)) {
            $db->exec('ALTER TABLE users ADD COLUMN last_access_at TEXT');
        }
        $invCols = [];
        foreach ($db->query('PRAGMA table_info(invitations)')->fetchAll() as $col) {
            $invCols[] = (string) ($col['name'] ?? '');
        }
        if (!in_array('email_sent_at', $invCols, true)) {
            $db->exec('ALTER TABLE invitations ADD COLUMN email_sent_at TEXT');
        }
        if (!in_array('accepted_at', $invCols, true)) {
            $db->exec('ALTER TABLE invitations ADD COLUMN accepted_at TEXT');
        }
    }

    public static function migrateSms(PDO $db): void
    {
        $userCols = [];
        foreach ($db->query('PRAGMA table_info(users)')->fetchAll() as $col) {
            $userCols[] = (string) ($col['name'] ?? '');
        }
        if (!in_array('phone', $userCols, true)) {
            $db->exec('ALTER TABLE users ADD COLUMN phone TEXT');
        }
        if (!in_array('sms_opt_out', $userCols, true)) {
            $db->exec('ALTER TABLE users ADD COLUMN sms_opt_out INTEGER NOT NULL DEFAULT 0');
        }
        $invCols = [];
        foreach ($db->query('PRAGMA table_info(invitations)')->fetchAll() as $col) {
            $invCols[] = (string) ($col['name'] ?? '');
        }
        if (!in_array('phone', $invCols, true)) {
            $db->exec('ALTER TABLE invitations ADD COLUMN phone TEXT');
        }
        if (!in_array('sms_sent_at', $invCols, true)) {
            $db->exec('ALTER TABLE invitations ADD COLUMN sms_sent_at TEXT');
        }
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

    public static function createHousehold(PDO $db, string $name, string $ownerName, string $email, string $password, string $phone = ''): int
    {
        $ts = self::now();
        $db->prepare('INSERT INTO households (name, plan, founding, created_at) VALUES (?,?,?,?)')
            ->execute([$name !== '' ? $name : ($ownerName . "'s circle"), 'yearly', 0, $ts]);
        $hid = (int) $db->lastInsertId();
        $db->prepare(
            'INSERT INTO users (household_id, name, email, password_hash, role, created_at, phone) VALUES (?,?,?,?,?,?,?)'
        )->execute([
            $hid,
            $ownerName,
            strtolower(trim($email)),
            password_hash($password, PASSWORD_DEFAULT),
            'owner',
            $ts,
            $phone !== '' ? $phone : null,
        ]);
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
        $st = $db->prepare('SELECT * FROM users WHERE household_id=? ORDER BY id');
        $st->execute([$hid]);
        return $st->fetchAll();
    }

    public static function pending(PDO $db, int $hid): array
    {
        $st = $db->prepare("SELECT * FROM invitations WHERE household_id=? AND status='pending' ORDER BY id");
        $st->execute([$hid]);
        return $st->fetchAll();
    }

    /** @param list<array<string,mixed>> $members */
    /** @param list<array<string,mixed>> $pending */
    public static function decorateCircleStatus(array $members, array $pending): array
    {
        foreach ($members as &$m) {
            if (($m['role'] ?? '') === 'owner' || !empty($m['last_access_at'])) {
                $m['circle_status'] = 'User Accesses the Circle';
                $m['circle_status_key'] = 'access';
            } else {
                $m['circle_status'] = 'Invite Accepted';
                $m['circle_status_key'] = 'accepted';
            }
        }
        unset($m);
        foreach ($pending as &$p) {
            if (!empty($p['email_sent_at']) || !empty($p['sms_sent_at'])) {
                $p['circle_status'] = 'Invite sent';
                $p['circle_status_key'] = 'sent';
            } else {
                $p['circle_status'] = 'Invited';
                $p['circle_status_key'] = 'invited';
            }
        }
        unset($p);
        return [$members, $pending];
    }

    public static function markInviteSent(PDO $db, int $id): void
    {
        $db->prepare('UPDATE invitations SET email_sent_at=? WHERE id=? AND email_sent_at IS NULL')->execute([self::now(), $id]);
    }

    public static function markInviteSmsSent(PDO $db, int $id): void
    {
        $db->prepare('UPDATE invitations SET sms_sent_at=? WHERE id=? AND sms_sent_at IS NULL')->execute([self::now(), $id]);
    }

    public static function phoneTaken(PDO $db, string $phone, ?int $exceptUserId = null, ?int $exceptInviteId = null): bool
    {
        if ($phone === '') {
            return false;
        }
        $st = $db->prepare('SELECT id FROM users WHERE phone=?');
        $st->execute([$phone]);
        $row = $st->fetch();
        if ($row && ($exceptUserId === null || (int) $row['id'] !== $exceptUserId)) {
            return true;
        }
        $inv = $db->prepare("SELECT id FROM invitations WHERE phone=? AND status='pending'");
        $inv->execute([$phone]);
        $pending = $inv->fetch();
        if ($pending && ($exceptInviteId === null || (int) $pending['id'] !== $exceptInviteId)) {
            return true;
        }
        return false;
    }

    public static function userByPhone(PDO $db, string $phone): ?array
    {
        if ($phone === '') {
            return null;
        }
        $st = $db->prepare('SELECT * FROM users WHERE phone=?');
        $st->execute([$phone]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public static function pendingByPhone(PDO $db, string $phone): ?array
    {
        if ($phone === '') {
            return null;
        }
        $st = $db->prepare("SELECT * FROM invitations WHERE phone=? AND status='pending' ORDER BY id DESC");
        $st->execute([$phone]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public static function pendingInvite(PDO $db, int $hid, int $inviteId): ?array
    {
        $st = $db->prepare("SELECT * FROM invitations WHERE id=? AND household_id=? AND status='pending'");
        $st->execute([$inviteId, $hid]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public static function setUserPhone(PDO $db, int $userId, string $phone, bool $optOut = false): void
    {
        if ($phone !== '' && self::phoneTaken($db, $phone, $userId)) {
            throw new RuntimeException('That mobile number is already on another Family Shield Pro login.');
        }
        $db->prepare('UPDATE users SET phone=?, sms_opt_out=? WHERE id=?')
            ->execute([$phone !== '' ? $phone : null, $optOut ? 1 : 0, $userId]);
    }

    public static function setSmsOptOut(PDO $db, int $userId, bool $optOut): void
    {
        $db->prepare('UPDATE users SET sms_opt_out=? WHERE id=?')->execute([$optOut ? 1 : 0, $userId]);
    }

    public static function touchLastAccess(PDO $db, int $userId): void
    {
        $db->prepare('UPDATE users SET last_access_at=? WHERE id=?')->execute([self::now(), $userId]);
    }

    public static function trusted(PDO $db, int $hid): array
    {
        $st = $db->prepare('SELECT * FROM trusted_contacts WHERE household_id=? ORDER BY kind, name');
        $st->execute([$hid]);
        return $st->fetchAll();
    }

    public static function invite(PDO $db, int $hid, string $email, string $name = '', string $phone = ''): array
    {
        $users = self::members($db, $hid);
        $pending = self::pending($db, $hid);
        if (count($users) + count($pending) >= 5) {
            throw new RuntimeException('The family plan includes up to five people. Remove someone or upgrade with us later.');
        }
        $email = strtolower(trim($email));
        if ($email === '' || !str_contains($email, '@')) {
            throw new RuntimeException('Need an email address to invite.');
        }
        $phone = trim($phone);
        if ($phone !== '' && self::userByPhone($db, $phone)) {
            $phone = '';
        }
        $token = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
        $db->prepare(
            "INSERT INTO invitations (household_id, email, name, token, status, created_at, phone) VALUES (?,?,?,?,'pending',?,?)"
        )->execute([$hid, $email, trim($name), $token, self::now(), $phone !== '' ? $phone : null]);
        return ['email' => $email, 'token' => $token, 'id' => (int) $db->lastInsertId(), 'phone' => $phone];
    }

    public static function acceptInvite(PDO $db, string $token, string $name, string $password, string $phone = ''): array
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
        $stored = trim($phone) !== '' ? trim($phone) : (string) ($inv['phone'] ?? '');
        if ($stored !== '' && self::phoneTaken($db, $stored, null, (int) $inv['id'])) {
            $stored = '';
        }
        $db->prepare(
            "INSERT INTO users (household_id, name, email, password_hash, role, created_at, phone) VALUES (?,?,?,?, 'member', ?, ?)"
        )->execute([
            $inv['household_id'],
            trim($name),
            $inv['email'],
            password_hash($password, PASSWORD_DEFAULT),
            self::now(),
            $stored !== '' ? $stored : null,
        ]);
        $db->prepare('UPDATE invitations SET status=?, accepted_at=? WHERE id=?')->execute(['accepted', self::now(), $inv['id']]);
        $u = $db->prepare('SELECT * FROM users WHERE lower(email)=?');
        $u->execute([$inv['email']]);
        return $u->fetch();
    }

    public static function pendingInviteById(PDO $db, int $inviteId): ?array
    {
        $st = $db->prepare("SELECT * FROM invitations WHERE id=? AND status='pending'");
        $st->execute([$inviteId]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public static function cancelPendingInvite(PDO $db, int $inviteId): bool
    {
        $st = $db->prepare("DELETE FROM invitations WHERE id=? AND status='pending'");
        $st->execute([$inviteId]);
        return $st->rowCount() > 0;
    }

    /** @return array{households:int,users:int,pending_invites:int,trusted:int,checks:int} */
    public static function adminCounts(PDO $db): array
    {
        $count = static function (string $sql) use ($db): int {
            return (int) $db->query($sql)->fetchColumn();
        };
        return [
            'households' => $count('SELECT COUNT(*) FROM households'),
            'users' => $count('SELECT COUNT(*) FROM users'),
            'pending_invites' => $count("SELECT COUNT(*) FROM invitations WHERE status='pending'"),
            'trusted' => $count('SELECT COUNT(*) FROM trusted_contacts'),
            'checks' => $count('SELECT COUNT(*) FROM checks'),
        ];
    }

    private static function adminLike(string $q): string
    {
        return '%' . strtolower(str_replace(['%', '_'], '', $q)) . '%';
    }

    /** @return list<array<string,mixed>> */
    public static function adminListUsers(PDO $db, string $q = ''): array
    {
        $q = trim($q);
        $sql = 'SELECT u.*, h.name AS household_name, h.plan AS household_plan
            FROM users u JOIN households h ON h.id = u.household_id';
        $params = [];
        if ($q !== '') {
            $like = self::adminLike($q);
            $sql .= ' WHERE lower(u.name) LIKE ? OR lower(u.email) LIKE ? OR lower(h.name) LIKE ? OR IFNULL(u.phone,\'\') LIKE ?';
            $params = [$like, $like, $like, $like];
        }
        $sql .= ' ORDER BY u.id LIMIT 500';
        $st = $db->prepare($sql);
        $st->execute($params);
        [$users] = self::decorateCircleStatus($st->fetchAll(), []);
        return $users;
    }

    /** @return list<array<string,mixed>> */
    public static function adminListInvites(PDO $db, string $q = ''): array
    {
        $q = trim($q);
        $sql = "SELECT i.*, h.name AS household_name
            FROM invitations i JOIN households h ON h.id = i.household_id
            WHERE i.status='pending'";
        $params = [];
        if ($q !== '') {
            $like = self::adminLike($q);
            $sql .= ' AND (lower(i.email) LIKE ? OR lower(IFNULL(i.name,\'\')) LIKE ? OR lower(h.name) LIKE ? OR IFNULL(i.phone,\'\') LIKE ?)';
            $params = [$like, $like, $like, $like];
        }
        $sql .= ' ORDER BY i.id DESC LIMIT 500';
        $st = $db->prepare($sql);
        $st->execute($params);
        [, $pending] = self::decorateCircleStatus([], $st->fetchAll());
        return $pending;
    }

    public static function adminGetUser(PDO $db, int $userId): ?array
    {
        $st = $db->prepare(
            'SELECT u.*, h.name AS household_name, h.plan AS household_plan,
                    h.stripe_status AS household_stripe_status,
                    h.created_at AS household_created_at
             FROM users u JOIN households h ON h.id = u.household_id WHERE u.id=?'
        );
        $st->execute([$userId]);
        $row = $st->fetch();
        if (!$row) {
            return null;
        }
        [$users] = self::decorateCircleStatus([$row], []);
        return $users[0];
    }

    public static function adminGetHousehold(PDO $db, int $hid): ?array
    {
        $st = $db->prepare('SELECT * FROM households WHERE id=?');
        $st->execute([$hid]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public static function adminOwnerIds(PDO $db, int $hid): array
    {
        $st = $db->prepare("SELECT id FROM users WHERE household_id=? AND role='owner' ORDER BY id");
        $st->execute([$hid]);
        return array_map(static fn ($r) => (int) $r['id'], $st->fetchAll());
    }

    /** @param array<string,mixed> $user */
    public static function adminIsLastOwner(PDO $db, array $user): bool
    {
        if (($user['role'] ?? '') !== 'owner') {
            return false;
        }
        return count(self::adminOwnerIds($db, (int) $user['household_id'])) <= 1;
    }

    /** @return list<array<string,mixed>> */
    public static function adminListHouseholds(PDO $db, string $q = ''): array
    {
        $q = trim($q);
        $sql = 'SELECT h.*,
            (SELECT COUNT(*) FROM users u WHERE u.household_id=h.id) AS user_count,
            (SELECT COUNT(*) FROM invitations i WHERE i.household_id=h.id AND i.status=\'pending\') AS invite_count,
            (SELECT COUNT(*) FROM trusted_contacts t WHERE t.household_id=h.id) AS trusted_count,
            (SELECT COUNT(*) FROM checks c WHERE c.household_id=h.id) AS check_count
            FROM households h';
        $params = [];
        if ($q !== '') {
            $sql .= ' WHERE lower(h.name) LIKE ? OR CAST(h.id AS TEXT)=?';
            $params = [self::adminLike($q), $q];
        }
        $sql .= ' ORDER BY h.id LIMIT 500';
        $st = $db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    public static function adminHouseholdDetail(PDO $db, int $hid): ?array
    {
        foreach (self::adminListHouseholds($db) as $row) {
            if ((int) $row['id'] === $hid) {
                return $row;
            }
        }
        return self::adminGetHousehold($db, $hid);
    }

    /** @return list<array<string,mixed>> */
    public static function adminListChecks(PDO $db, ?int $hid = null, int $limit = 50): array
    {
        $sql = 'SELECT c.id, c.household_id, c.user_id, c.kind, c.risk, c.created_at,
                u.name AS user_name, u.email AS user_email, h.name AS household_name
            FROM checks c
            JOIN users u ON u.id = c.user_id
            JOIN households h ON h.id = c.household_id';
        $params = [];
        if ($hid !== null) {
            $sql .= ' WHERE c.household_id=?';
            $params[] = $hid;
        }
        $sql .= ' ORDER BY c.id DESC LIMIT ?';
        $params[] = $limit;
        $st = $db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    private static function deleteCheckRow(PDO $db, int $checkId): void
    {
        $db->prepare('DELETE FROM reviews WHERE check_id=?')->execute([$checkId]);
        $db->prepare('DELETE FROM alerts WHERE check_id=?')->execute([$checkId]);
        $db->prepare('DELETE FROM checks WHERE id=?')->execute([$checkId]);
    }

    public static function adminDeleteCheck(PDO $db, int $checkId): bool
    {
        $st = $db->prepare('SELECT id FROM checks WHERE id=?');
        $st->execute([$checkId]);
        if (!$st->fetch()) {
            return false;
        }
        self::deleteCheckRow($db, $checkId);
        return true;
    }

    public static function adminAddTrusted(
        PDO $db,
        int $hid,
        string $kind,
        string $name,
        string $phone = '',
        string $website = '',
        string $notes = ''
    ): int {
        if (!self::adminGetHousehold($db, $hid)) {
            throw new RuntimeException('That circle is not in Family Shield Pro.');
        }
        $kind = trim($kind) !== '' ? trim($kind) : 'other';
        if (!in_array($kind, ['bank', 'doctor', 'insurer', 'utility', 'family', 'other'], true)) {
            $kind = 'other';
        }
        $name = trim($name);
        if ($name === '') {
            throw new RuntimeException('Give this contact a name you will recognize.');
        }
        $db->prepare(
            'INSERT INTO trusted_contacts (household_id, kind, name, phone, website, notes, created_at) VALUES (?,?,?,?,?,?,?)'
        )->execute([
            $hid,
            $kind,
            $name,
            trim($phone) !== '' ? trim($phone) : null,
            trim($website) !== '' ? trim($website) : null,
            trim($notes) !== '' ? trim($notes) : null,
            self::now(),
        ]);
        return (int) $db->lastInsertId();
    }

    public static function adminDeleteTrusted(PDO $db, int $contactId): bool
    {
        $st = $db->prepare('DELETE FROM trusted_contacts WHERE id=?');
        $st->execute([$contactId]);
        return $st->rowCount() > 0;
    }

    public static function adminUpdateUser(
        PDO $db,
        int $userId,
        string $name,
        string $email,
        string $phone = '',
        bool $smsOptOut = false,
        string $password = '',
        ?string $role = null,
        ?int $householdId = null
    ): array {
        $user = self::adminGetUser($db, $userId);
        if (!$user) {
            throw new RuntimeException('That login is not in Family Shield Pro.');
        }
        $name = trim($name);
        $email = strtolower(trim($email));
        if ($name === '') {
            throw new RuntimeException('Name cannot be empty.');
        }
        if ($email === '' || !str_contains($email, '@')) {
            throw new RuntimeException('Need a valid email address.');
        }
        $taken = $db->prepare('SELECT id FROM users WHERE lower(email)=? AND id<>?');
        $taken->execute([$email, $userId]);
        if ($taken->fetch()) {
            throw new RuntimeException('That email already has a Family Shield Pro login.');
        }
        $nextRole = strtolower(trim((string) ($role ?? $user['role'] ?? 'member')));
        if ($nextRole !== 'owner' && $nextRole !== 'member') {
            throw new RuntimeException('Role must be owner or member.');
        }
        $nextHid = $householdId !== null ? $householdId : (int) $user['household_id'];
        if (!self::adminGetHousehold($db, $nextHid)) {
            throw new RuntimeException('That circle is not in Family Shield Pro.');
        }
        $leaving = $nextHid !== (int) $user['household_id'] || $nextRole !== (string) ($user['role'] ?? '');
        if ($leaving && self::adminIsLastOwner($db, $user) && ($nextRole !== 'owner' || $nextHid !== (int) $user['household_id'])) {
            throw new RuntimeException('That login is the last owner. Add another owner first, or delete the whole circle.');
        }
        $db->prepare('UPDATE users SET name=?, email=?, role=?, household_id=? WHERE id=?')
            ->execute([$name, $email, $nextRole, $nextHid, $userId]);
        self::setUserPhone($db, $userId, $phone, $smsOptOut);
        $password = trim($password);
        if ($password !== '') {
            if (strlen($password) < 8) {
                throw new RuntimeException('Use at least 8 characters for a new password.');
            }
            $db->prepare('UPDATE users SET password_hash=? WHERE id=?')
                ->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);
        }
        $updated = self::adminGetUser($db, $userId);
        if (!$updated) {
            throw new RuntimeException('That login is not in Family Shield Pro.');
        }
        return $updated;
    }

    public static function adminDisable2fa(PDO $db, int $userId): array
    {
        $user = self::adminGetUser($db, $userId);
        if (!$user) {
            throw new RuntimeException('That login is not in Family Shield Pro.');
        }
        $db->prepare('UPDATE users SET totp_secret=NULL, totp_enabled=0, recovery_codes=NULL WHERE id=?')->execute([$userId]);
        $updated = self::adminGetUser($db, $userId);
        if (!$updated) {
            throw new RuntimeException('That login is not in Family Shield Pro.');
        }
        return $updated;
    }

    public static function adminCreateUser(
        PDO $db,
        int $hid,
        string $name,
        string $email,
        string $password,
        string $role = 'member',
        string $phone = ''
    ): array {
        if (!self::adminGetHousehold($db, $hid)) {
            throw new RuntimeException('That circle is not in Family Shield Pro.');
        }
        $name = trim($name);
        $email = strtolower(trim($email));
        $role = strtolower(trim($role));
        $password = trim($password);
        if ($name === '') {
            throw new RuntimeException('Name cannot be empty.');
        }
        if ($email === '' || !str_contains($email, '@')) {
            throw new RuntimeException('Need a valid email address.');
        }
        if ($role !== 'owner' && $role !== 'member') {
            throw new RuntimeException('Role must be owner or member.');
        }
        if (strlen($password) < 8) {
            throw new RuntimeException('Use at least 8 characters for a new password.');
        }
        $taken = $db->prepare('SELECT id FROM users WHERE lower(email)=?');
        $taken->execute([$email]);
        if ($taken->fetch()) {
            throw new RuntimeException('That email already has a Family Shield Pro login.');
        }
        $db->prepare(
            'INSERT INTO users (household_id, name, email, password_hash, role, created_at, phone) VALUES (?,?,?,?,?,?,?)'
        )->execute([$hid, $name, $email, password_hash($password, PASSWORD_DEFAULT), $role, self::now(), $phone !== '' ? $phone : null]);
        $uid = (int) $db->lastInsertId();
        if ($phone !== '') {
            try {
                self::setUserPhone($db, $uid, $phone, false);
            } catch (RuntimeException) {
                $db->prepare('UPDATE users SET phone=NULL WHERE id=?')->execute([$uid]);
            }
        }
        $created = self::adminGetUser($db, $uid);
        if (!$created) {
            throw new RuntimeException('Could not create that login.');
        }
        return $created;
    }

    public static function adminDeleteUser(PDO $db, int $userId): void
    {
        $user = self::adminGetUser($db, $userId);
        if (!$user) {
            throw new RuntimeException('That login is not in Family Shield Pro.');
        }
        if (self::adminIsLastOwner($db, $user)) {
            throw new RuntimeException('That login is the last owner. Add another owner first, or delete the whole circle.');
        }
        $st = $db->prepare('SELECT id FROM checks WHERE user_id=?');
        $st->execute([$userId]);
        foreach ($st->fetchAll() as $row) {
            self::deleteCheckRow($db, (int) $row['id']);
        }
        $db->prepare('DELETE FROM reviews WHERE requester_id=?')->execute([$userId]);
        $db->prepare('DELETE FROM alerts WHERE user_id=?')->execute([$userId]);
        $db->prepare('DELETE FROM password_resets WHERE user_id=?')->execute([$userId]);
        $db->prepare('DELETE FROM users WHERE id=?')->execute([$userId]);
    }

    public static function adminCreateHousehold(
        PDO $db,
        string $name,
        string $plan,
        string $ownerName,
        string $ownerEmail,
        string $ownerPassword,
        string $phone = ''
    ): array {
        $ownerEmail = strtolower(trim($ownerEmail));
        $ownerName = trim($ownerName);
        $name = trim($name);
        $ownerPassword = trim($ownerPassword);
        if ($name === '') {
            $name = $ownerName . "'s circle";
        }
        if ($ownerName === '') {
            throw new RuntimeException('Name cannot be empty.');
        }
        if ($ownerEmail === '' || !str_contains($ownerEmail, '@')) {
            throw new RuntimeException('Need a valid email address.');
        }
        if (strlen($ownerPassword) < 8) {
            throw new RuntimeException('Use at least 8 characters for a new password.');
        }
        $taken = $db->prepare('SELECT id FROM users WHERE lower(email)=?');
        $taken->execute([$ownerEmail]);
        if ($taken->fetch()) {
            throw new RuntimeException('That email already has a Family Shield Pro login.');
        }
        $hid = self::createHousehold($db, $name, $ownerName, $ownerEmail, $ownerPassword, $phone);
        self::adminUpdateHousehold($db, $hid, $name, $plan);
        $detail = self::adminHouseholdDetail($db, $hid);
        if (!$detail) {
            throw new RuntimeException('Could not create that circle.');
        }
        return $detail;
    }

    public static function adminDeleteHousehold(PDO $db, int $hid): void
    {
        if (!self::adminGetHousehold($db, $hid)) {
            throw new RuntimeException('That circle is not in Family Shield Pro.');
        }
        $st = $db->prepare('SELECT id FROM checks WHERE household_id=?');
        $st->execute([$hid]);
        foreach ($st->fetchAll() as $row) {
            self::deleteCheckRow($db, (int) $row['id']);
        }
        $db->prepare('DELETE FROM alerts WHERE household_id=?')->execute([$hid]);
        $db->prepare('DELETE FROM reviews WHERE household_id=?')->execute([$hid]);
        $db->prepare('DELETE FROM trusted_contacts WHERE household_id=?')->execute([$hid]);
        $db->prepare('DELETE FROM invitations WHERE household_id=?')->execute([$hid]);
        $users = $db->prepare('SELECT id FROM users WHERE household_id=?');
        $users->execute([$hid]);
        foreach ($users->fetchAll() as $row) {
            $db->prepare('DELETE FROM password_resets WHERE user_id=?')->execute([(int) $row['id']]);
        }
        $db->prepare('DELETE FROM users WHERE household_id=?')->execute([$hid]);
        $db->prepare('DELETE FROM households WHERE id=?')->execute([$hid]);
    }

    public static function adminCreateInvite(PDO $db, int $hid, string $email, string $name = '', string $phone = ''): array
    {
        if (!self::adminGetHousehold($db, $hid)) {
            throw new RuntimeException('That circle is not in Family Shield Pro.');
        }
        $email = strtolower(trim($email));
        if ($email === '' || !str_contains($email, '@')) {
            throw new RuntimeException('Need an email address to invite.');
        }
        $phone = trim($phone);
        if ($phone !== '' && self::userByPhone($db, $phone)) {
            $phone = '';
        }
        $token = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
        $db->prepare(
            "INSERT INTO invitations (household_id, email, name, token, status, created_at, phone) VALUES (?,?,?,?,'pending',?,?)"
        )->execute([$hid, $email, trim($name), $token, self::now(), $phone !== '' ? $phone : null]);
        return ['email' => $email, 'token' => $token, 'id' => (int) $db->lastInsertId(), 'phone' => $phone];
    }

    public static function adminUpdateHousehold(PDO $db, int $hid, string $name, string $plan): array
    {
        $household = self::adminGetHousehold($db, $hid);
        if (!$household) {
            throw new RuntimeException('That circle is not in Family Shield Pro.');
        }
        $name = trim($name);
        $plan = strtolower(trim($plan));
        if ($name === '') {
            throw new RuntimeException('Circle name cannot be empty.');
        }
        if ($plan !== 'monthly' && $plan !== 'yearly') {
            throw new RuntimeException('Plan must be monthly or yearly.');
        }
        $db->prepare('UPDATE households SET name=?, plan=? WHERE id=?')->execute([$name, $plan, $hid]);
        $updated = self::adminGetHousehold($db, $hid);
        if (!$updated) {
            throw new RuntimeException('That circle is not in Family Shield Pro.');
        }
        return $updated;
    }
}
