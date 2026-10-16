<?php
declare(strict_types=1);

// Võrgu ja päris andmebaasita PDO fixture. Autentimis- ja endpointi kood jääb päriseks.
final class RKTestPDO extends PDO
{
    public array $contacts;
    public int $updates = 0;

    public function __construct()
    {
        $this->contacts = [
            1 => ['id' => 1, 'language' => 'ru', 'message' => 'Нужен ремонт кухни.', 'source' => 'https://renoveerikodu.ee/ru/kontakty'],
            2 => ['id' => 2, 'language' => 'et', 'message' => 'Soovin köögi remonti.'],
            3 => ['id' => 3, 'language' => 'ru', 'message' => 'Нужен ремонт.', 'message_et' => 'Vajan remonti.', 'translation_source_hash' => hash('sha256', 'Нужен ремонт.')],
        ];
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new RKTestStatement($this, $query);
    }

    public function setAttribute(int $attribute, mixed $value): bool
    {
        return true;
    }

    public function exec(string $statement): int|false
    {
        if ($statement !== 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci') {
            throw new RuntimeException('Unexpected fixture command.');
        }
        return 0;
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        if (str_starts_with($query, 'SELECT TABLE_COLLATION FROM information_schema.TABLES')) {
            $statement = new RKTestStatement($this, $query);
            $statement->rows = [['TABLE_COLLATION' => 'utf8mb4_unicode_ci']];
            return $statement;
        }
        if ($query !== 'SHOW COLUMNS FROM contacts' && $query !== 'SHOW FULL COLUMNS FROM contacts') {
            throw new RuntimeException('Unexpected fixture query.');
        }
        $statement = new RKTestStatement($this, $query);
        $statement->rows = array_map(static fn(string $column): array => ['Field' => $column, 'Collation' => 'utf8mb4_unicode_ci'],
            ['id', 'language', 'message', 'message_et', 'translation_source_hash', 'translated_at', 'translation_provider']);
        return $statement;
    }
}

final class RKTestStatement extends PDOStatement
{
    private RKTestPDO $db;
    private string $sql;
    public array $rows = [];
    private int $affected = 0;

    public function __construct(RKTestPDO $db, string $sql)
    {
        $this->db = $db;
        $this->sql = $sql;
    }

    public function execute(?array $params = null): bool
    {
        if (str_starts_with($this->sql, 'SELECT id, username, password, role FROM admin_users')) {
            $this->rows = [['id' => 1, 'username' => 'test-admin', 'password' => 'test-credential-hash', 'role' => $_SESSION['test_role'] ?? 'admin']];
        } elseif (str_starts_with($this->sql, 'SELECT * FROM contacts')) {
            $this->rows = isset($this->db->contacts[$params[0]]) ? [$this->db->contacts[$params[0]]] : [];
        } elseif (str_starts_with($this->sql, 'UPDATE contacts SET message_et')) {
            [$translated, $hash, $provider, $id, $original] = $params;
            if (isset($this->db->contacts[$id]) && $this->db->contacts[$id]['message'] === $original) {
                $this->db->contacts[$id]['message_et'] = $translated;
                $this->db->contacts[$id]['translation_source_hash'] = $hash;
                $this->db->contacts[$id]['translation_provider'] = $provider;
                $this->db->contacts[$id]['translated_at'] = '2026-10-12 10:00:00';
                $this->affected = 1;
                $this->db->updates++;
            }
        } else {
            throw new RuntimeException('Unexpected fixture statement.');
        }
        return true;
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return array_shift($this->rows) ?? false;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        return $this->rows;
    }

    public function fetchColumn(int $column = 0): mixed
    {
        $row = array_shift($this->rows);
        return $row === null ? false : array_values($row)[$column];
    }

    public function rowCount(): int
    {
        return $this->affected;
    }
}
