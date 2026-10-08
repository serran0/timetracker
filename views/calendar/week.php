<?php
use TimeTracker\Calendar;

/** @var string $view */
/** @var array $range */
/** @var array $byDate */
/** @var array $hours */
/** @var array $filters */
/** @var DateTimeImmutable $today */
/** @var callable $link */

$colorBy = $filters['color'];
$todayStr = $today->format('Y-m-d');
$nowMin = (int) date('G') * 60 + (int) date('i');
$laneH = 30;
$lanePad = 5;
$pct = static fn(int $min): string => rtrim(rtrim(number_format($min / 1440 * 100, 4, '.', ''), '0'), '.');

$days = [];
for ($d = $range['from']; $d <= $range['to']; $d = $d->modify('+1 day')) {
    $days[] = $d;
}
$weekMinTotal = 0;
$firstStart = 480;
foreach ($days as $d) {
    foreach ($hours[(int) $d->format('N')] as [$ws]) {
        $firstStart = min($firstStart, $ws);
    }
}
$scrollTo = max(0, $firstStart - 60) / 1440;
$whAttr = static fn(array $wh): string => implode(',', array_map(static fn($iv) => $iv[0] . '-' . $iv[1], $wh));
?>
<div class="tl-scroll" data-tl-scroll data-scroll-to="<?= e(number_format($scrollTo, 4, '.', '')) ?>">
<div class="tl <?= $view === 'day' ? 'tl-day' : '' ?>" data-tl>
    <div class="tl-row tl-headrow">
        <div class="tl-label">Wk <?= e($range['from']->format('W')) ?></div>
        <div class="tl-hours">
            <?php for ($h = 0; $h < 24; $h++): ?><span><?= sprintf('%02d', $h) ?></span><?php endfor; ?>
        </div>
        <div class="tl-sum">Total</div>
    </div>
    <?php foreach ($days as $d): ?>
        <?php
        $ds = $d->format('Y-m-d');
        $list = $byDate[$ds] ?? [];
        [$list, $laneCount] = Calendar::assignLanes($list);
        $height = $laneCount * $laneH + $lanePad * 2;
        $dayMin = array_sum(array_column($list, 'minutes'));
        $weekMinTotal += $dayMin;
        $wh = $hours[(int) $d->format('N')];
        $classes = ['tl-row'];
        if ($ds === $todayStr) { $classes[] = 'today'; }
        if (!$wh) { $classes[] = 'off'; }
        ?>
        <div class="<?= e(implode(' ', $classes)) ?>">
            <a class="tl-label" href="<?= e($link(['view' => 'day', 'date' => $ds])) ?>" title="Open day">
                <span class="dow"><?= e($d->format('D')) ?></span>
                <span class="dom"><?= e($d->format('j M')) ?></span>
                <?php if ($view === 'day'): ?><small class="muted">Wk <?= e($d->format('W')) ?></small><?php endif; ?>
            </a>
            <div class="tl-track" data-date="<?= e($ds) ?>" data-wh="<?= e($whAttr($wh)) ?>" data-label="<?= e($d->format('D j M')) ?>" style="height:<?= $height ?>px">
                <?php foreach ($wh as [$ws, $we]): ?>
                    <div class="tl-wh" style="left:<?= $pct($ws) ?>%;width:<?= $pct($we - $ws) ?>%" title="Working hours <?= e(minutes_to_hhmm($ws) . '–' . minutes_to_hhmm($we)) ?>"></div>
                <?php endforeach; ?>
                <?php if ($ds === $todayStr): ?><div class="tl-now" style="left:<?= $pct($nowMin) ?>%" title="Now"></div><?php endif; ?>
                <?php foreach ($list as $e): ?>
                    <button type="button" class="te" data-entry="<?= e(Calendar::entryPayload($e)) ?>"
                            style="left:<?= $pct($e['start_min']) ?>%;width:<?= $pct($e['minutes']) ?>%;top:<?= $lanePad + $e['lane'] * $laneH ?>px;height:<?= $laneH - 3 ?>px;<?= e(Calendar::entryStyle($e, $colorBy)) ?>"
                            title="<?= e($e['start'] . '–' . $e['end'] . ' · ' . $e['client_name'] . ' · ' . $e['action_name'] . ($e['description'] ? "\n" . $e['description'] : '')) ?>">
                        <span class="te-time"><?= e($e['start'] . '–' . $e['end']) ?></span>
                        <span class="te-name"><?= e($e['client_name']) ?></span>
                        <span class="te-act"><?= e($e['action_name']) ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
            <div class="tl-sum" title="<?= e(fmt_dec($dayMin)) ?> h"><?= $dayMin ? e(fmt_dur($dayMin)) : '<span class="muted">–</span>' ?></div>
        </div>
    <?php endforeach; ?>
</div>
</div>
<p class="hint">Drag across hours to create a time report, or right-click an hour. On touch screens, tap an hour. Shaded areas are your working hours.</p>

<?php if ($view === 'day'): ?>
    <section class="card day-list">
        <h2>Time reports</h2>
        <?= TimeTracker\View::capture('calendar/list', get_defined_vars() + ['embedded' => true]) ?>
    </section>
<?php endif; ?>
