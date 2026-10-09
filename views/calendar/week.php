<?php
use TimeTracker\Calendar;

/** @var string $view */
/** @var array $range */
/** @var array $byDate */
/** @var array $hours */
/** @var array $filters */
/** @var DateTimeImmutable $today */
/** @var callable $link */
/** @var array $holidays */

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
$lunch = (!empty($user['lunch_start']) && !empty($user['lunch_end'])) ? [time_to_minutes($user['lunch_start']), time_to_minutes($user['lunch_end'])] : null;
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
        <div class="tl-label"><?= te('Wk {n}', ['n' => $range['from']->format('W')]) ?></div>
        <div class="tl-hours">
            <?php for ($h = 0; $h < 24; $h++): ?><span><?= sprintf('%02d', $h) ?></span><?php endfor; ?>
        </div>
        <div class="tl-sum"><?= te('Total') ?></div>
    </div>
    <?php foreach ($days as $d): ?>
        <?php
        $ds = $d->format('Y-m-d');
        $list = $byDate[$ds] ?? [];
        [$list, $laneCount] = Calendar::assignLanes($list);
        $height = $laneCount * $laneH + $lanePad * 2;
        $dayMin = array_sum(array_column($list, 'minutes'));
        $weekMinTotal += $dayMin;
        $hol = $holidays[$ds] ?? null;
        $wh = $hol ? [] : $hours[(int) $d->format('N')]; // a red day is not a working day: no shaded working hours
        $classes = ['tl-row'];
        if ($hol) { $classes[] = 'hol'; $classes[] = 'hol-' . $hol['kind']; }
        if ($ds === $todayStr) { $classes[] = 'today'; }
        if (!$wh) { $classes[] = 'off'; }
        ?>
        <div class="<?= e(implode(' ', $classes)) ?>">
            <a class="tl-label" href="<?= e($link(['view' => 'day', 'date' => $ds])) ?>" title="<?= te('Open day') ?>">
                <span class="dow"><?= e(date_l10n($d, 'D')) ?></span>
                <span class="dom"><?= e(date_l10n($d, 'j M')) ?></span>
                <?php if ($hol): ?><span class="hol-name"><?= e($hol['name']) ?></span><?php endif; ?>
                <?php if ($view === 'day'): ?><small class="muted"><?= te('Wk {n}', ['n' => $d->format('W')]) ?></small><?php endif; ?>
            </a>
            <div class="tl-track" data-date="<?= e($ds) ?>" data-wh="<?= e($whAttr($wh)) ?>" data-label="<?= e(date_l10n($d, 'D j M')) ?>"<?= $hol ? ' title="' . e($hol['name']) . '"' : '' ?> style="min-height:<?= $height ?>px">
                <?php foreach ($wh as [$ws, $we]): ?>
                    <div class="tl-wh" style="left:<?= $pct($ws) ?>%;width:<?= $pct($we - $ws) ?>%" title="<?= te('Working hours {range}', ['range' => minutes_to_hhmm($ws) . '–' . minutes_to_hhmm($we)]) ?>"></div>
                <?php endforeach; ?>
                <?php if ($ds === $todayStr): ?><div class="tl-now" style="left:<?= $pct($nowMin) ?>%" title="<?= te('Now') ?>"></div><?php endif; ?>
                <?php foreach ($list as $e): ?>
                    <?php
                    // Hatched stripe marking where the unpaid break sits inside the block.
                    $stripe = null;
                    if ($lunch && $e['break_minutes'] > 0 && $e['gross_minutes'] > 0) {
                        $os = max($e['start_min'], $lunch[0]);
                        $oe = min($e['end_min'], $lunch[1]);
                        if ($oe > $os) {
                            $stripe = [round(($os - $e['start_min']) / $e['gross_minutes'] * 100, 3), round(($oe - $os) / $e['gross_minutes'] * 100, 3)];
                        }
                    }
                    $tip = $e['start'] . '–' . $e['end'] . ($e['break_minutes'] ? ' (' . t('−{time} break', ['time' => fmt_dur($e['break_minutes'])]) . ')' : '')
                        . ' · ' . $e['client_name'] . ' · ' . $e['action_label'] . ($e['description'] ? "\n" . $e['description'] : '');
                    ?>
                    <button type="button" class="te" data-entry="<?= e(Calendar::entryPayload($e)) ?>"
                            style="left:<?= $pct($e['start_min']) ?>%;width:<?= $pct($e['gross_minutes']) ?>%;top:<?= $lanePad + $e['lane'] * $laneH ?>px;height:<?= $laneH - 3 ?>px;<?= e(Calendar::entryStyle($e, $colorBy)) ?>"
                            title="<?= e($tip) ?>">
                        <?php if ($stripe): ?><span class="te-break" style="left:<?= $stripe[0] ?>%;width:<?= $stripe[1] ?>%"></span><?php endif; ?>
                        <span class="te-time"><?= e($e['start'] . '–' . $e['end']) ?></span>
                        <span class="te-name"><?= e($e['client_name']) ?></span>
                        <span class="te-act"><?= e($e['action_label']) ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
            <div class="tl-sum" title="<?= e(fmt_dec($dayMin)) ?> h"><?= $dayMin ? e(fmt_dur($dayMin)) : '<span class="muted">–</span>' ?></div>
        </div>
    <?php endforeach; ?>
</div>
</div>
<p class="hint"><?= te('Click an hour to create a one-hour time report, drag across hours for a longer one, or right-click an hour. Drag a report to move it to other hours or days. On touch screens, tap an hour. Shaded areas are your working hours.') ?></p>

<?php if ($view === 'day'): ?>
    <section class="card day-list">
        <h2><?= te('Time reports') ?></h2>
        <?= TimeTracker\View::capture('calendar/list', get_defined_vars() + ['embedded' => true]) ?>
    </section>
<?php endif; ?>
