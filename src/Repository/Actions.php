<?php
declare(strict_types=1);

namespace TimeTracker\Repository;

use TimeTracker\Db;

/**
 * "Actions" are the time action templates: normal working time, overtime, emergency, ...
 */
final class Actions
{
    public static function all(int $uid): array
    {
        return Db::all(
            'SELECT a.*, (SELECT COUNT(*) FROM time_entries e WHERE e.action_id = a.id) AS entry_count
             FROM actions a WHERE a.user_id = ? ORDER BY a.is_archived, a.sort_order, a.name',
            [$uid]
        );
    }

    public static function selectable(int $uid): array
    {
        return Db::all('SELECT id, name, color, rate_multiplier, is_billable, is_archived FROM actions WHERE user_id = ? ORDER BY is_archived, sort_order, name', [$uid]);
    }

    public static function find(int $uid, int $id): ?array
    {
        return Db::one('SELECT * FROM actions WHERE id = ? AND user_id = ?', [$id, $uid]);
    }

    public static function validate(int $uid, array $in, ?int $id = null): array
    {
        $errors = [];
        $name = trim((string) ($in['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 120) {
            $errors[] = 'Name is required (max 120 characters).';
        } elseif (Db::value('SELECT 1 FROM actions WHERE user_id = ? AND name = ? AND id <> ?', [$uid, $name, $id ?? 0])) {
            $errors[] = 'You already have an action with that name.';
        }
        $mult = str_replace(',', '.', trim((string) ($in['rate_multiplier'] ?? '1')));
        if ($mult === '') {
            $mult = '1';
        }
        if (!is_numeric($mult) || (float) $mult < 0 || (float) $mult > 100) {
            $errors[] = 'Rate multiplier must be a number between 0 and 100.';
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

    public static function save(int $uid, array $d, ?int $id = null): int
    {
        if ($id) {
            Db::run('UPDATE actions SET name=?, color=?, rate_multiplier=?, is_billable=? WHERE id=? AND user_id=?',
                [$d['name'], $d['color'], $d['rate_multiplier'], $d['is_billable'], $id, $uid]);
            return $id;
        }
        $order = (int) Db::value('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM actions WHERE user_id = ?', [$uid]);
        return Db::insert('INSERT INTO actions (user_id, name, color, rate_multiplier, is_billable, sort_order) VALUES (?,?,?,?,?,?)',
            [$uid, $d['name'], $d['color'], $d['rate_multiplier'], $d['is_billable'], $order]);
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
