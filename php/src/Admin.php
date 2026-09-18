<?php
declare(strict_types=1);

/**
 * Copyright (c) 2026 Robert Foster
 *
 * Admin-only SQLite console for /admin.
 */
final class Admin
{
    private const PAGE_SIZE = 50;

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<array{name:string,rows:int}> */
    public function tables(): array
    {
        $st = $this->pdo->query(
            "SELECT name FROM sqlite_master
             WHERE type='table' AND name NOT LIKE 'sqlite_%'
             ORDER BY name COLLATE NOCASE"
        );
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $name) {
            $name = (string) $name;
            $n = (int) $this->pdo->query('SELECT COUNT(*) FROM ' . $this->quoteIdent($name))->fetchColumn();
            $out[] = ['name' => $name, 'rows' => $n];
        }
        return $out;
    }

    public function assertTable(string $table): string
    {
        $table = trim($table);
        foreach ($this->tables() as $t) {
            if (strcasecmp($t['name'], $table) === 0) {
                return $t['name'];
            }
        }
        throw new InvalidArgumentException('Unknown table.');
    }

    /** @return list<array{name:string,type:string,notnull:bool,pk:bool,dflt:?string}> */
    public function columns(string $table): array
    {
        $table = $this->assertTable($table);
        $st = $this->pdo->query('PRAGMA table_info(' . $this->quoteIdent($table) . ')');
        $cols = [];
        foreach ($st->fetchAll() as $row) {
            $cols[] = [
                'name' => (string) $row['name'],
                'type' => (string) ($row['type'] ?? ''),
                'notnull' => !empty($row['notnull']),
                'pk' => (int) ($row['pk'] ?? 0) > 0,
                'dflt' => $row['dflt_value'] !== null ? (string) $row['dflt_value'] : null,
            ];
        }
        return $cols;
    }

    /**
     * @return array{rows:list<array<string,mixed>>, total:int, page:int, pages:int, columns:list<string>}
     */
    public function browse(string $table, int $page = 1): array
    {
        $table = $this->assertTable($table);
        $page = max(1, $page);
        $total = (int) $this->pdo->query('SELECT COUNT(*) FROM ' . $this->quoteIdent($table))->fetchColumn();
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        if ($page > $pages) {
            $page = $pages;
        }
        $offset = ($page - 1) * self::PAGE_SIZE;
        $sql = 'SELECT rowid AS __rowid, * FROM ' . $this->quoteIdent($table)
            . ' ORDER BY rowid LIMIT ' . self::PAGE_SIZE . ' OFFSET ' . $offset;
        $rows = $this->pdo->query($sql)->fetchAll();
        $columns = [];
        if ($rows) {
            $columns = array_keys($rows[0]);
        } else {
            $columns = ['__rowid'];
            foreach ($this->columns($table) as $c) {
                $columns[] = $c['name'];
            }
        }
        return [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'columns' => $columns,
        ];
    }

    /** @return ?array<string,mixed> */
    public function row(string $table, int $rowid): ?array
    {
        $table = $this->assertTable($table);
        $st = $this->pdo->prepare(
            'SELECT rowid AS __rowid, * FROM ' . $this->quoteIdent($table) . ' WHERE rowid = :id'
        );
        $st->execute(['id' => $rowid]);
        $row = $st->fetch();
        return $row ?: null;
    }

    /** @param array<string,mixed> $fields */
    public function insert(string $table, array $fields): int
    {
        $table = $this->assertTable($table);
        $cols = $this->columns($table);
        $names = [];
        $vals = [];
        foreach ($cols as $c) {
            $n = $c['name'];
            if (!array_key_exists($n, $fields)) {
                continue;
            }
            $v = $fields[$n];
            if ($v === '' && !$c['notnull'] && !$c['pk']) {
                $names[] = $n;
                $vals[$n] = null;
                continue;
            }
            $names[] = $n;
            $vals[$n] = $v;
        }
        if ($names === []) {
            throw new InvalidArgumentException('Nothing to insert.');
        }
        $sql = 'INSERT INTO ' . $this->quoteIdent($table) . ' ('
            . implode(', ', array_map([$this, 'quoteIdent'], $names))
            . ') VALUES ('
            . implode(', ', array_map(static fn ($n) => ':' . $n, $names))
            . ')';
        $st = $this->pdo->prepare($sql);
        $st->execute($vals);
        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string,mixed> $fields */
    public function update(string $table, int $rowid, array $fields): void
    {
        $table = $this->assertTable($table);
        if ($this->row($table, $rowid) === null) {
            throw new InvalidArgumentException('Row not found.');
        }
        $cols = $this->columns($table);
        $sets = [];
        $vals = ['id' => $rowid];
        foreach ($cols as $c) {
            $n = $c['name'];
            if (!array_key_exists($n, $fields)) {
                continue;
            }
            $sets[] = $this->quoteIdent($n) . ' = :' . $n;
            $v = $fields[$n];
            $vals[$n] = ($v === '' && !$c['notnull']) ? null : $v;
        }
        if ($sets === []) {
            throw new InvalidArgumentException('Nothing to update.');
        }
        $sql = 'UPDATE ' . $this->quoteIdent($table) . ' SET ' . implode(', ', $sets) . ' WHERE rowid = :id';
        $this->pdo->prepare($sql)->execute($vals);
    }

    public function delete(string $table, int $rowid): void
    {
        $table = $this->assertTable($table);
        $st = $this->pdo->prepare('DELETE FROM ' . $this->quoteIdent($table) . ' WHERE rowid = :id');
        $st->execute(['id' => $rowid]);
        if ($st->rowCount() < 1) {
            throw new InvalidArgumentException('Row not found.');
        }
    }

    /**
     * @return array{ok:bool, columns:?list<string>, rows:?list<array>, affected:?int, error:?string, write:bool}
     */
    public function runSql(string $sql, bool $confirmWrite): array
    {
        $sql = trim($sql);
        if ($sql === '') {
            return ['ok' => false, 'columns' => null, 'rows' => null, 'affected' => null, 'error' => 'SQL is empty.', 'write' => false];
        }
        if (str_contains($sql, ';')) {
            $parts = array_values(array_filter(array_map('trim', explode(';', $sql)), static fn ($p) => $p !== ''));
            if (count($parts) > 1) {
                return ['ok' => false, 'columns' => null, 'rows' => null, 'affected' => null, 'error' => 'One statement at a time.', 'write' => false];
            }
            $sql = $parts[0] ?? '';
        }
        $write = (bool) preg_match(
            '/^\s*(INSERT|UPDATE|DELETE|REPLACE|DROP|ALTER|CREATE|TRUNCATE|VACUUM|REINDEX|ATTACH|DETACH)\b/i',
            $sql
        );
        if ($write && !$confirmWrite) {
            return [
                'ok' => false,
                'columns' => null,
                'rows' => null,
                'affected' => null,
                'error' => 'Confirm write SQL before running.',
                'write' => true,
            ];
        }
        try {
            if ($write) {
                $affected = $this->pdo->exec($sql);
                return [
                    'ok' => true,
                    'columns' => null,
                    'rows' => null,
                    'affected' => $affected === false ? 0 : (int) $affected,
                    'error' => null,
                    'write' => true,
                ];
            }
            $st = $this->pdo->query($sql);
            $rows = $st->fetchAll();
            $columns = $rows ? array_keys($rows[0]) : [];
            if (count($rows) > 500) {
                $rows = array_slice($rows, 0, 500);
            }
            return [
                'ok' => true,
                'columns' => $columns,
                'rows' => $rows,
                'affected' => count($rows),
                'error' => null,
                'write' => false,
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'columns' => null,
                'rows' => null,
                'affected' => null,
                'error' => $e->getMessage(),
                'write' => $write,
            ];
        }
    }

    public function quoteIdent(string $name): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
            throw new InvalidArgumentException('Bad identifier.');
        }
        return '"' . $name . '"';
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION['admin_csrf'])) {
            $_SESSION['admin_csrf'] = bin2hex(random_bytes(16));
        }
        return (string) $_SESSION['admin_csrf'];
    }

    public static function verifyCsrf(?string $token): void
    {
        $ok = is_string($token)
            && !empty($_SESSION['admin_csrf'])
            && hash_equals((string) $_SESSION['admin_csrf'], $token);
        if (!$ok) {
            http_response_code(400);
            echo 'Bad request.';
            exit;
        }
    }
}
