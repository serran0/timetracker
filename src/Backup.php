<?php
declare(strict_types=1);

namespace TimeTracker;

use PDO;
use RuntimeException;

/**
 * Database backup and restore without mysqldump: a plain .sql file (optionally gzipped) that can also be loaded with the
 * mysql command line client.
 *
 * Format: one statement per line (strings are escaped, so values never contain real line breaks), framed by
 * "-- Timetracker backup" / "-- End of backup" so truncated files are recognised. Restore only accepts lines of the exact
 * shapes the backup writes (SET, DROP TABLE IF EXISTS, CREATE TABLE, INSERT INTO) for Timetracker's own tables and
 * refuses anything that could smuggle in a second statement.
 */
final class Backup
{
    public const HEADER = '-- Timetracker backup';
    public const FOOTER = '-- End of backup';
    /** Not worth backing up: they only hold transient state. */
    private const EPHEMERAL = ['sessions', 'login_attempts'];
    /** Order in the file (readability only; foreign key checks are off while restoring). */
    private const ORDER = ['users', 'clients', 'actions', 'working_hours', 'free_days', 'time_entries', 'app_meta', 'settings', 'audit_log'];
    private const INSERT_BYTES = 262144;

    /** @return string[] tables that exist and belong in a backup */
    public static function tables(bool $withAudit = true): array
    {
        $have = array_column(Db::all('SELECT TABLE_NAME AS n FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()'), 'n');
        $out = [];
        foreach (self::ORDER as $t) {
            if (in_array($t, $have, true) && ($withAudit || $t !== 'audit_log')) {
                $out[] = $t;
            }
        }
        return $out;
    }

    /** Names a restore may touch. */
    private static function allowedTables(): array
    {
        return array_values(array_diff(Installer::TABLES, self::EPHEMERAL));
    }

    private static function pdo(bool $stream): PDO
    {
        $pdo = Db::connect((array) Config::get('db', []));
        if ($stream) {
            $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false); // rows are written as they arrive
        } else {
            $pdo->setAttribute(PDO::MYSQL_ATTR_MULTI_STATEMENTS, false);   // one statement per exec, whatever the file says
        }
        return $pdo;
    }

    /**
     * Writes the whole backup through $write(string) in chunks, from a consistent snapshot.
     * @param callable(string): void $write
     * @return array{tables: int, rows: int}
     */
    public static function dump(callable $write, bool $withAudit = true): array
    {
        $pdo = self::pdo(true);
        $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
        $tables = array_values(array_filter(self::tables($withAudit)));
        $write(self::HEADER . "\n-- App version: " . TT_VERSION . "\n-- Schema: " . (int) SystemInfo::schemaVersion() . "\n-- Created: " . date('c') . "\n-- Tables: " . implode(',', $tables) . "\n");
        $write("SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET UNIQUE_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n");
        $rowsTotal = 0;
        foreach ($tables as $t) {
            $create = (string) $pdo->query('SHOW CREATE TABLE `' . $t . '`')->fetch(PDO::FETCH_NUM)[1];
            $write('DROP TABLE IF EXISTS `' . $t . "`;\n" . preg_replace('/\s*\R\s*/', ' ', $create) . ";\n");
            $stmt = $pdo->query('SELECT * FROM `' . $t . '`');
            $head = null;
            $buf = '';
            while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
                $head ??= 'INSERT INTO `' . $t . '` (`' . implode('`,`', array_keys($row)) . '`) VALUES ';
                $vals = [];
                foreach ($row as $v) {
                    $vals[] = $v === null ? 'NULL' : (is_int($v) || is_float($v) ? (string) $v : $pdo->quote((string) $v));
                }
                $buf .= ($buf === '' ? '' : ',') . '(' . implode(',', $vals) . ')';
                $rowsTotal++;
                if (strlen($buf) >= self::INSERT_BYTES) {
                    $write($head . $buf . ";\n");
                    $buf = '';
                }
            }
            if ($buf !== '') {
                $write($head . $buf . ";\n");
            }
        }
        $pdo->exec('COMMIT');
        $write("SET FOREIGN_KEY_CHECKS=1;\n" . self::FOOTER . "\n");
        return ['tables' => count($tables), 'rows' => $rowsTotal];
    }

    /** True when $line contains exactly one statement: no ";" outside quotes except the last character, no comments. */
    private static function singleStatement(string $line): bool
    {
        $n = strlen($line);
        $quote = null;
        for ($i = 0; $i < $n; $i++) {
            $c = $line[$i];
            if ($quote !== null) {
                if ($c === '\\' && $quote !== '`') {
                    $i++;
                } elseif ($c === $quote) {
                    if (($line[$i + 1] ?? '') === $quote) {
                        $i++; // doubled quote inside a literal
                    } else {
                        $quote = null;
                    }
                }
                continue;
            }
            if ($c === "'" || $c === '"' || $c === '`') {
                $quote = $c;
            } elseif ($c === ';') {
                if ($i !== $n - 1) {
                    return false;
                }
            } elseif (($c === '-' && ($line[$i + 1] ?? '') === '-') || $c === '#' || ($c === '/' && ($line[$i + 1] ?? '') === '*')) {
                return false;
            }
        }
        return $quote === null && $n > 0 && $line[$n - 1] === ';';
    }

    /** @return resource */
    private static function open(string $path)
    {
        $fp = @fopen($path, 'rb');
        if (!$fp) {
            throw new RuntimeException(t('The file could not be read.'));
        }
        $magic = fread($fp, 2);
        fclose($fp);
        if ($magic === "\x1f\x8b") {
            if (!function_exists('gzopen')) {
                throw new RuntimeException(t('This server cannot read compressed backups (the zlib extension is missing).'));
            }
            $fp = @gzopen($path, 'rb');
        } else {
            $fp = @fopen($path, 'rb');
        }
        if (!$fp) {
            throw new RuntimeException(t('The file could not be read.'));
        }
        return $fp;
    }

    /**
     * Checks the whole file without touching the database. Throws RuntimeException with a readable message.
     * @return array{schema: int, tables: string[], statements: int}
     */
    public static function inspect(string $path): array
    {
        $fp = self::open($path);
        $isGz = get_resource_type($fp) === 'stream' && stream_get_meta_data($fp)['wrapper_type'] === 'ZLIB';
        $read = static fn() => $isGz ? gzgets($fp) : fgets($fp);
        $allowed = self::allowedTables();
        $tbl = '`(' . implode('|', array_map('preg_quote', $allowed)) . ')`';
        $first = true;
        $schema = null;
        $created = [];
        $statements = 0;
        $sawFooter = false;
        $lineNo = 0;
        try {
            while (($raw = $read()) !== false) {
                $lineNo++;
                $line = rtrim($raw, "\r\n");
                if ($first) {
                    if ($line !== self::HEADER) {
                        throw new RuntimeException(t('This is not a Timetracker backup file.'));
                    }
                    $first = false;
                    continue;
                }
                if ($line === '') {
                    continue;
                }
                if ($sawFooter) {
                    throw new RuntimeException(t('Unexpected content after the end of the backup (line {n}).', ['n' => $lineNo]));
                }
                if (str_starts_with($line, '--')) {
                    if (preg_match('/^-- Schema: (\d+)$/', $line, $m)) {
                        $schema = (int) $m[1];
                    } elseif ($line === self::FOOTER) {
                        $sawFooter = true;
                    }
                    continue;
                }
                $ok = preg_match("/^SET (NAMES utf8mb4|FOREIGN_KEY_CHECKS=[01]|UNIQUE_CHECKS=[01]|SQL_MODE='NO_AUTO_VALUE_ON_ZERO');$/", $line)
                    || preg_match('/^DROP TABLE IF EXISTS ' . $tbl . ';$/', $line);
                if (!$ok && preg_match('/^CREATE TABLE ' . $tbl . ' \(/', $line, $m) && str_contains($line, ') ENGINE=InnoDB')) {
                    $ok = true;
                    $created[$m[1]] = true;
                }
                if (!$ok) {
                    $ok = (bool) preg_match('/^INSERT INTO ' . $tbl . ' \(`[A-Za-z0-9_]+`(?:,`[A-Za-z0-9_]+`)*\) VALUES \(/', $line);
                }
                if (!$ok || !self::singleStatement($line)) {
                    throw new RuntimeException(t('Line {n} is not something a Timetracker backup contains, so the file was not used.', ['n' => $lineNo]));
                }
                $statements++;
            }
        } finally {
            $isGz ? gzclose($fp) : fclose($fp);
        }
        if ($first) {
            throw new RuntimeException(t('The file is empty.'));
        }
        if ($schema === null) {
            throw new RuntimeException(t('The backup does not say which version it is from.'));
        }
        if ($schema > TT_SCHEMA) {
            throw new RuntimeException(t('This backup comes from a newer Timetracker (schema {have}); this version only understands schema {want} and older.', ['have' => $schema, 'want' => TT_SCHEMA]));
        }
        if (!$sawFooter) {
            throw new RuntimeException(t('The backup is incomplete (the end marker is missing). It was probably cut off while it was saved or uploaded.'));
        }
        foreach (['users', 'app_meta'] as $must) {
            if (!isset($created[$must])) {
                throw new RuntimeException(t('The backup does not contain the table "{name}".', ['name' => $must]));
            }
        }
        return ['schema' => $schema, 'tables' => array_keys($created), 'statements' => $statements];
    }

    /**
     * Replaces the database contents with the backup. Validate with inspect() first. Not atomic (MySQL commits every DROP and
     * CREATE), so a safety copy should exist before calling this. Older backups are upgraded to the current schema afterwards.
     * @return int statements executed
     */
    public static function restore(string $path): int
    {
        @set_time_limit(0);
        $fp = self::open($path);
        $isGz = stream_get_meta_data($fp)['wrapper_type'] === 'ZLIB';
        $pdo = self::pdo(false);
        $n = 0;
        try {
            while (($raw = ($isGz ? gzgets($fp) : fgets($fp))) !== false) {
                $line = rtrim($raw, "\r\n");
                if ($line === '' || str_starts_with($line, '--')) {
                    continue;
                }
                $pdo->exec($line);
                $n++;
            }
        } finally {
            $isGz ? gzclose($fp) : fclose($fp);
        }
        unset($_SESSION['_schema']);
        Migrator::run(); // a backup from an older version is brought up to date
        return $n;
    }
}
