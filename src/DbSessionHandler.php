<?php
declare(strict_types=1);

namespace TimeTracker;

use PDOException;
use SessionHandlerInterface;
use SessionUpdateTimestampHandlerInterface;

/**
 * Stores PHP sessions in the database so any number of web servers can share them without sticky routing.
 * Switch it on with  'session' => ['handler' => 'db']  in config/config.php (see the README).
 *
 * There is no row locking: concurrent requests of one user do not queue behind each other. The session only holds the
 * user id, the CSRF token and a few small flags, so a last-write-wins race on those is harmless.
 * With lazy_write PHP calls updateTimestamp() instead of write() when nothing changed, so a plain page view costs a
 * single indexed read plus a tiny UPDATE (and the UPDATE is skipped for the first minute after the previous touch).
 */
final class DbSessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    /** Do not rewrite last_activity more often than this. */
    private const TOUCH_EVERY = 60;

    private int $lifetime;
    private int $lastSeen = 0;

    public function __construct(int $lifetime)
    {
        $this->lifetime = $lifetime;
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        try {
            $row = Db::one('SELECT data, last_activity FROM sessions WHERE id = ?', [$id]);
        } catch (PDOException $e) {
            error_log('Timetracker session read failed: ' . $e->getMessage());
            return '';
        }
        if (!$row || (int) $row['last_activity'] < time() - $this->lifetime) {
            return '';
        }
        $this->lastSeen = (int) $row['last_activity'];
        return (string) $row['data'];
    }

    public function write(string $id, string $data): bool
    {
        try {
            Db::run(
                'INSERT INTO sessions (id, data, last_activity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE data = VALUES(data), last_activity = VALUES(last_activity)',
                [$id, $data, time()]
            );
            return true;
        } catch (PDOException $e) {
            error_log('Timetracker session write failed: ' . $e->getMessage());
            return false;
        }
    }

    public function destroy(string $id): bool
    {
        try {
            Db::run('DELETE FROM sessions WHERE id = ?', [$id]);
        } catch (PDOException $e) {
            error_log('Timetracker session destroy failed: ' . $e->getMessage());
        }
        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        try {
            // Index on last_activity; bounded so one request never runs a huge delete.
            return Db::run('DELETE FROM sessions WHERE last_activity < ? LIMIT 5000', [time() - $this->lifetime])->rowCount();
        } catch (PDOException $e) {
            error_log('Timetracker session gc failed: ' . $e->getMessage());
            return false;
        }
    }

    /** Strict mode: only accept session ids this server actually issued. */
    public function validateId(string $id): bool
    {
        try {
            return (bool) Db::value('SELECT 1 FROM sessions WHERE id = ? AND last_activity >= ?', [$id, time() - $this->lifetime]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        if (time() - $this->lastSeen < self::TOUCH_EVERY) {
            return true;
        }
        try {
            Db::run('UPDATE sessions SET last_activity = ? WHERE id = ?', [time(), $id]);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
}
