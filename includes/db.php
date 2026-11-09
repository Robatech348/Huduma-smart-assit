<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $c = config('db');
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'], (int)$c['port'], $c['name']);
    try {
        $pdo = new PDO($dsn, $c['user'], $c['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec("SET time_zone = '" . (new DateTime())->format('P') . "'");
    } catch (PDOException $e) {
        http_response_code(503);
        $msg = config('app.debug') ? $e->getMessage() : 'Service temporarily unavailable.';
        exit('Database connection failed: ' . htmlspecialchars($msg) .
            '<br>Is MySQL running, and have you run <a href="install/install.php">install/install.php</a>?');
    }
    return $pdo;
}

/** Run a prepared query and return the statement. */
function q(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function q_one(string $sql, array $params = []): ?array
{
    $row = q($sql, $params)->fetch();
    return $row === false ? null : $row;
}

function q_all(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function q_val(string $sql, array $params = [])
{
    $v = q($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}
