<?php

/**
 * Base Model Class
 *
 * Provides common database operations for all models including CRUD operations,
 * pagination, and custom queries.
 *
 * @package    CoverLetterGenerator
 * @subpackage Core
 * @author     J.J.Johnson <email4johnson@gmail.com>
 * @copyright  2026 VisionQuest Services LLC
 */
class Model
{
    /**
     * @var PDO Database connection instance
     */
    protected PDO $db;

    /**
     * @var string The database table name
     */
    protected string $table;

    /**
     * @var string The primary key column name
     */
    protected string $primaryKey = 'id';

    /**
     * Initialize the model with database connection
     */
    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Retrieve all records from the table
     *
     * @return array Array of all records
     */
    public function findAll(): array
    {
        $sql = "SELECT * FROM {$this->table} ORDER BY {$this->primaryKey} ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Find a record by its primary key
     *
     * @param int $id The primary key value
     * @return array|null The record or null if not found
     */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Find a single record by a specific column value
     *
     * @param string $column The column name to search
     * @param mixed  $value  The value to match
     * @return array|null The record or null if not found
     */
    public function findBy(string $column, $value): ?array
    {
        if (!preg_match('/^[a-zA-Z_]+$/', $column)) {
            throw new \InvalidArgumentException('Invalid column name.');
        }
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$column} = ?");
        $stmt->execute([$value]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Find all records matching a specific column value
     *
     * @param string $column The column name to search
     * @param mixed  $value  The value to match
     * @return array Array of matching records
     */
    public function findAllBy(string $column, $value): array
    {
        if (!preg_match('/^[a-zA-Z_]+$/', $column)) {
            throw new \InvalidArgumentException('Invalid column name.');
        }
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$column} = ?");
        $stmt->execute([$value]);
        return $stmt->fetchAll();
    }

    /**
     * Create a new record
     *
     * @param array $data Associative array of column => value pairs
     * @return int The ID of the newly created record
     */
    public function create(array $data): int
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $stmt = $this->db->prepare("INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})");
        $stmt->execute(array_values($data));

        return (int) $this->db->lastInsertId();
    }

    /**
     * Update an existing record
     *
     * @param int   $id   The primary key of the record to update
     * @param array $data Associative array of column => value pairs to update
     * @return bool True on success, false on failure
     */
    public function update(int $id, array $data): bool
    {
        $setClause = implode(' = ?, ', array_keys($data)) . ' = ?';

        $stmt = $this->db->prepare("UPDATE {$this->table} SET {$setClause} WHERE {$this->primaryKey} = ?");
        return $stmt->execute([...array_values($data), $id]);
    }

    /**
     * Delete a record
     *
     * @param int $id The primary key of the record to delete
     * @return bool True on success, false on failure
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Count total records in the table
     *
     * @return int The total count
     */
    public function count(): int
    {
        $stmt = $this->db->query("SELECT COUNT(*) FROM {$this->table}");
        return (int) $stmt->fetchColumn();
    }

    /**
     * Paginate records
     *
     * @param int $page    Current page number (1-based)
     * @param int $perPage Number of records per page
     * @return array Array of records for the current page
     */
    public function paginate(int $page = 1, int $perPage = 10): array
    {
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT * FROM {$this->table} ORDER BY {$this->primaryKey} DESC LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Execute a custom SELECT query
     *
     * @param string $sql    The SQL query to execute
     * @param array  $params Parameters to bind to the query
     * @return array Array of results
     */
    protected function query(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Execute a custom SELECT query and return single result
     *
     * @param string $sql    The SQL query to execute
     * @param array  $params Parameters to bind to the query
     * @return array|null Single result or null if not found
     */
    protected function queryOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Execute a non-SELECT query (INSERT, UPDATE, DELETE)
     *
     * @param string $sql    The SQL query to execute
     * @param array  $params Parameters to bind to the query
     * @return bool True on success, false on failure
     */
    protected function execute(string $sql, array $params = []): bool
    {
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
}
