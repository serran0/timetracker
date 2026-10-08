<?php
declare(strict_types=1);

namespace TimeTracker;

use PDO;
use Throwable;

/**
 * What the admin "System" page shows: component versions and the same kind of checks the setup page runs
 * (PHP, MySQL, files), plus a comparison of the live database structure with database/schema.sql.
 */
final class SystemInfo
{
    private static function check(string $label, string $status, string $detail = ''): array
    {
        return ['label' => $label, 'status' => $status, 'detail' => $detail];
    }

    /** @return array<int, array{0: string, 1: string, 2: string}> [component, version, note] */
    public static function components(): array
    {
        $rows = [
            [t('Timetracker'), TT_VERSION, ''],
            [t('Database schema'), self::schemaVersion() . ' / ' . TT_SCHEMA, t('installed / expected')],
            ['PHP', PHP_VERSION, PHP_SAPI],
            [t('Web server'), (string) ($_SERVER['SERVER_SOFTWARE'] ?? t('unknown')), ''],
            [t('Operating system'), php_uname('s') . ' ' . php_uname('r'), php_uname('m')],
            ['Zend Engine', zend_version(), ''],
        ];
        try {
            $pdo = Db::pdo();
            $rows[] = [t('Database server'), (string) $pdo->query('SELECT VERSION()')->fetchColumn(), ''];
            $rows[] = [t('PDO MySQL client library'), (string) $pdo->getAttribute(PDO::ATTR_CLIENT_VERSION), ''];
        } catch (Throwable $e) {
            $rows[] = [t('Database server'), t('not reachable'), $e->getMessage()];
        }
        $rows[] = ['OpenSSL', defined('OPENSSL_VERSION_TEXT') ? OPENSSL_VERSION_TEXT : t('not loaded'), ''];
        $rows[] = ['zlib', defined('ZLIB_VERSION') ? ZLIB_VERSION : t('not loaded'), t('backup compression')];
        $rows[] = ['libzip', class_exists(\ZipArchive::class) && defined('ZipArchive::LIBZIP_VERSION') ? \ZipArchive::LIBZIP_VERSION : t('not loaded'), t('Excel export')];
        $rows[] = ['PCRE', defined('PCRE_VERSION') ? PCRE_VERSION : t('not loaded'), ''];
        foreach (['pdo_mysql', 'mbstring', 'ctype', 'json', 'session', 'zip', 'intl', 'opcache', 'redis', 'memcached'] as $ext) {
            $rows[] = [t('PHP extension: {name}', ['name' => $ext]), extension_loaded($ext) ? (string) (phpversion($ext) ?: PHP_VERSION) : t('not loaded'), ''];
        }
        return $rows;
    }

    public static function schemaVersion(): string
    {
        try {
            return (string) Db::value("SELECT meta_value FROM app_meta WHERE meta_key = 'schema_version'");
        } catch (Throwable) {
            return '?';
        }
    }

    /** PHP version, extensions, files and the main php.ini limits. */
    public static function phpChecks(): array
    {
        $checks = [];
        foreach (Installer::environmentChecks() as $c) {
            // After installation a read-only config/ is fine (it is only needed by setup).
            if ($c['label'] === t('Config directory writable') && $c['status'] === 'fail') {
                $c['status'] = 'warn';
                $c['detail'] = t('Not writable, which is fine after setup. It is only needed to run setup again.');
            }
            $checks[] = $c;
        }
        $checks[] = self::check(t('Memory limit'), 'ok', (string) ini_get('memory_limit'));
        $checks[] = self::check(t('Maximum execution time'), 'ok', ini_get('max_execution_time') . ' s');
        $checks[] = self::check(t('Upload size limit'), 'ok', t('{upload} per file, {post} per request', ['upload' => (string) ini_get('upload_max_filesize'), 'post' => (string) ini_get('post_max_size')]));
        $checks[] = self::check(t('Session storage'), 'ok', (string) Config::get('session.handler', 'files'));
        $checks[] = ini_get('display_errors') && !in_array(strtolower((string) ini_get('display_errors')), ['0', 'off', 'stderr'], true)
            ? self::check(t('Error display'), 'warn', t('display_errors is on; switch it off on a production server.'))
            : self::check(t('Error display'), 'ok', t('display_errors is off.'));
        $checks[] = function_exists('opcache_get_status') && ini_get('opcache.enable')
            ? self::check('OPcache', 'ok', t('enabled'))
            : self::check('OPcache', 'warn', t('Not enabled; enabling it makes PHP noticeably faster.'));
        return $checks;
    }

    /** Connection, server settings and schema version. */
    public static function databaseChecks(): array
    {
        $checks = [];
        try {
            $t0 = microtime(true);
            $pdo = Db::pdo();
            $pdo->query('SELECT 1')->fetchColumn();
            $checks[] = self::check(t('Connection'), 'ok', t('Connected in {ms} ms.', ['ms' => (string) round((microtime(true) - $t0) * 1000, 1)]));
            $row = $pdo->query('SELECT DATABASE() AS db, @@character_set_database AS cs, @@collation_database AS col, @@sql_mode AS mode, @@max_allowed_packet AS pkt, @@time_zone AS tz, @@innodb_file_per_table AS fpt')->fetch();
            $checks[] = self::check(t('Database'), 'ok', (string) $row['db']);
            $checks[] = str_starts_with((string) $row['cs'], 'utf8mb4')
                ? self::check(t('Character set'), 'ok', $row['cs'] . ' / ' . $row['col'])
                : self::check(t('Character set'), 'warn', t('{cs}: utf8mb4 is recommended so every character can be stored.', ['cs' => (string) $row['cs']]));
            $checks[] = self::check(t('SQL mode'), 'ok', $row['mode'] !== '' ? (string) $row['mode'] : t('(empty)'));
            $pkt = (int) $row['pkt'];
            $checks[] = $pkt >= 4 * 1024 * 1024
                ? self::check('max_allowed_packet', 'ok', round($pkt / 1048576, 1) . ' MB')
                : self::check('max_allowed_packet', 'warn', t('{size} MB: a larger value helps when restoring big backups.', ['size' => (string) round($pkt / 1048576, 1)]));
            $checks[] = self::check(t('Server time zone'), 'ok', (string) $row['tz']);
            $engine = (string) $pdo->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'")->fetchColumn();
            $checks[] = strcasecmp($engine, 'InnoDB') === 0
                ? self::check(t('Storage engine'), 'ok', 'InnoDB')
                : self::check(t('Storage engine'), 'fail', t('The tables must use InnoDB (found: {engine}).', ['engine' => $engine ?: '?']));
            $v = self::schemaVersion();
            $checks[] = (int) $v === TT_SCHEMA
                ? self::check(t('Schema version'), 'ok', $v)
                : self::check(t('Schema version'), 'fail', t('The database is at schema {have}, this version expects {want}.', ['have' => $v, 'want' => (string) TT_SCHEMA]));
        } catch (Throwable $e) {
            $checks[] = self::check(t('Connection'), 'fail', $e->getMessage());
        }
        return $checks;
    }

    /**
     * Tables, columns, indexes and foreign keys the code expects (from database/schema.sql) against the live database.
     * @return array{checks: array, tables: array}
     */
    public static function structure(): array
    {
        $file = TT_ROOT . '/database/schema.sql';
        if (!is_readable($file)) {
            return ['checks' => [self::check(t('Database schema file'), 'fail', t('database/schema.sql is missing or unreadable.'))], 'tables' => []];
        }
        $expected = self::parseSchema((string) file_get_contents($file));
        $checks = [];
        try {
            $pdo = Db::pdo();
            $cols = [];
            foreach ($pdo->query('SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()')->fetchAll() as $r) {
                $cols[$r['TABLE_NAME']][strtolower($r['COLUMN_NAME'])] = strtolower($r['DATA_TYPE']);
            }
            $idx = [];
            foreach ($pdo->query('SELECT DISTINCT TABLE_NAME, INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE()')->fetchAll() as $r) {
                $idx[$r['TABLE_NAME']][strtolower($r['INDEX_NAME'])] = true;
            }
            $fks = [];
            foreach ($pdo->query('SELECT TABLE_NAME, CONSTRAINT_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE()')->fetchAll() as $r) {
                $fks[$r['TABLE_NAME']][strtolower($r['CONSTRAINT_NAME'])] = true;
            }
            $stats = [];
            foreach ($pdo->query('SELECT TABLE_NAME, TABLE_ROWS, DATA_LENGTH + INDEX_LENGTH AS bytes FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()')->fetchAll() as $r) {
                $stats[$r['TABLE_NAME']] = ['rows' => (int) $r['TABLE_ROWS'], 'bytes' => (int) $r['bytes']];
            }
        } catch (Throwable $e) {
            return ['checks' => [self::check(t('Database structure'), 'fail', $e->getMessage())], 'tables' => []];
        }

        $tables = [];
        foreach ($expected as $table => $def) {
            $problems = [];
            if (!isset($cols[$table])) {
                $checks[] = self::check(t('Table: {name}', ['name' => $table]), 'fail', t('The table is missing.'));
                continue;
            }
            foreach ($def['columns'] as $c => $type) {
                if (!isset($cols[$table][$c])) {
                    $problems[] = t('missing column {name}', ['name' => $c]);
                } elseif ($cols[$table][$c] !== $type) {
                    $problems[] = t('column {name} is {have}, expected {want}', ['name' => $c, 'have' => $cols[$table][$c], 'want' => $type]);
                }
            }
            foreach ($def['indexes'] as $i) {
                if (!isset($idx[$table][$i])) {
                    $problems[] = t('missing index {name}', ['name' => $i]);
                }
            }
            foreach ($def['foreign_keys'] as $f) {
                if (!isset($fks[$table][$f])) {
                    $problems[] = t('missing foreign key {name}', ['name' => $f]);
                }
            }
            $extra = array_diff(array_keys($cols[$table]), array_keys($def['columns']));
            $detail = count($def['columns']) . ' ' . t('columns') . ', ' . count($def['indexes']) . ' ' . t('indexes') . ($def['foreign_keys'] ? ', ' . count($def['foreign_keys']) . ' ' . t('foreign keys') : '');
            if ($problems) {
                $checks[] = self::check(t('Table: {name}', ['name' => $table]), 'fail', implode('; ', $problems));
            } elseif ($extra) {
                $checks[] = self::check(t('Table: {name}', ['name' => $table]), 'warn', $detail . ' · ' . t('extra columns not used by this version: {list}', ['list' => implode(', ', $extra)]));
            } else {
                $checks[] = self::check(t('Table: {name}', ['name' => $table]), 'ok', $detail);
            }
            $tables[$table] = $stats[$table] ?? ['rows' => 0, 'bytes' => 0];
        }
        $unknown = array_diff(array_keys($cols), array_keys($expected));
        if ($unknown) {
            $checks[] = self::check(t('Other tables'), 'warn', t('Not part of Timetracker: {list}', ['list' => implode(', ', $unknown)]));
        }
        return ['checks' => $checks, 'tables' => $tables];
    }

    /** @return array<string, array{columns: array<string,string>, indexes: string[], foreign_keys: string[]}> */
    public static function parseSchema(string $sql): array
    {
        $out = [];
        preg_match_all('/CREATE TABLE\s+`?(\w+)`?\s*\((.*?)\)\s*ENGINE=/s', $sql, $m, PREG_SET_ORDER);
        foreach ($m as [, $table, $body]) {
            $def = ['columns' => [], 'indexes' => [], 'foreign_keys' => []];
            foreach (preg_split('/\R/', $body) ?: [] as $line) {
                $line = rtrim(trim($line), ',');
                if ($line === '') {
                    continue;
                }
                if (preg_match('/^PRIMARY\s+KEY/i', $line)) {
                    $def['indexes'][] = 'primary';
                } elseif (preg_match('/^(?:UNIQUE\s+)?KEY\s+`?(\w+)`?/i', $line, $k)) {
                    $def['indexes'][] = strtolower($k[1]);
                } elseif (preg_match('/^CONSTRAINT\s+`?(\w+)`?\s+FOREIGN/i', $line, $k)) {
                    $def['foreign_keys'][] = strtolower($k[1]);
                } elseif (preg_match('/^`?(\w+)`?\s+([A-Za-z]+)/', $line, $k)) {
                    $def['columns'][strtolower($k[1])] = strtolower($k[2]);
                }
            }
            $out[$table] = $def;
        }
        return $out;
    }
}
