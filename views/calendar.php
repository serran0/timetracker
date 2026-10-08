<?php
use TimeTracker\Calendar;
use TimeTracker\View;

/** @var string $view */
/** @var array $range */
/** @var array $filters */
/** @var array $baseParams */
/** @var array $clients */
/** @var array $actions */
/** @var array $user */
/** @var DateTimeImmutable $today */

/** Link to the calendar keeping the current view and filters. */
$link = static fn(array $over = []): string => url('calendar.php', $over + $baseParams);
$dateStr = $range['date']->format('Y-m-d');
$activeClients = array_filter($clients, static fn($c) => !$c['is_archived']);
$activeClientIds = array_column($activeClients, 'id');
$activeActions = array_filter($actions, static fn($a) => !$a['is_archived'] && in_array($a['client_id'], $activeClientIds, true));
$vars = get_defined_vars();
?>
<div class="cal-head">
    <div class="cal-nav">
        <a class="btn btn-icon" href="<?= e($link($range['prev'])) ?>" aria-label="Previous">‹</a>
        <a class="btn" href="<?= e($view === 'list' ? $link(['date' => null]) : $link(['date' => $today->format('Y-m-d')])) ?>">Today</a>
        <a class="btn btn-icon" href="<?= e($link($range['next'])) ?>" aria-label="Next">›</a>
    </div>
    <div class="cal-title">
        <h1><?= e($range['title']) ?></h1>
        <?php if ($range['subtitle'] !== ''): ?><span class="muted"><?= e($range['subtitle']) ?></span><?php endif; ?>
    </div>
    <div class="cal-tools">
        <div class="segmented" role="tablist" aria-label="Calendar view">
            <?php foreach (Calendar::VIEWS as $k => $label): ?>
                <?php
                $over = ['view' => $k, 'date' => $dateStr];
                if ($k === 'list') {
                    $over['date'] = null;
                    $over['from'] = $range['date']->modify('first day of this month')->format('Y-m-d');
                    $over['to'] = $range['date']->modify('last day of this month')->format('Y-m-d');
                }
                ?>
                <a href="<?= e(url('calendar.php', $over + Calendar::filterParams($filters))) ?>" class="<?= $view === $k ? 'active' : '' ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </div>
        <button class="btn btn-primary" type="button" data-new-entry data-date="<?= e($today->format('Y-m-d')) ?>">+ New report</button>
    </div>
</div>

<?php if (!$activeClients || !$activeActions): ?>
    <div class="alert alert-info">
        <?php if (!$activeClients): ?>To start reporting time, <a href="clients.php">add your first client</a>.<?php else: ?>Your clients have no actions yet – add some on the <a href="actions.php">Actions</a> page.<?php endif; ?>
    </div>
<?php endif; ?>

<?= View::capture('partials/filterbar', $vars) ?>

<div class="cal-body" data-view="<?= e($view) ?>">
    <?= View::capture('calendar/' . $view, $vars) ?>
</div>

<?= View::capture('partials/summary', $vars + ['label' => $summaryLabel, 'compare' => null]) ?>

<?= View::capture('partials/entry_modal', $vars) ?>
