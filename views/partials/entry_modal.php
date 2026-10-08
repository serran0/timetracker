<?php
/** @var array $clients */
/** @var array $actions */
$hasData = (bool) array_filter($clients, static fn($c) => !$c['is_archived']) && (bool) array_filter($actions, static fn($a) => !$a['is_archived']);
$options = static function (array $rows): string {
    $out = '';
    foreach ([false, true] as $archived) {
        $group = array_filter($rows, static fn($r) => (bool) $r['is_archived'] === $archived);
        if (!$group) {
            continue;
        }
        $out .= $archived ? '<optgroup label="Archived">' : '';
        foreach ($group as $r) {
            $out .= '<option value="' . (int) $r['id'] . '" data-color="' . e($r['color']) . '">' . e($r['name']) . '</option>';
        }
        $out .= $archived ? '</optgroup>' : '';
    }
    return $out;
};
?>
<dialog id="entry-dialog" class="dialog" data-ready="<?= $hasData ? '1' : '0' ?>">
    <form method="dialog" id="entry-form" autocomplete="off">
        <div class="dialog-head">
            <h2 id="entry-title">New time report</h2>
            <button type="button" class="icon-btn" data-dialog-close aria-label="Close">×</button>
        </div>
        <div class="alert alert-error" id="entry-errors" hidden></div>
        <input type="hidden" name="id" value="">
        <div class="grid-2">
            <label>Client
                <select name="client_id" required><?= $options($clients) ?></select>
            </label>
            <label>Action
                <select name="action_id" required><?= $options($actions) ?></select>
            </label>
        </div>
        <div class="grid-3">
            <label>Date
                <input type="date" name="date" required>
            </label>
            <label>Start
                <input type="text" name="start" inputmode="numeric" placeholder="08:00" maxlength="5" required list="time-options" pattern="^\d{1,2}[:.]?\d{0,2}$">
            </label>
            <label>End
                <input type="text" name="end" inputmode="numeric" placeholder="17:00" maxlength="5" required list="time-options" pattern="^\d{1,2}[:.]?\d{0,2}$">
            </label>
        </div>
        <p class="muted dialog-duration" id="entry-duration"></p>
        <label>Description
            <textarea name="description" rows="3" maxlength="5000" placeholder="What did you work on? (optional)"></textarea>
        </label>
        <datalist id="time-options">
            <?php for ($m = 0; $m <= 1440; $m += 30): ?><option value="<?= e(minutes_to_hhmm($m)) ?>"><?php endfor; ?>
        </datalist>
        <div class="dialog-actions">
            <button type="button" class="btn btn-danger" id="entry-delete" hidden>Delete</button>
            <span class="spacer"></span>
            <button type="button" class="btn" data-dialog-close>Cancel</button>
            <button type="submit" class="btn btn-primary" id="entry-save">Save</button>
        </div>
    </form>
</dialog>
