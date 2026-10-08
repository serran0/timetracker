<?php
/** @var array $summary */
/** @var string $label */
/** @var array $user */
$cur = $user['currency'];
$totalMin = max(1, $summary['minutes']);
$groups = [t('Per client') => $summary['by_client'], t('Per action') => $summary['by_action']];
?>
<section class="summary card" aria-label="<?= te('Summary') ?>">
    <div class="summary-head">
        <h2><?= te('Summary') ?> <span class="muted">· <?= e($label) ?></span></h2>
    </div>
    <div class="kpis">
        <div class="kpi">
            <span class="kpi-label"><?= te('Total hours') ?></span>
            <span class="kpi-value"><?= e(fmt_dur($summary['minutes'])) ?></span>
            <span class="kpi-sub"><?= e(fmt_dec($summary['minutes'])) ?> h</span>
        </div>
        <div class="kpi">
            <span class="kpi-label"><?= te('Billable') ?></span>
            <span class="kpi-value"><?= e(fmt_dur($summary['billable_minutes'])) ?></span>
            <span class="kpi-sub"><?= e(fmt_dec($summary['billable_minutes'])) ?> h</span>
        </div>
        <?php if ($summary['amount'] > 0): ?>
        <div class="kpi">
            <span class="kpi-label"><?= te('Est. amount') ?></span>
            <span class="kpi-value"><?= e(fmt_money($summary['amount'])) ?></span>
            <span class="kpi-sub"><?= e($cur) ?><?php if (abs($summary['amount_vat'] - $summary['amount']) >= 0.005): ?> · (<?= te('{amount} incl. VAT', ['amount' => fmt_money($summary['amount_vat'])]) ?>)<?php endif; ?></span>
        </div>
        <?php endif; ?>
        <div class="kpi">
            <span class="kpi-label"><?= te('Time reports') ?></span>
            <span class="kpi-value"><?= (int) $summary['count'] ?></span>
            <span class="kpi-sub">&nbsp;</span>
        </div>
    </div>
    <?php if ($summary['count']): ?>
    <div class="breakdowns">
        <?php foreach ($groups as $heading => $rows): ?>
        <div class="breakdown">
            <h3><?= e($heading) ?></h3>
            <?php foreach ($rows as $r): ?>
                <div class="bd-row">
                    <span class="dot" style="background:<?= e($r['color']) ?>"></span>
                    <span class="bd-name"><?= e($r['label'] ?? $r['name']) ?></span>
                    <span class="bd-val"><?= e(fmt_dur($r['minutes'])) ?> <small class="muted">(<?= e(fmt_dec($r['minutes'])) ?> h)</small></span>
                    <span class="bd-bar"><i style="width:<?= round($r['minutes'] / $totalMin * 100, 1) ?>%;background:<?= e($r['color']) ?>"></i></span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
        <p class="muted"><?= te('No time reports in this period.') ?></p>
    <?php endif; ?>
</section>
