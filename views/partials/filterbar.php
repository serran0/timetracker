<?php
/** @var string $view */
/** @var array $range */
/** @var array $filters */
/** @var array $clients */
/** @var array $actionNames */
/** @var array $presets */
$hasFilter = $filters['clients'] || $filters['actions'] || $filters['billable'] !== '';
$exportUrl = url('export.php', [
    'from'     => $range['from']->format('Y-m-d'),
    'to'       => $range['to']->format('Y-m-d'),
    'client'   => $filters['clients'] ?: null,
    'action'   => $filters['actions'] ?: null,
    'billable' => $filters['billable'] !== '' ? $filters['billable'] : null,
]);
?>
<form class="filterbar" method="get" action="calendar.php" data-filterbar>
    <input type="hidden" name="view" value="<?= e($view) ?>">

    <?php if ($view === 'list'): ?>
        <label class="fb-field">From
            <input type="date" name="from" value="<?= e($range['from']->format('Y-m-d')) ?>" data-autosubmit>
        </label>
        <label class="fb-field">To
            <input type="date" name="to" value="<?= e($range['to']->format('Y-m-d')) ?>" data-autosubmit>
        </label>
        <label class="fb-field">Period
            <select data-preset-select aria-label="Quick period">
                <option value="">Custom…</option>
                <?php foreach ($presets as $label => [$pf, $pt]): ?>
                    <option value="<?= e($pf . '|' . $pt) ?>" <?= ($pf === $range['from']->format('Y-m-d') && $pt === $range['to']->format('Y-m-d')) ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    <?php else: ?>
        <label class="fb-field">Go to date
            <input type="date" name="date" value="<?= e($range['date']->format('Y-m-d')) ?>" data-autosubmit>
        </label>
    <?php endif; ?>

    <details class="multi fb-field">
        <summary>Clients<?php if ($filters['clients']): ?> <span class="count"><?= count($filters['clients']) ?></span><?php endif; ?></summary>
        <div class="multi-menu">
            <?php foreach ($clients as $c): ?>
                <label class="check-label"><input type="checkbox" name="client[]" value="<?= (int) $c['id'] ?>" <?= in_array((int) $c['id'], $filters['clients'], true) ? 'checked' : '' ?>>
                    <span class="dot" style="background:<?= e($c['color']) ?>"></span><?= e($c['name']) ?><?= $c['is_archived'] ? ' <small class="muted">(archived)</small>' : '' ?></label>
            <?php endforeach; ?>
            <?php if (!$clients): ?><span class="muted">No clients</span><?php endif; ?>
        </div>
    </details>

    <details class="multi fb-field">
        <summary>Actions<?php if ($filters['actions']): ?> <span class="count"><?= count($filters['actions']) ?></span><?php endif; ?></summary>
        <div class="multi-menu">
            <?php foreach ($actionNames as $a): ?>
                <label class="check-label"><input type="checkbox" name="action[]" value="<?= e($a['name']) ?>" <?= in_array($a['name'], $filters['actions'], true) ? 'checked' : '' ?>>
                    <span class="dot" style="background:<?= e($a['color']) ?>"></span><?= e($a['name']) ?></label>
            <?php endforeach; ?>
            <?php if (!$actionNames): ?><span class="muted">No actions</span><?php endif; ?>
        </div>
    </details>

    <label class="fb-field">
        <select name="billable" aria-label="Billable filter" data-autosubmit>
            <option value="">All hours</option>
            <option value="1" <?= $filters['billable'] === '1' ? 'selected' : '' ?>>Billable only</option>
            <option value="0" <?= $filters['billable'] === '0' ? 'selected' : '' ?>>Non-billable only</option>
        </select>
    </label>

    <label class="fb-field">
        <select name="color" aria-label="Colour by" data-autosubmit>
            <option value="client" <?= $filters['color'] === 'client' ? 'selected' : '' ?>>Colour by client</option>
            <option value="action" <?= $filters['color'] === 'action' ? 'selected' : '' ?>>Colour by action</option>
        </select>
    </label>

    <div class="fb-actions">
        <button class="btn btn-sm btn-primary" type="submit">Apply</button>
        <?php if ($hasFilter): ?>
            <a class="btn btn-sm" href="<?= e(url('calendar.php', array_filter(['view' => $view, 'date' => $view === 'list' ? null : $range['date']->format('Y-m-d'), 'from' => $view === 'list' ? $range['from']->format('Y-m-d') : null, 'to' => $view === 'list' ? $range['to']->format('Y-m-d') : null, 'color' => $filters['color'] === 'action' ? 'action' : null]))) ?>">Clear</a>
        <?php endif; ?>
        <a class="btn btn-sm" href="<?= e($exportUrl) ?>">Export…</a>
    </div>
</form>
