<?php
declare(strict_types=1);

namespace TimeTracker;

use PDO;

/**
 * Upgrades an existing database to the schema number the code expects (TT_SCHEMA).
 * Each step is idempotent, and a database lock stops two requests from migrating at once.
 *
 * Schema numbers: 1 = 0.1, 2 = 0.2 (unpaid breaks), 3 = 0.2.1 (actions belong to a client), 4 = 0.2.3 (user language), 5 = 0.2.5 (holidays and own work-free days), 6 = 0.2.14 (default calendar colouring).
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

    /** 0.2.14: the user's default for colouring the calendar (by client or by action). */
    private static function toSchema6(PDO $pdo): void
    {
        if (!self::columnExists($pdo, 'users', 'default_color')) {
            $pdo->exec("ALTER TABLE users ADD COLUMN default_color VARCHAR(6) NOT NULL DEFAULT 'client' COMMENT 'calendar colouring: client or action' AFTER show_holidays");
        }
    }

    /**
     * 0.2.5: holiday overlay setting and the user's own work-free days. Existing users get the overlay switched
     * off (they opt in); new users default to on.
     */
    private static function toSchema5(PDO $pdo): void
    {
        if (!self::columnExists($pdo, 'users', 'show_holidays')) {
            $pdo->exec("ALTER TABLE users ADD COLUMN show_holidays TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'overlay Swedish red days in the calendar' AFTER locale");
            $pdo->exec('UPDATE users SET show_holidays = 0');
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS free_days (
            id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id    INT UNSIGNED NOT NULL,
            start_date DATE NOT NULL,
            end_date   DATE NOT NULL,
            name       VARCHAR(120) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_free_days_user (user_id, start_date),
            CONSTRAINT fk_free_days_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    /** 0.2.3: per-user language. Existing users keep English until they choose otherwise. */
    private static function toSchema4(PDO $pdo): void
    {
        if (!self::columnExists($pdo, 'users', 'locale')) {
            $pdo->exec("ALTER TABLE users ADD COLUMN locale VARCHAR(5) NOT NULL DEFAULT 'en' AFTER currency");
        }
    }

    private static function indexExists(PDO $pdo, string $table, string $index): bool
    {
        $q = $pdo->prepare('SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?');
        $q->execute([$table, $index]);
        return (bool) $q->fetchColumn();
    }

    private static function constraintExists(PDO $pdo, string $table, string $name): bool
    {
        $q = $pdo->prepare('SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?');
        $q->execute([$table, $name]);
        return (bool) $q->fetchColumn();
    }

    /**
     * 0.2.1: actions belong to a client. Every existing (user-level) action is copied to each of that user's
     * clients, reports are re-pointed to the matching copy, and the user-level originals are removed.
     */
    private static function toSchema3(PDO $pdo): void
    {
        if (!self::columnExists($pdo, 'actions', 'client_id')) {
            $pdo->exec('ALTER TABLE actions ADD COLUMN client_id INT UNSIGNED NULL AFTER user_id');
        }
        // The user_id foreign key currently relies on the (user_id, name) unique index; give it its own index first.
        if (!self::indexExists($pdo, 'actions', 'idx_actions_user')) {
            $pdo->exec('ALTER TABLE actions ADD KEY idx_actions_user (user_id)');
        }
        if (self::indexExists($pdo, 'actions', 'uq_actions_user_name')) {
            $pdo->exec('ALTER TABLE actions DROP INDEX uq_actions_user_name');
        }

        $pdo->beginTransaction();
        try {
            // 1. a copy of each old action for each client of the same user
            $pdo->exec('INSERT INTO actions (user_id, client_id, name, color, rate_multiplier, is_billable, sort_order, is_archived, created_at)
                        SELECT a.user_id, c.id, a.name, a.color, a.rate_multiplier, a.is_billable, a.sort_order, a.is_archived, a.created_at
                        FROM actions a JOIN clients c ON c.user_id = a.user_id
                        WHERE a.client_id IS NULL
                          AND NOT EXISTS (SELECT 1 FROM actions x WHERE x.client_id = c.id AND x.name = a.name)');
            // 2. reports use the copy that belongs to their client
            $pdo->exec('UPDATE time_entries e
                        JOIN actions old ON old.id = e.action_id AND old.client_id IS NULL
                        JOIN actions new ON new.client_id = e.client_id AND new.name = old.name
                        SET e.action_id = new.id');
            // 3. originals are no longer needed (fails loudly if a report still points at one)
            $pdo->exec('DELETE FROM actions WHERE client_id IS NULL');
            $pdo->commit();
        } catch (\Throwable $t) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $t;
        }

        $pdo->exec('ALTER TABLE actions MODIFY client_id INT UNSIGNED NOT NULL');
        if (!self::indexExists($pdo, 'actions', 'uq_actions_client_name')) {
            $pdo->exec('ALTER TABLE actions ADD UNIQUE KEY uq_actions_client_name (client_id, name)');
        }
        if (!self::constraintExists($pdo, 'actions', 'fk_actions_client')) {
            $pdo->exec('ALTER TABLE actions ADD CONSTRAINT fk_actions_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE');
        }
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
