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
        $sql = 'SELECT c.*, (SELECT COUNT(*) FROM time_entries e WHERE e.client_id = c.id) AS entry_count,
                (SELECT COUNT(*) FROM actions a WHERE a.client_id = c.id AND a.is_archived = 0) AS action_count
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
        $name = trim(to_str($in['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 160) {
            $errors[] = t('Name is required (max 160 characters).');
        } elseif (Db::value('SELECT 1 FROM clients WHERE user_id = ? AND name = ? AND id <> ?', [$uid, $name, $id ?? 0])) {
            $errors[] = t('You already have a client with that name.');
        }
        $rate = trim(to_str($in['hourly_rate'] ?? ''));
        $rate = str_replace(',', '.', $rate);
        if ($rate === '') {
            $rate = null;
        } elseif (!is_numeric($rate) || (float) $rate < 0 || (float) $rate > 99999999) {
            $errors[] = t('Hourly rate must be a positive number.');
            $rate = null;
        } else {
            $rate = round((float) $rate, 2);
        }
        $vat = str_replace(',', '.', trim(to_str($in['vat_percent'] ?? '')));
        if ($vat === '') {
            $vat = 0.0;
        } elseif (!is_numeric($vat) || (float) $vat < 0 || (float) $vat > 100) {
            $errors[] = t('VAT must be a percentage between 0 and 100.');
            $vat = 0.0;
        } else {
            $vat = round((float) $vat, 2);
        }
        if (mb_strlen(to_str($in['notes'] ?? '')) > 5000) {
            $errors[] = t('Notes are too long (max 5000 characters).');
        }
        $ref = trim(to_str($in['reference'] ?? ''));
        $data = [
            'name'        => $name,
            'reference'   => $ref !== '' ? mb_substr($ref, 0, 160) : null,
            'color'       => valid_color(to_str($in['color'] ?? '')),
            'hourly_rate' => $rate,
            'vat_percent' => $vat,
            'notes'       => trim(to_str($in['notes'] ?? '')) ?: null,
        ];
        return [$data, $errors];
    }

    public static function save(int $uid, array $d, ?int $id = null): int
    {
        if ($id) {
            Db::run('UPDATE clients SET name=?, reference=?, color=?, hourly_rate=?, vat_percent=?, notes=? WHERE id=? AND user_id=?',
                [$d['name'], $d['reference'], $d['color'], $d['hourly_rate'], $d['vat_percent'], $d['notes'], $id, $uid]);
            return $id;
        }
        return Db::insert('INSERT INTO clients (user_id, name, reference, color, hourly_rate, vat_percent, notes) VALUES (?,?,?,?,?,?,?)',
            [$uid, $d['name'], $d['reference'], $d['color'], $d['hourly_rate'], $d['vat_percent'], $d['notes']]);
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
