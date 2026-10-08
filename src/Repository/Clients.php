<?php
declare(strict_types=1);

namespace TimeTracker\Repository;

use TimeTracker\Db;

final class Clients
{
    public const PALETTE = ['#4f46e5', '#0ea5e9', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6', '#84cc16', '#f97316'];

    /** @return array<int,array> */
    public static function all(int $uid, bool $includeArchived = true): array
    {
        $sql = 'SELECT c.*, (SELECT COUNT(*) FROM time_entries e WHERE e.client_id = c.id) AS entry_count
                FROM clients c WHERE c.user_id = ?' . ($includeArchived ? '' : ' AND c.is_archived = 0') . ' ORDER BY c.is_archived, c.name';
        return Db::all($sql, [$uid]);
    }

    /** Active clients plus (optionally) one archived client that is still referenced. */
    public static function selectable(int $uid): array
    {
        return Db::all('SELECT id, name, color, is_archived FROM clients WHERE user_id = ? ORDER BY is_archived, name', [$uid]);
    }

    public static function find(int $uid, int $id): ?array
    {
        return Db::one('SELECT * FROM clients WHERE id = ? AND user_id = ?', [$id, $uid]);
    }

    public static function nextColor(int $uid): string
    {
        $n = (int) Db::value('SELECT COUNT(*) FROM clients WHERE user_id = ?', [$uid]);
        return self::PALETTE[$n % count(self::PALETTE)];
    }

    /** @return array{0: array, 1: string[]} cleaned data and errors */
    public static function validate(int $uid, array $in, ?int $id = null): array
    {
        $errors = [];
        $name = trim((string) ($in['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 160) {
            $errors[] = 'Name is required (max 160 characters).';
        } elseif (Db::value('SELECT 1 FROM clients WHERE user_id = ? AND name = ? AND id <> ?', [$uid, $name, $id ?? 0])) {
            $errors[] = 'You already have a client with that name.';
        }
        $rate = trim((string) ($in['hourly_rate'] ?? ''));
        $rate = str_replace(',', '.', $rate);
        if ($rate === '') {
            $rate = null;
        } elseif (!is_numeric($rate) || (float) $rate < 0 || (float) $rate > 99999999) {
            $errors[] = 'Hourly rate must be a positive number.';
            $rate = null;
        } else {
            $rate = round((float) $rate, 2);
        }
        $ref = trim((string) ($in['reference'] ?? ''));
        $data = [
            'name'        => $name,
            'reference'   => $ref !== '' ? mb_substr($ref, 0, 160) : null,
            'color'       => valid_color((string) ($in['color'] ?? '')),
            'hourly_rate' => $rate,
            'notes'       => trim((string) ($in['notes'] ?? '')) ?: null,
        ];
        return [$data, $errors];
    }

    public static function save(int $uid, array $d, ?int $id = null): int
    {
        if ($id) {
            Db::run('UPDATE clients SET name=?, reference=?, color=?, hourly_rate=?, notes=? WHERE id=? AND user_id=?',
                [$d['name'], $d['reference'], $d['color'], $d['hourly_rate'], $d['notes'], $id, $uid]);
            return $id;
        }
        return Db::insert('INSERT INTO clients (user_id, name, reference, color, hourly_rate, notes) VALUES (?,?,?,?,?,?)',
            [$uid, $d['name'], $d['reference'], $d['color'], $d['hourly_rate'], $d['notes']]);
    }

    public static function setArchived(int $uid, int $id, bool $archived): void
    {
        Db::run('UPDATE clients SET is_archived = ? WHERE id = ? AND user_id = ?', [$archived ? 1 : 0, $id, $uid]);
    }

    /** Deletes only clients without time entries. */
    public static function delete(int $uid, int $id): bool
    {
        $used = Db::value('SELECT COUNT(*) FROM time_entries WHERE client_id = ? AND user_id = ?', [$id, $uid]);
        if ($used) {
            return false;
        }
        Db::run('DELETE FROM clients WHERE id = ? AND user_id = ?', [$id, $uid]);
        return true;
    }
}
