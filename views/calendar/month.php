<?php
/** @var array $range */
/** @var array $byDate */
/** @var array $hours */
/** @var array $filters */
/** @var DateTimeImmutable $today */
/** @var callable $link */
/** @var array $holidays */

$colorBy = $filters['color'];
$todayStr = $today->format('Y-m-d');
$monthNo = $range['month']->format('Y-m');
$maxChips = 3;

$weeks = [];
for ($d = $range['from']; $d <= $range['to']; $d = $d->modify('+1 day')) {
    $weeks[$d->format('o-W')][] = $d;
}
?>
<div class="month" data-month>
    <div class="month-row month-headrow">
        <div class="wk-col"><?= te('Wk') ?></div>
        <?php for ($i = 1; $i <= 7; $i++): ?><div class="dow"><?= e(TimeTracker\I18n::dayName($i, true)) ?></div><?php endfor; ?>
        <div class="wk-total" title="<?= te('Hours per week') ?>"><?= te('Hrs') ?></div>
    </div>
    <?php foreach ($weeks as $days): ?>
        <?php
        $weekMin = 0;
        // Data for "Report whole working week": one row per day with working hours.
        $weekData = array_map(static fn($wd) => [
            'date'  => $wd->format('Y-m-d'),
            'label' => date_l10n($wd, 'D j M'),
            'wh'    => implode(',', array_map(static fn($iv) => $iv[0] . '-' . $iv[1], $hours[(int) $wd->format('N')])),
            'n'     => $dayCounts[$wd->format('Y-m-d')] ?? 0,
            'h'     => $holidays[$wd->format('Y-m-d')]['name'] ?? '', // red day: listed, but not ticked by default
        ], $days);
        ?>
        <div class="month-row">
            <a class="wk-col" href="<?= e($link(['view' => 'week', 'date' => $days[0]->format('Y-m-d')])) ?>" title="<?= te('Open week {n} – right-click for more', ['n' => $days[0]->format('W')]) ?>"
               data-week="<?= e(json_encode($weekData, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) ?>" data-week-no="<?= e($days[0]->format('W')) ?>"><?= e($days[0]->format('W')) ?></a>
            <?php foreach ($days as $d): ?>
                <?php
                $ds = $d->format('Y-m-d');
                $list = $byDate[$ds] ?? [];
                $dayMin = array_sum(array_column($list, 'minutes'));
                $weekMin += $dayMin;
                $hol = $holidays[$ds] ?? null;
                $whDay = $hol ? [] : $hours[(int) $d->format('N')]; // a red day is not a working day
                $classes = ['mv-day'];
                if ($hol) { $classes[] = 'hol'; $classes[] = 'hol-' . $hol['kind']; }
                if ($d->format('Y-m') !== $monthNo) { $classes[] = 'out'; }
                if ($ds === $todayStr) { $classes[] = 'today'; }
                if (!$whDay) { $classes[] = 'off'; }
                $dayUrl = $link(['view' => 'day', 'date' => $ds]);
                ?>
                <div class="<?= e(implode(' ', $classes)) ?>" data-date="<?= e($ds) ?>" data-wh="<?= e(implode(',', array_map(static fn($iv) => $iv[0] . '-' . $iv[1], $whDay))) ?>" data-day-url="<?= e($dayUrl) ?>" data-week-url="<?= e($link(['view' => 'week', 'date' => $ds])) ?>" data-label="<?= e(date_l10n($d, 'D j M')) ?>">
                    <div class="mv-top">
                        <a class="mv-num" href="<?= e($dayUrl) ?>"><?= $d->format('j') === '1' ? e(date_l10n($d, 'j M')) : e($d->format('j')) ?></a>
                        <?php if ($dayMin): ?><span class="mv-total" title="<?= e(fmt_dec($dayMin)) ?> h"><?= e(fmt_dur($dayMin)) ?></span><?php endif; ?>
                    </div>
                    <?php if ($hol): ?><span class="hol-name" title="<?= e($hol['name']) ?>"><?= e($hol['name']) ?></span><?php endif; ?>
                    <div class="mv-entries">
                        <?php foreach (array_slice($list, 0, $maxChips) as $e): ?>
                            <button type="button" class="chip" style="<?= e(TimeTracker\Calendar::entryStyle($e, $colorBy)) ?>"
                                    data-entry="<?= e(TimeTracker\Calendar::entryPayload($e)) ?>"
                                    title="<?= e($e['start'] . '–' . $e['end'] . ' · ' . $e['client_name'] . ' · ' . $e['action_label'] . ($e['description'] ? "\n" . $e['description'] : '')) ?>">
                                <span class="chip-time"><?= te('{h} hrs', ['h' => fmt_hours_short($e['minutes'])]) ?></span>
                                <span class="chip-text"><?= e($e['client_name']) ?></span>
                            </button>
                        <?php endforeach; ?>
                        <?php if (count($list) > $maxChips): ?>
                            <a class="more" href="<?= e($dayUrl) ?>"><?= te('+{n} more', ['n' => count($list) - $maxChips]) ?></a>
                        <?php endif; ?>
                        <?php if (count($list) > 0): ?>
                            <span class="dots" aria-hidden="true">
                                <?php foreach (array_slice($list, 0, 6) as $e): ?><i style="background:<?= e($colorBy === 'action' ? $e['action_color'] : $e['client_color']) ?>"></i><?php endforeach; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <div class="wk-total"><?= $weekMin ? e(fmt_dur($weekMin)) : '<span class="muted">–</span>' ?></div>
        </div>
    <?php endforeach; ?>
</div>
<p class="hint"><?= te('Right-click a day to create a time report, or right-click a week number to report a whole working week. Drag a report to another day to move it. On touch screens, press and hold.') ?></p>
