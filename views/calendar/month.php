<?php
/** @var array $range */
/** @var array $byDate */
/** @var array $hours */
/** @var array $filters */
/** @var DateTimeImmutable $today */
/** @var callable $link */

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
        <div class="wk-col">Wk</div>
        <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dn): ?><div class="dow"><?= $dn ?></div><?php endforeach; ?>
        <div class="wk-total">Week</div>
    </div>
    <?php foreach ($weeks as $days): ?>
        <?php $weekMin = 0; ?>
        <div class="month-row">
            <a class="wk-col" href="<?= e($link(['view' => 'week', 'date' => $days[0]->format('Y-m-d')])) ?>" title="Open week <?= e($days[0]->format('W')) ?>"><?= e($days[0]->format('W')) ?></a>
            <?php foreach ($days as $d): ?>
                <?php
                $ds = $d->format('Y-m-d');
                $list = $byDate[$ds] ?? [];
                $dayMin = array_sum(array_column($list, 'minutes'));
                $weekMin += $dayMin;
                $classes = ['mv-day'];
                if ($d->format('Y-m') !== $monthNo) { $classes[] = 'out'; }
                if ($ds === $todayStr) { $classes[] = 'today'; }
                if (!$hours[(int) $d->format('N')]) { $classes[] = 'off'; }
                $dayUrl = $link(['view' => 'day', 'date' => $ds]);
                ?>
                <div class="<?= e(implode(' ', $classes)) ?>" data-date="<?= e($ds) ?>" data-wh="<?= e(implode(',', array_map(static fn($iv) => $iv[0] . '-' . $iv[1], $hours[(int) $d->format('N')]))) ?>" data-day-url="<?= e($dayUrl) ?>" data-week-url="<?= e($link(['view' => 'week', 'date' => $ds])) ?>" data-label="<?= e($d->format('D j M')) ?>">
                    <div class="mv-top">
                        <a class="mv-num" href="<?= e($dayUrl) ?>"><?= $d->format('j') === '1' ? e($d->format('j M')) : e($d->format('j')) ?></a>
                        <?php if ($dayMin): ?><span class="mv-total" title="<?= e(fmt_dec($dayMin)) ?> h"><?= e(fmt_dur($dayMin)) ?></span><?php endif; ?>
                    </div>
                    <div class="mv-entries">
                        <?php foreach (array_slice($list, 0, $maxChips) as $e): ?>
                            <button type="button" class="chip" style="<?= e(TimeTracker\Calendar::entryStyle($e, $colorBy)) ?>"
                                    data-entry="<?= e(TimeTracker\Calendar::entryPayload($e)) ?>"
                                    title="<?= e($e['start'] . '–' . $e['end'] . ' · ' . $e['client_name'] . ' · ' . $e['action_name'] . ($e['description'] ? "\n" . $e['description'] : '')) ?>">
                                <span class="chip-time"><?= e($e['start']) ?></span>
                                <span class="chip-text"><?= e($e['client_name']) ?></span>
                            </button>
                        <?php endforeach; ?>
                        <?php if (count($list) > $maxChips): ?>
                            <a class="more" href="<?= e($dayUrl) ?>">+<?= count($list) - $maxChips ?> more</a>
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
<p class="hint">Right-click a day to create a time report. On touch screens, press and hold a day.</p>
