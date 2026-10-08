<?php
declare(strict_types=1);

namespace TimeTracker;

use PDO;
use Throwable;
use TimeTracker\Repository\Users;

/**
 * Backs the setup page: environment checks, database permission probe and installation.
 */
final class Installer
{
    public const TABLES = ['free_days', 'time_entries', 'working_hours', 'actions', 'clients', 'login_attempts', 'users', 'app_meta'];
    private const REQUIRED_PRIVILEGES = ['CREATE', 'ALTER', 'INDEX', 'INSERT', 'SELECT', 'UPDATE', 'DELETE', 'DROP'];

    /**
     * Each check: ['label' => string, 'status' => 'ok'|'warn'|'fail', 'detail' => string]
     */
    private static function check(string $label, string $status, string $detail = ''): array
    {
        return ['label' => $label, 'status' => $status, 'detail' => $detail];
    }

    public static function hasFailure(array $checks): bool
    {
        foreach ($checks as $c) {
            if ($c['status'] === 'fail') {
                return true;
            }
        }
        return false;
    }

    /** PHP version, extensions and file permissions. */
    public static function environmentChecks(): array
    {
        $checks = [];
        $v = PHP_VERSION;
        // Supported: PHP 8.2 to 8.4 (8.4 is the target). Newer versions are not tested, so they only get a warning.
        $checks[] = PHP_VERSION_ID < 80200
            ? self::check(t('PHP version'), 'fail', t('{v} – PHP 8.2 or newer is required (8.4 recommended).', ['v' => $v]))
            : (PHP_VERSION_ID >= 80500
                ? self::check(t('PHP version'), 'warn', t('{v} – newer than PHP 8.4, the newest version this app supports.', ['v' => $v]))
                : (PHP_VERSION_ID >= 80400
                    ? self::check(t('PHP version'), 'ok', $v)
                    : self::check(t('PHP version'), 'warn', t('{v} – works, but PHP 8.4 is the targeted version.', ['v' => $v]))));

        foreach (['pdo_mysql' => 'PDO MySQL driver', 'mbstring' => 'mbstring', 'ctype' => 'ctype', 'json' => 'JSON', 'session' => 'Sessions'] as $ext => $label) {
            $checks[] = extension_loaded($ext)
                ? self::check(t('Extension: {name}', ['name' => $label]), 'ok')
                : self::check(t('Extension: {name}', ['name' => $label]), 'fail', t('The PHP extension "{ext}" is not loaded.', ['ext' => $ext]));
        }
        $checks[] = class_exists(\ZipArchive::class)
            ? self::check(t('Extension: {name}', ['name' => 'zip']), 'ok', t('Needed for Excel (.xlsx) export.'))
            : self::check(t('Extension: {name}', ['name' => 'zip']), 'warn', t('Not available – Excel export will be disabled (CSV and text still work).'));

        $configDir = TT_ROOT . '/config';
        $checks[] = (is_dir($configDir) && is_writable($configDir))
            ? self::check(t('Config directory writable'), 'ok', 'config/')
            : self::check(t('Config directory writable'), 'fail', t('The web server user must be able to write to the config/ directory so setup can save config.php.'));

        $checks[] = is_readable(TT_ROOT . '/database/schema.sql')
            ? self::check(t('Database schema file'), 'ok', 'database/schema.sql')
            : self::check(t('Database schema file'), 'fail', t('database/schema.sql is missing or unreadable.'));
        return $checks;
    }

    /**
     * Connects with the supplied credentials and probes real permissions.
     * @return array{checks: array, pdo: ?PDO, db_exists: bool}
     */
    public static function databaseChecks(array $db): array
    {
        $checks = [];
        $pdo = null;
        $exists = false;

        if (!preg_match('/^[A-Za-z0-9_$]{1,64}$/', (string) $db['name'])) {
            $checks[] = self::check(t('Database name'), 'fail', t('Use only letters, digits, underscore or $ (max 64 characters).'));
            return ['checks' => $checks, 'pdo' => null, 'db_exists' => false];
        }
        if ($db['host'] === '' || $db['user'] === '') {
            $checks[] = self::check(t('Database credentials'), 'fail', t('Host and user are required.'));
            return ['checks' => $checks, 'pdo' => null, 'db_exists' => false];
        }

        try {
            $server = Db::connect($db, false);
            $version = (string) $server->query('SELECT VERSION()')->fetchColumn();
            $checks[] = self::check(t('Connect to database server'), 'ok', t('{host}:{port} (server {version})', ['host' => $db['host'], 'port' => $db['port'], 'version' => $version]));
        } catch (Throwable $e) {
            $checks[] = self::check(t('Connect to database server'), 'fail', self::cleanError($e));
            return ['checks' => $checks, 'pdo' => null, 'db_exists' => false];
        }

        $stmt = $server->prepare('SELECT 1 FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?');
        $stmt->execute([$db['name']]);
        $exists = (bool) $stmt->fetchColumn();

        if ($exists) {
            try {
                $pdo = Db::connect($db, true);
                $checks[] = self::check(t('Database "{name}"', ['name' => $db['name']]), 'ok', t('Exists and is accessible.'));
            } catch (Throwable $e) {
                $checks[] = self::check(t('Database "{name}"', ['name' => $db['name']]), 'fail', self::cleanError($e));
                return ['checks' => $checks, 'pdo' => null, 'db_exists' => true];
            }

            $present = [];
            foreach (self::TABLES as $t) {
                $q = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?');
                $q->execute([$db['name'], $t]);
                if ($q->fetchColumn()) {
                    $present[] = $t;
                }
            }
            $checks[] = $present
                ? self::check(t('Database is free of Timetracker tables'), 'fail', t('Already contains: {tables}. Choose a different (or empty) database so nothing is overwritten.', ['tables' => implode(', ', $present)]))
                : self::check(t('Database is free of Timetracker tables'), 'ok');

            foreach (self::probePrivileges($pdo) as $priv => $err) {
                $checks[] = $err === null
                    ? self::check(t('Privilege: {name}', ['name' => $priv]), 'ok')
                    : self::check(t('Privilege: {name}', ['name' => $priv]), 'fail', $err);
            }
        } else {
            $can = self::canCreateDatabase($server, $db['name']);
            if ($can === true) {
                $checks[] = self::check(t('Database "{name}"', ['name' => $db['name']]), 'ok', t('Does not exist yet – it will be created (CREATE privilege found).'));
            } elseif ($can === false) {
                $checks[] = self::check(t('Database "{name}"', ['name' => $db['name']]), 'fail', t('Does not exist and this user has no CREATE privilege. Create the database first (utf8mb4) or use a privileged user.'));
            } else {
                $checks[] = self::check(t('Database "{name}"', ['name' => $db['name']]), 'warn', t('Does not exist yet. Could not read privileges; setup will try to create it.'));
            }
            $checks[] = self::check(t('Table privileges'), 'warn', t('Will be tested right after the database is created.'));
        }

        return ['checks' => $checks, 'pdo' => $pdo, 'db_exists' => $exists];
    }

    /**
     * Really exercises CREATE/INSERT/SELECT/UPDATE/ALTER/INDEX/DELETE/DROP on a throw-away table.
     * @return array<string, ?string> privilege => error message (null when OK)
     */
    public static function probePrivileges(PDO $pdo): array
    {
        $t = 'tt_probe_' . bin2hex(random_bytes(4));
        $steps = [
            'CREATE' => "CREATE TABLE `$t` (id INT NOT NULL PRIMARY KEY, v VARCHAR(10)) ENGINE=InnoDB",
            'INSERT' => "INSERT INTO `$t` (id, v) VALUES (1, 'a')",
            'SELECT' => "SELECT * FROM `$t`",
            'UPDATE' => "UPDATE `$t` SET v = 'b' WHERE id = 1",
            'ALTER'  => "ALTER TABLE `$t` ADD COLUMN extra INT NULL",
            'INDEX'  => "CREATE INDEX idx_probe ON `$t` (v)",
            'DELETE' => "DELETE FROM `$t` WHERE id = 1",
            'DROP'   => "DROP TABLE `$t`",
        ];
        $result = [];
        $created = false;
        foreach ($steps as $priv => $sql) {
            if ($priv !== 'CREATE' && !$created) {
                $result[$priv] = t('Skipped because CREATE failed.');
                continue;
            }
            try {
                $stmt = $pdo->query($sql);
                if ($stmt instanceof \PDOStatement) {
                    $stmt->fetchAll();
                }
                $result[$priv] = null;
                if ($priv === 'CREATE') {
                    $created = true;
                }
            } catch (Throwable $e) {
                $result[$priv] = self::cleanError($e);
                if ($priv === 'DROP') {
                    $result[$priv] .= ' – ' . t('the harmless test table `{table}` was left behind and can be removed manually.', ['table' => $t]);
                }
            }
        }
        // Make sure the probe table never lingers if DROP failed.
        if ($created && $result['DROP'] !== null) {
            try {
                $pdo->exec("DROP TABLE IF EXISTS `$t`");
            } catch (Throwable) {
            }
        }
        return $result;
    }

    /** true / false, or null when SHOW GRANTS cannot be interpreted. */
    private static function canCreateDatabase(PDO $server, string $dbName): ?bool
    {
        try {
            $grants = $server->query('SHOW GRANTS FOR CURRENT_USER()')->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable) {
            return null;
        }
        foreach ($grants as $g) {
            if (!preg_match('/^GRANT (.+?) ON (.+?) TO /i', (string) $g, $m)) {
                continue;
            }
            $privs = strtoupper($m[1]);
            if (!str_contains($privs, 'ALL PRIVILEGES') && !preg_match('/(^|,\s*)CREATE(\s*,|$)/', $privs)) {
                continue;
            }
            $scope = str_replace('`', '', $m[2]);
            if ($scope === '*.*') {
                return true;
            }
            [$schemaPattern] = explode('.', $scope, 2);
            if (preg_match(self::grantPatternToRegex($schemaPattern), $dbName)) {
                return true;
            }
        }
        return false;
    }

    private static function grantPatternToRegex(string $pattern): string
    {
        $re = '';
        for ($i = 0, $n = strlen($pattern); $i < $n; $i++) {
            $ch = $pattern[$i];
            if ($ch === '\\' && $i + 1 < $n) {
                $re .= preg_quote($pattern[++$i], '/');
            } elseif ($ch === '_') {
                $re .= '.';
            } elseif ($ch === '%') {
                $re .= '.*';
            } else {
                $re .= preg_quote($ch, '/');
            }
        }
        return '/^' . $re . '$/i';
    }

    /**
     * Full installation. Throws RuntimeException with a user-presentable message on failure.
     * @param array{host:string,port:int|string,name:string,user:string,pass:string} $db
     * @param array{username:string,password:string,display_name:string,currency:string} $admin
     */
    public static function install(array $db, array $admin, string $timezone): void
    {
        $createdDatabase = false;
        $touchedTables = false;
        $server = null;
        $pdo = null;
        try {
            $server = Db::connect($db, false);
            $stmt = $server->prepare('SELECT 1 FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?');
            $stmt->execute([$db['name']]);
            if (!$stmt->fetchColumn()) {
                $server->exec('CREATE DATABASE `' . $db['name'] . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
                $createdDatabase = true;
            }
            $pdo = Db::connect($db, true);

            $probe = self::probePrivileges($pdo);
            $failed = array_keys(array_filter($probe, static fn($e) => $e !== null));
            if ($failed) {
                throw new \RuntimeException(t('The database user lacks privileges: {list}.', ['list' => implode(', ', $failed)]));
            }

            $q = $pdo->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME IN (' . Db::placeholders(self::TABLES) . ')');
            $q->execute([$db['name'], ...self::TABLES]);
            if ($existing = $q->fetchAll(PDO::FETCH_COLUMN)) {
                throw new \RuntimeException(t('The database already contains Timetracker tables ({tables}). Nothing was changed; choose an empty database.', ['tables' => implode(', ', $existing)]));
            }

            $touchedTables = true;
            foreach (self::splitStatements((string) file_get_contents(TT_ROOT . '/database/schema.sql')) as $sql) {
                $pdo->exec($sql);
            }
            $pdo->prepare('INSERT INTO app_meta (meta_key, meta_value) VALUES (?, ?)')->execute(['schema_version', (string) TT_SCHEMA]);

            Users::create($admin['username'], $admin['password'], $admin['display_name'], true, $timezone, $admin['currency'], $admin['locale'] ?? 'en', $pdo);

            $php = Config::render($db, $timezone);
            $target = Config::path();
            $tmp = $target . '.tmp';
            if (file_put_contents($tmp, $php, LOCK_EX) === false) {
                throw new \RuntimeException(t('Could not write config/config.php. Check that the config/ directory is writable.'));
            }
            @chmod($tmp, 0640);
            if (!rename($tmp, $target)) {
                @unlink($tmp);
                throw new \RuntimeException(t('Could not save config/config.php.'));
            }
        } catch (Throwable $e) {
            // Roll back anything this run created so setup can simply be retried.
            try {
                if ($createdDatabase && $server) {
                    $server->exec('DROP DATABASE `' . $db['name'] . '`');
                } elseif ($pdo && $touchedTables) {
                    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
                    foreach (self::TABLES as $t) {
                        $pdo->exec("DROP TABLE IF EXISTS `$t`");
                    }
                    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
                }
            } catch (Throwable) {
            }
            throw $e instanceof \RuntimeException ? $e : new \RuntimeException(self::cleanError($e), 0, $e);
        }
    }

    /** @return string[] */
    private static function splitStatements(string $sql): array
    {
        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
        $parts = preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [];
        return array_values(array_filter(array_map('trim', $parts), static fn($s) => $s !== ''));
    }

    /** Error text without noisy driver prefixes. */
    private static function cleanError(Throwable $e): string
    {
        $m = $e->getMessage();
        $m = preg_replace('/^SQLSTATE\[[^\]]*\]:?\s*(\[[^\]]*\])?\s*/', '', $m) ?? $m;
        return trim($m);
    }
}
