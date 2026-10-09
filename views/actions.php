<?php
/** @var array $clients */
/** @var ?array $client */
/** @var array $actions */
/** @var array $form */
/** @var string[] $errors */
/** @var string[] $palette */
$editing = !empty($form['id']);
?>
<div class="page-head">
    <div>
        <h1><?= te('Time actions') ?></h1>
        <p class="muted"><?= te("Templates for the kind of work you report (normal working time, overtime, emergency call-outs…). Every client has its own set, so rates and multipliers can differ per client. The multiplier scales that client's hourly rate.") ?></p>
    </div>
</div>

<?php if (!$clients): ?>
    <div class="card empty"><?= th('Actions belong to a client. {link} to create its actions.', ['link' => '<a href="clients.php">' . te('Add your first client') . '</a>']) ?></div>
    <?php return; ?>
<?php endif; ?>

<form method="get" action="actions.php" class="card client-picker">
    <label><?= te('Client') ?>
        <select name="client" data-autosubmit>
            <option value="" <?= $client ? '' : 'selected' ?>><?= te('— Select client —') ?></option>
            <?php foreach ($clients as $c): ?>
                <option value="<?= (int) $c['id'] ?>" <?= $client && (int) $c['id'] === (int) $client['id'] ? 'selected' : '' ?>><?= e($c['name']) ?><?= $c['is_archived'] ? ' ' . te('(archived)') : '' ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <?php if ($client): ?><span class="dot" style="background:<?= e($client['color']) ?>"></span><?php endif; ?>
    <noscript><button class="btn btn-sm"><?= te('Show') ?></button></noscript>
    <?php if ($client): ?><button type="button" class="btn btn-primary picker-add" data-modal-new="action-dialog">+ <?= te('Add action') ?></button><?php endif; ?>
</form>

<?php if (!$client): ?>
    <div class="card empty"><?= te('Select a client to see and manage its actions.') ?></div>
    <?php return; ?>
<?php endif; ?>

<section class="card">
    <?php if (!$actions): ?>
        <div class="empty"><?= te('This client has no actions yet. Add one with the "Add action" button, or start from the standard set below.') ?></div>
    <?php else: ?>
    <div class="table-wrap">
    <table class="table">
        <thead><tr><th><?= te('Action') ?></th><th class="num"><?= te('Rate ×') ?></th><th><?= te('Billable') ?></th><th class="num"><?= te('Reports') ?></th><th></th></tr></thead>
        <tbody>
        <?php foreach ($actions as $a): ?>
            <?php $editData = ['id' => (int) $a['id'], 'name' => action_label($a['name']), 'color' => $a['color'], 'rate_multiplier' => (string) $a['rate_multiplier'], 'is_billable' => (int) $a['is_billable']]; ?>
            <tr class="<?= $a['is_archived'] ? 'is-archived' : '' ?>">
                <td>
                    <span class="dot" style="background:<?= e($a['color']) ?>"></span>
                    <strong><?= e(action_label($a['name'])) ?></strong>
                    <?php if ($a['is_archived']): ?><span class="badge"><?= te('Archived') ?></span><?php endif; ?>
                </td>
                <td class="num">×<?= e(rtrim(rtrim(number_format((float) $a['rate_multiplier'], 2, TimeTracker\I18n::decimalMark(), ''), '0'), TimeTracker\I18n::decimalMark())) ?></td>
                <td><?= $a['is_billable'] ? te('Yes') : '<span class="muted">' . te('No') . '</span>' ?></td>
                <td class="num"><?= (int) $a['entry_count'] ?></td>
                <td class="row-actions">
                    <a class="btn btn-sm" href="actions.php?client=<?= (int) $client['id'] ?>&amp;edit=<?= (int) $a['id'] ?>" data-modal-edit="action-dialog" data-values="<?= e(json_encode($editData, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) ?>"><?= te('Edit') ?></a>
                    <form method="post" class="inline">
                        <?= csrf_field() ?><input type="hidden" name="client_id" value="<?= (int) $client['id'] ?>"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                        <button class="btn btn-sm" name="op" value="<?= $a['is_archived'] ? 'unarchive' : 'archive' ?>"><?= te($a['is_archived'] ? 'Restore' : 'Archive') ?></button>
                        <?php if (!$a['entry_count']): ?>
                            <button class="btn btn-sm btn-danger" name="op" value="delete" data-confirm="<?= te('Delete action "{name}"?', ['name' => action_label($a['name'])]) ?>"><?= te('Delete') ?></button>
                        <?php endif; ?>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>

    <div class="card-sub">
        <h3><?= te('Quick setup for {client}', ['client' => $client['name']]) ?></h3>
        <form method="post" class="inline-form">
            <?= csrf_field() ?><input type="hidden" name="client_id" value="<?= (int) $client['id'] ?>">
            <button class="btn btn-sm" name="op" value="standard"><?= te('Add standard actions') ?></button>
        </form>
        <?php $others = array_filter($clients, static fn($c) => (int) $c['id'] !== (int) $client['id'] && $c['action_count'] > 0); ?>
        <?php if ($others): ?>
        <form method="post" class="inline-form">
            <?= csrf_field() ?><input type="hidden" name="client_id" value="<?= (int) $client['id'] ?>"><input type="hidden" name="op" value="copy">
            <button class="btn btn-sm"><?= te('Copy actions from…') ?></button>
            <select name="from" aria-label="<?= te('Copy actions from…') ?>">
                <?php foreach ($others as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
            </select>
        </form>
        <?php endif; ?>
    </div>
</section>

<?php /* Add / edit action: a modal. It opens by itself when the server has something to show (a validation error, or ?edit=ID). */ ?>
<dialog id="action-dialog" class="dialog" data-form-dialog data-defaults="<?= e(json_encode(['name' => '', 'color' => '#10b981', 'rate_multiplier' => '1.00', 'is_billable' => 1])) ?>" data-open="<?= ($editing || $errors) ? '1' : '0' ?>"
        data-title-new="<?= te('New action') ?>" data-title-edit="<?= te('Edit action') ?>"
        data-submit-new="<?= te('Add action') ?>" data-submit-edit="<?= te('Save changes') ?>">
    <form method="post" autocomplete="off">
        <div class="dialog-head">
            <h2 data-dialog-title><?= te($editing ? 'Edit action' : 'New action') ?></h2>
            <button type="button" class="icon-btn" data-dialog-close aria-label="<?= te('Close') ?>">×</button>
        </div>
        <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
        <?= csrf_field() ?>
        <input type="hidden" name="client_id" value="<?= (int) $client['id'] ?>">
        <input type="hidden" name="op" value="save">
        <input type="hidden" name="id" value="<?= (int) ($form['id'] ?? 0) ?>">
        <label><?= te('Name') ?>
            <input type="text" name="name" value="<?= e($form['name']) ?>" required maxlength="120">
        </label>
        <div class="grid-2">
            <label><?= te('Colour') ?>
                <span class="color-row">
                    <input type="color" name="color" value="<?= e($form['color']) ?>" data-swatch-target>
                    <span class="swatches">
                        <?php foreach ($palette as $p): ?><button type="button" class="swatch<?= strtolower($p) === strtolower($form['color']) ? ' is-active' : '' ?>" style="background:<?= e($p) ?>" data-color="<?= e($p) ?>" aria-label="<?= e($p) ?>"></button><?php endforeach; ?>
                    </span>
                </span>
            </label>
            <label><?= te('Rate multiplier') ?>
                <input type="text" inputmode="decimal" name="rate_multiplier" value="<?= e((string) $form['rate_multiplier']) ?>">
                <small class="muted"><?= te('1 = normal rate, 1.5 = overtime, 2 = emergency…') ?></small>
            </label>
        </div>
        <label class="check-label">
            <input type="checkbox" name="is_billable" value="1" <?= !empty($form['is_billable']) ? 'checked' : '' ?>>
            <?= te('Billable to the client') ?>
        </label>
        <div class="dialog-actions">
            <button class="btn btn-primary" type="submit" data-dialog-submit><?= te($editing ? 'Save changes' : 'Add action') ?></button>
            <button class="btn" type="button" data-dialog-close><?= te('Cancel') ?></button>
        </div>
    </form>
</dialog>
