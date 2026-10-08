<?php
declare(strict_types=1);

namespace TimeTracker;

use PDO;

/**
 * Upgrades an existing database to the schema number the code expects (TT_SCHEMA).
 * Each step is idempotent, and a database lock stops two requests from migrating at once.
 *
 * Schema numbers: 1 = Timetracker 0.1, 2 = Timetracker 0.2 (unpaid breaks).
 */
final class Migrator
{
    public static function run(): void
    {
        if (($_SESSION['_schema'] ?? 0) === TT_SCHEMA) {
            return;
        }
        $pdo = Db::pdo();
        if (self::current($pdo) < TT_SCHEMA) {
            $pdo->query("SELECT GET_LOCK('timetracker_migrate', 15)")->fetchAll();
            try {
                $current = self::current($pdo); // another request may have finished meanwhile
                for ($n = $current + 1; $n <= TT_SCHEMA; $n++) {
                    $step = 'toSchema' . $n;
                    self::$step($pdo);
                    $pdo->prepare('REPLACE INTO app_meta (meta_key, meta_value) VALUES (?, ?)')->execute(['schema_version', (string) $n]);
                }
            } finally {
                $pdo->query("SELECT RELEASE_LOCK('timetracker_migrate')")->fetchAll();
            }
        }
        $_SESSION['_schema'] = TT_SCHEMA;
    }

    /** Installs from 0.1 stored the app version ("0.1.0") instead of a number. */
    private static function current(PDO $pdo): int
    {
        $v = $pdo->query("SELECT meta_value FROM app_meta WHERE meta_key = 'schema_version'")->fetchColumn();
        if ($v === false || !ctype_digit((string) $v)) {
            return 1;
        }
        return (int) $v;
    }

    private static function columnExists(PDO $pdo, string $table, string $column): bool
    {
        $q = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $q->execute([$table, $column]);
        return (bool) $q->fetchColumn();
    }

    /** 0.2: unpaid break on reports + a default lunch window per user. */
    private static function toSchema2(PDO $pdo): void
    {
        if (!self::columnExists($pdo, 'time_entries', 'break_minutes')) {
            $pdo->exec('ALTER TABLE time_entries ADD COLUMN break_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER end_time');
        }
        if (!self::columnExists($pdo, 'users', 'lunch_start')) {
            $pdo->exec('ALTER TABLE users ADD COLUMN lunch_start TIME NULL, ADD COLUMN lunch_end TIME NULL');
        }
    }
}
