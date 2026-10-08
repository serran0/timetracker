<?php
use TimeTracker\Export\ReportBuilder;

/** @var DateTimeImmutable $from */
/** @var DateTimeImmutable $to */
/** @var array $filters */
/** @var array $opts */
/** @var array $clients */
/** @var array $actions */
/** @var array $presets */
/** @var array $summary */
/** @var array $table */
/** @var int $rowCount */
/** @var bool $xlsxOk */
/** @var array $user */
$fromStr = $from->format('Y-m-d');
$toStr = $to->format('Y-m-d');
?>
<div class="page-head">
    <div>
        <h1><?= te('Export') ?></h1>
        <p class="muted"><?= te('Export your time reports for invoicing, for example at the end of the month.') ?></p>
    </div>
</div>

<form method="get" action="export.php" class="export-grid" data-export-form>
    <input type="hidden" name="submitted" value="1">
    <div class="export-main">
        <section class="card">
            <h2><?= te('1 · Period') ?></h2>
            <div class="grid-3">
                <label><?= te('From') ?> <input type="date" name="from" value="<?= e($fromStr) ?>" required></label>
                <label><?= te('To') ?> <input type="date" name="to" value="<?= e($toStr) ?>" required></label>
                <label><?= te('Quick select') ?>
                    <select data-preset-select>
                        <option value=""><?= te('Custom…') ?></option>
                        <?php foreach ($presets as $label => [$pf, $pt]): ?>
                            <option value="<?= e($pf . '|' . $pt) ?>" <?= ($pf === $fromStr && $pt === $toStr) ? 'selected' : '' ?>><?= te($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
        </section>

        <section class="card">
            <h2><?= te('2 · What to include') ?></h2>
            <div class="grid-2">
                <fieldset>
                    <legend><?= te('Clients') ?> <small class="muted"><?= te('(none ticked = all)') ?></small></legend>
                    <?php foreach ($clients as $c): ?>
                        <label class="check-label"><input type="checkbox" name="client[]" value="<?= (int) $c['id'] ?>" <?= in_array((int) $c['id'], $filters['clients'], true) ? 'checked' : '' ?>>
                            <span class="dot" style="background:<?= e($c['color']) ?>"></span><?= e($c['name']) ?></label>
                    <?php endforeach; ?>
                    <?php if (!$clients): ?><span class="muted"><?= te('No clients') ?></span><?php endif; ?>
                </fieldset>
                <fieldset>
                    <legend><?= te('Actions') ?> <small class="muted"><?= te('(none ticked = all)') ?></small></legend>
                    <?php foreach ($actions as $a): ?>
                        <label class="check-label"><input type="checkbox" name="action[]" value="<?= e($a['name']) ?>" <?= in_array($a['name'], $filters['actions'], true) ? 'checked' : '' ?>>
                            <span class="dot" style="background:<?= e($a['color']) ?>"></span><?= e(action_label($a['name'])) ?></label>
                    <?php endforeach; ?>
                </fieldset>
            </div>
            <label><?= te('Billing') ?>
                <select name="billable">
                    <option value=""><?= te('Billable and non-billable') ?></option>
                    <option value="1" <?= $filters['billable'] === '1' ? 'selected' : '' ?>><?= te('Billable only') ?></option>
                    <option value="0" <?= $filters['billable'] === '0' ? 'selected' : '' ?>><?= te('Non-billable only') ?></option>
                </select>
            </label>
        </section>

        <section class="card">
            <h2><?= te('3 · Format & columns') ?></h2>
            <fieldset>
                <legend><?= te('File format') ?></legend>
                <div class="radio-row">
                    <?php foreach (ReportBuilder::FORMATS as $key => $label): ?>
                        <?php $disabled = $key === 'xlsx' && !$xlsxOk; ?>
                        <label class="radio-card <?= $disabled ? 'disabled' : '' ?>">
                            <input type="radio" name="format" value="<?= e($key) ?>" <?= $opts['format'] === $key ? 'checked' : '' ?> <?= $disabled ? 'disabled' : '' ?>>
                            <span><?= te($label) ?><?= $disabled ? '<small>' . te('needs PHP zip extension') . '</small>' : '' ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
            <fieldset>
                <legend><?= te('Columns') ?></legend>
                <div class="col-grid">
                    <?php foreach (ReportBuilder::COLUMNS as $key => [$label]): ?>
                        <label class="check-label"><input type="checkbox" name="cols[]" value="<?= e($key) ?>" <?= in_array($key, $opts['cols'], true) ? 'checked' : '' ?>> <?= te($label) ?></label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
            <div class="grid-3">
                <label><?= te('Duration format') ?>
                    <select name="duration">
                        <option value="decimal" <?= $opts['duration'] === 'decimal' ? 'selected' : '' ?>><?= te('Decimal hours (7.50)') ?></option>
                        <option value="hm" <?= $opts['duration'] === 'hm' ? 'selected' : '' ?>><?= te('Hours:minutes (7:30)') ?></option>
                    </select>
                </label>
                <label><?= te('CSV delimiter') ?>
                    <select name="delimiter">
                        <option value="," <?= $opts['delimiter'] === ',' ? 'selected' : '' ?>><?= te('Comma ( , )') ?></option>
                        <option value=";" <?= $opts['delimiter'] === ';' ? 'selected' : '' ?>><?= te('Semicolon ( ; ) – European Excel') ?></option>
                        <option value="tab" <?= $opts['delimiter'] === 'tab' ? 'selected' : '' ?>><?= te('Tab') ?></option>
                    </select>
                </label>
                <label><?= te('Decimal mark') ?>
                    <select name="decimal">
                        <option value="." <?= $opts['decimal'] === '.' ? 'selected' : '' ?>><?= te('Point ( 7.50 )') ?></option>
                        <option value="," <?= $opts['decimal'] === ',' ? 'selected' : '' ?>><?= te('Comma ( 7,50 )') ?></option>
                    </select>
                </label>
            </div>
            <label class="check-label"><input type="checkbox" name="totals" value="1" <?= $opts['totals'] ? 'checked' : '' ?>> <?= te('Add a totals row') ?></label>
            <label class="check-label"><input type="checkbox" name="vat" value="1" <?= $opts['vat'] ? 'checked' : '' ?>> <?= te('Include VAT (amounts also shown including VAT)') ?></label>
        </section>
    </div>

    <aside class="export-side">
        <section class="card sticky">
            <h2><?= te('Preview') ?></h2>
            <div class="kpis kpis-compact">
                <div class="kpi"><span class="kpi-label"><?= te('Reports') ?></span><span class="kpi-value"><?= (int) $rowCount ?></span></div>
                <div class="kpi"><span class="kpi-label"><?= te('Hours') ?></span><span class="kpi-value"><?= e(fmt_dec($summary['minutes'])) ?></span></div>
                <?php if ($summary['amount'] > 0): ?>
                <div class="kpi"><span class="kpi-label"><?= te('Amount') ?></span><span class="kpi-value"><?= e(fmt_money_vat($summary['amount'], $opts['vat'] ? $summary['amount_vat'] : null)) ?></span></div>
                <?php endif; ?>
            </div>
            <?php if ($table['rows']): ?>
            <div class="table-wrap">
            <table class="table table-compact">
                <thead><tr><?php foreach ($table['headers'] as $h): ?><th><?= e($h) ?></th><?php endforeach; ?></tr></thead>
                <tbody>
                <?php foreach ($table['rows'] as $row): ?>
                    <tr>
                    <?php foreach ($row as $i => $v): ?>
                        <?php
                        $t = $table['types'][$i];
                        $txt = match ($t) {
                            'dur'   => ReportBuilder::formatDuration((int) $v, $opts),
                            'money' => ReportBuilder::formatMoney($v === null ? null : (float) $v, $opts),
                            default => (string) $v,
                        };
                        ?>
                        <td class="<?= in_array($t, ['dur', 'money'], true) ? 'num' : '' ?>"><?= e(mb_strimwidth($txt, 0, 40, '…')) ?></td>
                    <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php if ($rowCount > count($table['rows'])): ?><p class="muted"><?= te('…and {n} more.', ['n' => $rowCount - count($table['rows'])]) ?></p><?php endif; ?>
            <?php else: ?>
                <p class="muted"><?= te('No time reports match this selection.') ?></p>
            <?php endif; ?>
            <div class="form-actions stack">
                <button class="btn btn-primary btn-block" type="submit" name="download" value="1" <?= $rowCount ? '' : 'disabled' ?>><?= te('Download export') ?></button>
                <button class="btn btn-block" type="submit"><?= te('Update preview') ?></button>
            </div>
        </section>
    </aside>
</form>
