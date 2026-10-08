<?php
use TimeTracker\Repository\WorkingHours;

/** @var array $intervals */
/** @var string[] $errors */
$row = static function (int $day, int $i, ?array $iv): string {
    $s = $iv ? minutes_to_hhmm($iv[0]) : '';
    $e = $iv ? minutes_to_hhmm($iv[1]) : '';
    return '<div class="wh-row">'
        . '<input type="text" name="wh[' . $day . '][' . $i . '][start]" value="' . e($s) . '" placeholder="08:00" inputmode="numeric" maxlength="5" aria-label="Start time" list="time-options">'
        . '<span class="muted">–</span>'
        . '<input type="text" name="wh[' . $day . '][' . $i . '][end]" value="' . e($e) . '" placeholder="17:00" inputmode="numeric" maxlength="5" aria-label="End time" list="time-options">'
        . '<button type="button" class="icon-btn" data-wh-remove aria-label="Remove interval" title="Remove">×</button>'
        . '</div>';
};
?>
<div class="page-head">
    <div>
        <h1>Working hours</h1>
        <p class="muted">Your regular schedule. It is highlighted in the weekly and daily calendar views, so overtime and out-of-hours work stands out. Add several intervals to a day for breaks (e.g. 08:00–12:00 and 13:00–17:00). Leave a day empty for a day off.</p>
    </div>
</div>

<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

<form method="post" class="card" data-wh-form>
    <?= csrf_field() ?>
    <div class="wh-days">
    <?php foreach (WorkingHours::DAYS as $d => $label): ?>
        <?php $list = $intervals[$d] ?? []; ?>
        <div class="wh-day" data-day="<?= $d ?>">
            <div class="wh-day-head">
                <strong><?= e($label) ?></strong>
                <?php if ($d === 1): ?><button type="button" class="btn btn-sm btn-ghost" data-wh-copy>Copy to Tue–Fri</button><?php endif; ?>
            </div>
            <div class="wh-rows" data-wh-rows>
                <?php foreach ($list as $i => $iv) { echo $row($d, $i, $iv); } ?>
                <?= $row($d, count($list), null) ?>
            </div>
            <button type="button" class="btn btn-sm btn-ghost" data-wh-add>+ Add interval</button>
        </div>
    <?php endforeach; ?>
    </div>
    <p class="muted">Weekly total: <strong><?= e(fmt_dur(WorkingHours::weeklyMinutes($intervals))) ?> h</strong></p>
    <datalist id="time-options">
        <?php for ($m = 0; $m <= 1440; $m += 30): ?><option value="<?= e(minutes_to_hhmm($m)) ?>"><?php endfor; ?>
    </datalist>
    <div class="form-actions"><button class="btn btn-primary" type="submit">Save working hours</button></div>
</form>
