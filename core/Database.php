<?php

class Database
{
    private static ?Database $instance = null;
    private static array $connectionOverride = [];
    private PDO $pdo;

    private function __construct()
    {
        $this->pdo = self::createPdo(true);
    }

    public static function getInstance(): Database
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    public static function setConnectionOverride(array $config): void
    {
        self::$connectionOverride = $config;
        self::reset();
    }

    public static function clearConnectionOverride(): void
    {
        self::$connectionOverride = [];
        self::reset();
    }

    public static function createPdo(bool $withDatabase, array $config = []): PDO
    {
        $settings = array_merge([
            'host' => DB_HOST,
            'port' => DB_PORT,
            'name' => DB_NAME,
            'user' => DB_USER,
            'pass' => DB_PASS,
        ], self::$connectionOverride, $config);

        $dsn = $withDatabase
            ? sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $settings['host'], $settings['port'], $settings['name'], DB_CHARSET)
            : sprintf('mysql:host=%s;port=%s;charset=%s', $settings['host'], $settings['port'], DB_CHARSET);

        return new PDO($dsn, $settings['user'], $settings['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    public static function isInstalled(): bool
    {
        try {
            $pdo = self::createPdo(true);
            $statement = $pdo->query("SHOW TABLES LIKE 'admin_users'");
            return (bool) $statement->fetchColumn();
        } catch (PDOException) {
            return false;
        }
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }

    public function fetch(string $sql, array $params = []): array|false
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return $statement->fetch();
    }

    public function fetchColumn(string $sql, array $params = [], int $column = 0): mixed
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return $statement->fetchColumn($column);
    }

    public function execute(string $sql, array $params = []): bool
    {
        $statement = $this->pdo->prepare($sql);
        return $statement->execute($params);
    }

    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_map(static fn(string $column): string => ':' . $column, $columns);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $this->execute($sql, array_combine($placeholders, array_values($data)));
        return (int) $this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, string $where, array $params = []): int
    {
        $set = [];
        $bindings = [];

        foreach ($data as $column => $value) {
            $key = ':set_' . $column;
            $set[] = $column . ' = ' . $key;
            $bindings[$key] = $value;
        }

        $sql = sprintf('UPDATE %s SET %s WHERE %s', $table, implode(', ', $set), $where);
        $statement = $this->pdo->prepare($sql);
        $statement->execute($bindings + $params);
        return $statement->rowCount();
    }

    public function delete(string $table, string $where, array $params = []): int
    {
        $sql = sprintf('DELETE FROM %s WHERE %s', $table, $where);
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return $statement->rowCount();
    }

    public function beginTransaction(): void
    {
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
        }
    }

    public function commit(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->commit();
        }
    }

    public function rollBack(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }
}
