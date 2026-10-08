<?php
use TimeTracker\Audit;

/** @var array $filters */
/** @var array{rows: array, total: int, page: int, pages: int, per_page: int} $result */
/** @var string $tz */
$base = $filters + ['per_page' => $result['per_page']];
$pageUrl = static fn(int $p): string => url('admin_audit.php', $base + ['page' => $p]);
$from = $result['total'] ? ($result['page'] - 1) * $result['per_page'] + 1 : 0;
$to = min($result['total'], $result['page'] * $result['per_page']);
$filtered = $filters['from'] !== '' || $filters['to'] !== '' || $filters['action'] !== '' || $filters['user'] !== '';
?>
<div class="page-head"><div><h1><?= te('Audit log') ?></h1><p class="muted"><?= te('Everything users and administrators do. Time-reporting actions are logged without names, labels or report details. Times are shown in {tz}.', ['tz' => $tz]) ?></p></div></div>

<form method="get" action="admin_audit.php" class="filterbar audit-filter">
    <label class="fb-field"><?= te('From') ?>
        <input type="date" name="from" value="<?= e($filters['from']) ?>">
    </label>
    <label class="fb-field"><?= te('To') ?>
        <input type="date" name="to" value="<?= e($filters['to']) ?>">
    </label>
    <label class="fb-field"><?= te('Action type') ?>
        <select name="action">
            <option value=""><?= te('All actions') ?></option>
            <?php foreach (Audit::CATEGORIES as $cat => $catLabel): ?>
                <optgroup label="<?= te($catLabel) ?>">
                    <option value="cat:<?= e($cat) ?>" <?= $filters['action'] === 'cat:' . $cat ? 'selected' : '' ?>><?= te('Everything in “{category}”', ['category' => t($catLabel)]) ?></option>
                    <?php foreach (Audit::EVENTS as $code => [$c, $label]): if ($c !== $cat) continue; ?>
                        <option value="<?= e($code) ?>" <?= $filters['action'] === $code ? 'selected' : '' ?>><?= te($label) ?></option>
                    <?php endforeach; ?>
                </optgroup>
            <?php endforeach; ?>
        </select>
    </label>
    <label class="fb-field"><?= te('User') ?>
        <input type="text" name="user" value="<?= e($filters['user']) ?>" placeholder="<?= te('Username starts with…') ?>" maxlength="64">
    </label>
    <label class="fb-field"><?= te('Rows per page') ?>
        <select name="per_page" data-autosubmit>
            <?php foreach (Audit::PER_PAGE_CHOICES as $n): ?><option value="<?= $n ?>" <?= $result['per_page'] === $n ? 'selected' : '' ?>><?= $n ?></option><?php endforeach; ?>
        </select>
    </label>
    <div class="fb-actions">
        <button class="btn btn-sm btn-primary" type="submit"><?= te('Apply') ?></button>
        <?php if ($filtered): ?><a class="btn btn-sm" href="<?= e(url('admin_audit.php', ['per_page' => $result['per_page']])) ?>"><?= te('Clear') ?></a><?php endif; ?>
    </div>
</form>

<section class="card">
    <?php if (!$result['rows']): ?>
        <div class="empty"><?= te('No log entries match.') ?></div>
    <?php else: ?>
    <div class="table-wrap">
    <table class="table audit-table">
        <thead><tr><th><?= te('Date and time') ?></th><th><?= te('Action type') ?></th><th><?= te('User') ?></th><th><?= te('Description') ?></th></tr></thead>
        <tbody>
        <?php foreach ($result['rows'] as $r): ?>
            <tr>
                <td class="nowrap"><?= e($r['created_at']) ?></td>
                <td class="nowrap"><span class="badge audit-<?= e(Audit::EVENTS[$r['action']][0] ?? 'other') ?>"><?= e(Audit::typeLabel($r['action'])) ?></span></td>
                <td class="nowrap"><?= $r['username'] !== '' ? '<strong>' . e($r['username']) . '</strong>' : '<span class="muted">–</span>' ?></td>
                <td><?= e(Audit::describe($r)) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <nav class="pager" aria-label="<?= te('Pages') ?>">
        <span class="muted"><?= te('Showing {from}–{to} of {total} · page {page} of {pages}', ['from' => $from, 'to' => $to, 'total' => $result['total'], 'page' => $result['page'], 'pages' => $result['pages']]) ?></span>
        <span class="pager-links">
            <?php if ($result['page'] > 1): ?>
                <a class="btn btn-sm" href="<?= e($pageUrl(1)) ?>">« <?= te('First') ?></a>
                <a class="btn btn-sm" href="<?= e($pageUrl($result['page'] - 1)) ?>">‹ <?= te('Previous') ?></a>
            <?php endif; ?>
            <?php if ($result['page'] < $result['pages']): ?>
                <a class="btn btn-sm" href="<?= e($pageUrl($result['page'] + 1)) ?>"><?= te('Next') ?> ›</a>
                <a class="btn btn-sm" href="<?= e($pageUrl($result['pages'])) ?>"><?= te('Last') ?> »</a>
            <?php endif; ?>
        </span>
    </nav>
    <?php endif; ?>
</section>
