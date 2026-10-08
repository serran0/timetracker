<?php
declare(strict_types=1);

namespace TimeTracker\Repository;

use TimeTracker\Db;

/**
 * "Actions" are the time action templates (normal working time, overtime, emergency, ...).
 * Every action belongs to one client, so each client can have its own set and rate multipliers.
 */
final class Actions
{
    /** name, colour, rate multiplier, billable */
    public const STANDARD = [
        ['Normal working time',     '#10b981', 1.00, 1],
        ['Overtime',                '#f59e0b', 1.50, 1],
        ['Emergency / call-out',    '#ef4444', 2.00, 1],
        ['Travel',                  '#0ea5e9', 1.00, 1],
        ['Internal / non-billable', '#94a3b8', 1.00, 0],
    ];

    /** All actions of one client, with usage counts. */
    public static function forClient(int $uid, int $clientId): array
    {
        return Db::all(
            'SELECT a.*, (SELECT COUNT(*) FROM time_entries e WHERE e.action_id = a.id) AS entry_count
             FROM actions a WHERE a.user_id = ? AND a.client_id = ? ORDER BY a.is_archived, a.sort_order, a.name',
            [$uid, $clientId]
        );
    }

    /** Every action of the user (with its client id) – used by the report dialog. */
    public static function selectable(int $uid): array
    {
        return Db::all(
            'SELECT id, client_id, name, color, rate_multiplier, is_billable, is_archived
             FROM actions WHERE user_id = ? ORDER BY is_archived, sort_order, name',
            [$uid]
        );
    }

    /** Distinct action names across all clients, for filters ("show all Overtime"). */
    public static function names(int $uid): array
    {
        return Db::all('SELECT name, MIN(color) AS color FROM actions WHERE user_id = ? GROUP BY name ORDER BY name', [$uid]);
    }

    /**
     * Decides which name to store when an action is edited. The form shows the translated label of a standard
     * action; saving it unchanged must not freeze the translation into the database, so a name equal to the
     * displayed label keeps the stored name. Any other text is a deliberate rename.
     */
    public static function nameToStore(?array $existing, string $submitted): string
    {
        $submitted = trim($submitted);
        if ($existing && $submitted === \TimeTracker\I18n::actionLabel((string) $existing['name'])) {
            return (string) $existing['name'];
        }
        return $submitted;
    }

    public static function find(int $uid, int $id): ?array
    {
        return Db::one('SELECT * FROM actions WHERE id = ? AND user_id = ?', [$id, $uid]);
    }

    public static function validate(int $uid, int $clientId, array $in, ?int $id = null): array
    {
        $errors = [];
        $name = trim((string) ($in['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 120) {
            $errors[] = t('Name is required (max 120 characters).');
        } elseif (Db::value('SELECT 1 FROM actions WHERE client_id = ? AND name = ? AND id <> ?', [$clientId, $name, $id ?? 0])) {
            $errors[] = t('This client already has an action with that name.');
        }
        $mult = str_replace(',', '.', trim((string) ($in['rate_multiplier'] ?? '1')));
        if ($mult === '') {
            $mult = '1';
        }
        if (!is_numeric($mult) || (float) $mult < 0 || (float) $mult > 100) {
            $errors[] = t('Rate multiplier must be a number between 0 and 100.');
            $mult = 1;
        }
        $data = [
            'name'            => $name,
            'color'           => valid_color((string) ($in['color'] ?? ''), '#10b981'),
            'rate_multiplier' => round((float) $mult, 2),
            'is_billable'     => !empty($in['is_billable']) ? 1 : 0,
        ];
        return [$data, $errors];
    }

    public static function save(int $uid, int $clientId, array $d, ?int $id = null): int
    {
        if ($id) {
            Db::run('UPDATE actions SET name=?, color=?, rate_multiplier=?, is_billable=? WHERE id=? AND user_id=? AND client_id=?',
                [$d['name'], $d['color'], $d['rate_multiplier'], $d['is_billable'], $id, $uid, $clientId]);
            return $id;
        }
        $order = (int) Db::value('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM actions WHERE client_id = ?', [$clientId]);
        return Db::insert('INSERT INTO actions (user_id, client_id, name, color, rate_multiplier, is_billable, sort_order) VALUES (?,?,?,?,?,?,?)',
            [$uid, $clientId, $d['name'], $d['color'], $d['rate_multiplier'], $d['is_billable'], $order]);
    }

    /** Gives a client the standard set (normal, overtime, emergency, travel, non-billable). */
    public static function seedStandard(int $uid, int $clientId): void
    {
        foreach (self::STANDARD as $i => [$name, $color, $mult, $billable]) {
            Db::run('INSERT INTO actions (user_id, client_id, name, color, rate_multiplier, is_billable, sort_order) VALUES (?,?,?,?,?,?,?)',
                [$uid, $clientId, $name, $color, $mult, $billable, $i]);
        }
    }

    /** Copies the active actions of one client to another (skipping names that already exist). Returns how many were added. */
    public static function copyFrom(int $uid, int $fromClientId, int $toClientId): int
    {
        $n = Db::run(
            'INSERT INTO actions (user_id, client_id, name, color, rate_multiplier, is_billable, sort_order)
             SELECT a.user_id, ?, a.name, a.color, a.rate_multiplier, a.is_billable, a.sort_order
             FROM actions a
             WHERE a.user_id = ? AND a.client_id = ? AND a.is_archived = 0
               AND NOT EXISTS (SELECT 1 FROM actions x WHERE x.client_id = ? AND x.name = a.name)',
            [$toClientId, $uid, $fromClientId, $toClientId]
        )->rowCount();
        return $n;
    }

    public static function setArchived(int $uid, int $id, bool $archived): void
    {
        Db::run('UPDATE actions SET is_archived = ? WHERE id = ? AND user_id = ?', [$archived ? 1 : 0, $id, $uid]);
    }

    public static function delete(int $uid, int $id): bool
    {
        if (Db::value('SELECT COUNT(*) FROM time_entries WHERE action_id = ? AND user_id = ?', [$id, $uid])) {
            return false;
        }
        Db::run('DELETE FROM actions WHERE id = ? AND user_id = ?', [$id, $uid]);
        return true;
    }
}
