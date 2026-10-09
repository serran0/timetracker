<?php
declare(strict_types=1);

namespace TimeTracker;

use PDO;

/**
 * Thin PDO wrapper. All queries use prepared statements.
 */
final class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            self::$pdo = self::connect(Config::get('db', []));
        }
        return self::$pdo;
    }

    /** Open a connection. $withDatabase=false connects to the server only (used by the installer). */
    public static function connect(array $db, bool $withDatabase = true): PDO
    {
        // Defence in depth: the values end up in a DSN string, so odd characters are refused here too.
        $shape = ['host' => $db['host'] ?? 'localhost', 'port' => (int) ($db['port'] ?? 3306), 'name' => $withDatabase ? ($db['name'] ?? '') : 'x', 'user' => 'x'];
        if (Installer::validateDbInput($shape)) {
            throw new \RuntimeException('Invalid database connection details.');
        }
        $dsn = sprintf(
            'mysql:host=%s;port=%d;%scharset=utf8mb4',
            $db['host'] ?? 'localhost',
            (int) ($db['port'] ?? 3306),
            $withDatabase ? 'dbname=' . ($db['name'] ?? '') . ';' : ''
        );
        return new PDO($dsn, to_str($db['user'] ?? ''), to_str($db['pass'] ?? ''), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 5,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4, sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'",
        ]);
    }

    public static function run(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function value(string $sql, array $params = []): mixed
    {
        $v = self::run($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    public static function insert(string $sql, array $params = []): int
    {
        self::run($sql, $params);
        return (int) self::pdo()->lastInsertId();
    }

    /** Returns "?,?,?" for an IN() list. */
    public static function placeholders(array $values): string
    {
        return implode(',', array_fill(0, count($values), '?'));
    }
}
