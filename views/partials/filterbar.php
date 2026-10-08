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
        <label class="fb-field"><?= te('From') ?>
            <input type="date" name="from" value="<?= e($range['from']->format('Y-m-d')) ?>" data-autosubmit>
        </label>
        <label class="fb-field"><?= te('To') ?>
            <input type="date" name="to" value="<?= e($range['to']->format('Y-m-d')) ?>" data-autosubmit>
        </label>
        <label class="fb-field"><?= te('Period') ?>
            <select data-preset-select aria-label="<?= te('Quick period') ?>">
                <option value=""><?= te('Custom…') ?></option>
                <?php foreach ($presets as $label => [$pf, $pt]): ?>
                    <option value="<?= e($pf . '|' . $pt) ?>" <?= ($pf === $range['from']->format('Y-m-d') && $pt === $range['to']->format('Y-m-d')) ? 'selected' : '' ?>><?= te($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    <?php elseif ($view === 'day'): ?>
        <label class="fb-field"><?= te('Go to date') ?>
            <input type="date" name="date" value="<?= e($range['date']->format('Y-m-d')) ?>" data-autosubmit>
        </label>
    <?php elseif ($view === 'week'): ?>
        <?php $pickLabel = te('Wk {n}', ['n' => $range['from']->format('W')]) . ' · ' . e(date_l10n($range['from'], 'j M') . ' – ' . date_l10n($range['to'], 'j M Y')); ?>
        <div class="fb-field datepick" data-datepick="week">
            <span><?= te('Go to week') ?></span>
            <button type="button" class="dp-btn" data-dp-toggle aria-haspopup="dialog" aria-expanded="false"><?= $pickLabel ?></button>
            <input type="hidden" name="date" value="<?= e($range['date']->format('Y-m-d')) ?>">
        </div>
    <?php else: ?>
        <div class="fb-field datepick" data-datepick="month">
            <span><?= te('Go to month') ?></span>
            <button type="button" class="dp-btn" data-dp-toggle aria-haspopup="dialog" aria-expanded="false"><?= e(date_l10n($range['date'], 'F Y')) ?></button>
            <input type="hidden" name="date" value="<?= e($range['date']->format('Y-m-d')) ?>">
        </div>
    <?php endif; ?>

    <details class="multi fb-field">
        <summary><?= te('Clients') ?><?php if ($filters['clients']): ?> <span class="count"><?= count($filters['clients']) ?></span><?php endif; ?></summary>
        <div class="multi-menu">
            <?php foreach ($clients as $c): ?>
                <label class="check-label"><input type="checkbox" name="client[]" value="<?= (int) $c['id'] ?>" <?= in_array((int) $c['id'], $filters['clients'], true) ? 'checked' : '' ?>>
                    <span class="dot" style="background:<?= e($c['color']) ?>"></span><?= e($c['name']) ?><?= $c['is_archived'] ? ' <small class="muted">' . te('(archived)') . '</small>' : '' ?></label>
            <?php endforeach; ?>
            <?php if (!$clients): ?><span class="muted"><?= te('No clients') ?></span><?php endif; ?>
        </div>
    </details>

    <details class="multi fb-field">
        <summary><?= te('Actions') ?><?php if ($filters['actions']): ?> <span class="count"><?= count($filters['actions']) ?></span><?php endif; ?></summary>
        <div class="multi-menu">
            <?php foreach ($actionNames as $a): ?>
                <label class="check-label"><input type="checkbox" name="action[]" value="<?= e($a['name']) ?>" <?= in_array($a['name'], $filters['actions'], true) ? 'checked' : '' ?>>
                    <span class="dot" style="background:<?= e($a['color']) ?>"></span><?= e(action_label($a['name'])) ?></label>
            <?php endforeach; ?>
            <?php if (!$actionNames): ?><span class="muted"><?= te('No actions') ?></span><?php endif; ?>
        </div>
    </details>

    <label class="fb-field">
        <select name="billable" aria-label="<?= te('Billable filter') ?>" data-autosubmit>
            <option value=""><?= te('All hours') ?></option>
            <option value="1" <?= $filters['billable'] === '1' ? 'selected' : '' ?>><?= te('Billable only') ?></option>
            <option value="0" <?= $filters['billable'] === '0' ? 'selected' : '' ?>><?= te('Non-billable only') ?></option>
        </select>
    </label>

    <label class="fb-field">
        <select name="color" aria-label="<?= te('Colour by') ?>" data-autosubmit>
            <option value="client" <?= $filters['color'] === 'client' ? 'selected' : '' ?>><?= te('Colour by client') ?></option>
            <option value="action" <?= $filters['color'] === 'action' ? 'selected' : '' ?>><?= te('Colour by action') ?></option>
        </select>
    </label>

    <div class="fb-actions">
        <button class="btn btn-sm btn-primary" type="submit"><?= te('Apply') ?></button>
        <?php if ($hasFilter): ?>
            <a class="btn btn-sm" href="<?= e(url('calendar.php', array_filter(['view' => $view, 'date' => $view === 'list' ? null : $range['date']->format('Y-m-d'), 'from' => $view === 'list' ? $range['from']->format('Y-m-d') : null, 'to' => $view === 'list' ? $range['to']->format('Y-m-d') : null, 'color' => $filters['color'] === 'action' ? 'action' : null]))) ?>"><?= te('Clear') ?></a>
        <?php endif; ?>
        <a class="btn btn-sm" href="<?= e($exportUrl) ?>"><?= te('Export…') ?></a>
    </div>
</form>
