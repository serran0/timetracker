<?php
/** @var array $clients */
/** @var array $actions */
/** @var array $user */
$activeClients = array_filter($clients, static fn($c) => !$c['is_archived']);
$activeClientIds = array_column($activeClients, 'id');
// Ready when at least one active client has at least one active action.
$hasData = (bool) array_filter($actions, static fn($a) => !$a['is_archived'] && in_array($a['client_id'], $activeClientIds, true));

$clientOptions = '';
foreach ([false, true] as $archived) {
    $group = array_filter($clients, static fn($r) => (bool) $r['is_archived'] === $archived);
    if (!$group) {
        continue;
    }
    $clientOptions .= $archived ? '<optgroup label="Archived">' : '';
    foreach ($group as $c) {
        $clientOptions .= '<option value="' . (int) $c['id'] . '">' . e($c['name']) . '</option>';
    }
    $clientOptions .= $archived ? '</optgroup>' : '';
}

// Actions belong to a client: the dialog filters this list by the selected client.
$actionData = array_map(static fn($a) => [
    'id' => (int) $a['id'], 'client_id' => (int) $a['client_id'], 'name' => $a['name'], 'archived' => (bool) $a['is_archived'],
], $actions);

$lunchAttr = (!empty($user['lunch_start']) && !empty($user['lunch_end']))
    ? time_to_minutes($user['lunch_start']) . '-' . time_to_minutes($user['lunch_end']) : '';
?>
<dialog id="entry-dialog" class="dialog dialog-wide" data-ready="<?= $hasData ? '1' : '0' ?>" data-lunch="<?= e($lunchAttr) ?>">
    <form method="dialog" id="entry-form" autocomplete="off">
        <div class="dialog-head">
            <h2 id="entry-title">New time report</h2>
            <button type="button" class="icon-btn" data-dialog-close aria-label="Close">×</button>
        </div>
        <div class="alert alert-error" id="entry-errors" hidden></div>
        <input type="hidden" name="id" value="">

        <div class="grid-2">
            <label>Client
                <select name="client_id" required><?= $clientOptions ?></select>
            </label>
            <label>Action
                <select name="action_id" required></select>
            </label>
        </div>
        <div class="alert alert-info" id="entry-no-actions" hidden>This client has no actions yet. <a href="actions.php" id="entry-no-actions-link">Add actions</a> first.</div>

        <div class="days" id="entry-days">
            <div class="days-head">
                <span class="col-inc"></span><span>Date</span><span>Start</span><span>End</span><span>Break (min)</span><span class="col-net">Net</span><span></span>
            </div>
            <div class="days-rows" id="entry-rows"></div>
            <div class="days-foot">
                <button type="button" class="btn btn-sm" id="entry-add-day">+ Day</button>
                <button type="button" class="btn btn-sm btn-ghost" id="entry-use-lunch" <?= $lunchAttr === '' ? 'hidden' : '' ?> title="Set every break to the part of the report that falls inside your lunch window">Reset breaks to lunch window</button>
                <span class="muted days-total" id="entry-duration"></span>
            </div>
        </div>

        <label>Description <span class="muted">(applies to all days)</span>
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
    <script type="application/json" id="entry-actions"><?= json_encode($actionData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR) ?></script>
    <template id="entry-row-tpl">
        <div class="dlg-row">
            <span class="col-inc"><input type="checkbox" data-f="include" checked aria-label="Include this day"></span>
            <div class="col-date"><input type="date" data-f="date" required aria-label="Date"><span class="dow" data-f="dow"></span></div>
            <input type="text" data-f="start" placeholder="08:00" inputmode="numeric" maxlength="5" list="time-options" aria-label="Start time">
            <input type="text" data-f="end" placeholder="17:00" inputmode="numeric" maxlength="5" list="time-options" aria-label="End time">
            <input type="number" data-f="break" min="0" max="1440" step="5" inputmode="numeric" aria-label="Unpaid break in minutes">
            <span class="col-net" data-f="net"></span>
            <button type="button" class="icon-btn" data-remove aria-label="Remove this day" title="Remove day">×</button>
            <span class="row-note" data-f="note"></span>
        </div>
    </template>
</dialog>
