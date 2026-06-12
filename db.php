<?php
// ================================================================
//  db.php — PDO connection singleton + query helpers
// ================================================================
require_once __DIR__ . '/config.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s',
                       DB_HOST, DB_NAME, DB_CHARSET);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

/** Execute a query with optional params, return PDOStatement */
function query(string $sql, array $params = []): PDOStatement {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/** Fetch a single row */
function fetch_one(string $sql, array $params = []): ?array {
    $r = query($sql, $params)->fetch();
    return $r ?: null;
}

/** Fetch all rows */
function fetch_all(string $sql, array $params = []): array {
    return query($sql, $params)->fetchAll();
}

/** Execute INSERT/UPDATE/DELETE, return lastInsertId or rowCount */
function execute(string $sql, array $params = []): string|int {
    $stmt = query($sql, $params);
    $id   = db()->lastInsertId();
    return $id ?: $stmt->rowCount();
}