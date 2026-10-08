<?php
use TimeTracker\Repository\WorkingHours;

/** @var array $intervals */
/** @var string[] $errors */
$row = static function (int $day, int $i, ?array $iv): string {
    $s = $iv ? minutes_to_hhmm($iv[0]) : '';
    $e = $iv ? minutes_to_hhmm($iv[1]) : '';
    return '<div class="wh-row">'
        . '<input type="text" name="wh[' . $day . '][' . $i . '][start]" value="' . e($s) . '" placeholder="08:00" inputmode="numeric" maxlength="5" aria-label="' . te('Start time') . '" list="time-options">'
        . '<span class="muted">–</span>'
        . '<input type="text" name="wh[' . $day . '][' . $i . '][end]" value="' . e($e) . '" placeholder="17:00" inputmode="numeric" maxlength="5" aria-label="' . te('End time') . '" list="time-options">'
        . '<button type="button" class="icon-btn" data-wh-remove aria-label="' . te('Remove interval') . '" title="' . te('Remove') . '">×</button>'
        . '</div>';
};
?>
<div class="page-head">
    <div>
        <h1><?= te('Working hours') ?></h1>
        <p class="muted"><?= te('Your regular schedule. It is highlighted in the weekly and daily calendar views, so overtime and out-of-hours work stands out. Add several intervals to a day for breaks (e.g. 08:00–12:00 and 13:00–17:00). Leave a day empty for a day off.') ?></p>
    </div>
</div>

<?php
// Stored values are TIME strings (12:00:00); after a validation error show exactly what was typed.
$lunchVal = static fn($v): string => ($v === null || $v === '') ? '' : (preg_match('/^\d{2}:\d{2}:\d{2}$/', (string) $v) ? substr((string) $v, 0, 5) : (string) $v);
?>
<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

<form method="post" class="card" data-wh-form>
    <?= csrf_field() ?>
    <div class="wh-days">
    <?php foreach (WorkingHours::DAYS as $d): ?>
        <?php $list = $intervals[$d] ?? []; ?>
        <div class="wh-day" data-day="<?= $d ?>">
            <div class="wh-day-head">
                <strong><?= e(WorkingHours::label($d)) ?></strong>
                <?php if ($d === 1): ?><button type="button" class="btn btn-sm btn-ghost" data-wh-copy><?= te('Copy to Tue–Fri') ?></button><?php endif; ?>
            </div>
            <div class="wh-rows" data-wh-rows>
                <?php foreach ($list as $i => $iv) { echo $row($d, $i, $iv); } ?>
                <?= $row($d, count($list), null) ?>
            </div>
            <button type="button" class="btn btn-sm btn-ghost" data-wh-add><?= te('+ Add interval') ?></button>
        </div>
    <?php endforeach; ?>
    </div>
    <div class="wh-lunch">
        <h2><?= te('Lunch window') ?></h2>
        <p class="muted"><?= te('Your usual unpaid break. When you create a time report, the part of it that overlaps this window is pre-filled as an unpaid break and deducted from the hours, so a report 08:00–17:00 counts as 8:00 h. You can change the break on every report. Leave empty if you do not deduct a break by default.') ?></p>
        <div class="lunch-grid">
            <label><?= te('From') ?>
                <input type="text" name="lunch_start" value="<?= e($lunchVal($user['lunch_start'] ?? null)) ?>" placeholder="12:00" inputmode="numeric" maxlength="5" list="time-options">
            </label>
            <label><?= te('To') ?>
                <input type="text" name="lunch_end" value="<?= e($lunchVal($user['lunch_end'] ?? null)) ?>" placeholder="13:00" inputmode="numeric" maxlength="5" list="time-options">
            </label>
        </div>
    </div>
    <p class="muted"><?= th('Weekly total: {hours}', ['hours' => '<strong>' . e(fmt_dur(WorkingHours::weeklyMinutes($intervals))) . ' h</strong>']) ?></p>
    <datalist id="time-options">
        <?php for ($m = 0; $m <= 1440; $m += 30): ?><option value="<?= e(minutes_to_hhmm($m)) ?>"><?php endfor; ?>
    </datalist>
    <div class="form-actions"><button class="btn btn-primary" type="submit"><?= te('Save working hours') ?></button></div>
</form>
