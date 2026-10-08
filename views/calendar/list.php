<?php
/** @var array $entries */
/** @var array $byDate */
/** @var array $user */
/** @var array $filters */
$embedded ??= false;
$colorBy = $filters['color'];
$cur = $user['currency'];
$total = 0;
?>
<?php if (!$entries): ?>
    <div class="card empty"><?= te('No time reports found for this selection.') ?></div>
<?php else: ?>
<div class="<?= $embedded ? '' : 'card ' ?>table-wrap">
<table class="table entries-table">
    <thead>
    <tr><th><?= te('Date') ?></th><th><?= te('Time') ?></th><th class="num"><?= te('Hours') ?></th><th><?= te('Details') ?></th><th class="num"><?= te('Amount') ?></th></tr>
    </thead>
    <?php foreach ($byDate as $date => $rows): ?>
        <?php
        $d = new DateTimeImmutable($date);
        $dayMin = array_sum(array_column($rows, 'minutes'));
        $total += $dayMin;
        ?>
        <tbody>
        <?php if (!$embedded): ?>
            <tr class="day-row">
                <td colspan="2"><strong><?= e(date_l10n($d, 'D j M Y')) ?></strong> <span class="muted">· <?= te('week {n}', ['n' => $d->format('W')]) ?></span></td>
                <td class="num"><strong><?= e(fmt_dur($dayMin)) ?></strong></td>
                <td colspan="2"></td>
            </tr>
        <?php endif; ?>
        <?php foreach ($rows as $e): ?>
            <tr class="entry-row" data-entry="<?= e(TimeTracker\Calendar::entryPayload($e)) ?>" tabindex="0">
                <td class="nowrap"><?= e(date_l10n($d, 'D j M')) ?></td>
                <td class="nowrap"><?= e($e['start'] . '–' . $e['end']) ?></td>
                <td class="num"><?= e(fmt_dur($e['minutes'])) ?> <small class="muted"><?= e(fmt_dec($e['minutes'])) ?></small>
                    <?php if ($e['break_minutes']): ?><br><small class="muted" title="<?= te('Unpaid break deducted') ?>"><?= te('−{time} break', ['time' => fmt_dur($e['break_minutes'])]) ?></small><?php endif; ?></td>
                <td>
                    <span class="dot" style="background:<?= e($e['client_color']) ?>"></span><strong><?= e($e['client_name']) ?></strong>
                    <span class="tag" style="--c:<?= e($e['action_color']) ?>"><?= e($e['action_label']) ?></span>
                    <?php if (!$e['billable']): ?><span class="badge"><?= te('non-billable') ?></span><?php endif; ?>
                    <?php if ($e['description']): ?><div class="desc"><?= nl2br(e($e['description'])) ?></div><?php endif; ?>
                </td>
                <td class="num"><?= $e['amount'] !== null ? e(fmt_money($e['amount'])) : '<span class="muted">–</span>' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    <?php endforeach; ?>
    <tfoot>
    <tr><td colspan="2"><strong><?= te('Total') ?></strong></td><td class="num"><strong><?= e(fmt_dur($total)) ?></strong></td><td></td>
        <td class="num"><strong><?= $summary['amount'] > 0 ? e(fmt_money($summary['amount']) . ' ' . $cur) : '' ?></strong></td></tr>
    </tfoot>
</table>
</div>
<?php endif; ?>
