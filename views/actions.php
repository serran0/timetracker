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
</form>

<?php if (!$client): ?>
    <div class="card empty"><?= te('Select a client to see and manage its actions.') ?></div>
    <?php return; ?>
<?php endif; ?>

<div class="split">
    <section class="card">
        <?php if (!$actions): ?>
            <div class="empty"><?= te('This client has no actions yet. Add one on the right, or start from the standard set.') ?></div>
        <?php else: ?>
        <div class="table-wrap">
        <table class="table">
            <thead><tr><th><?= te('Action') ?></th><th class="num"><?= te('Rate ×') ?></th><th><?= te('Billable') ?></th><th class="num"><?= te('Reports') ?></th><th></th></tr></thead>
            <tbody>
            <?php foreach ($actions as $a): ?>
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
                        <a class="btn btn-sm" href="actions.php?client=<?= (int) $client['id'] ?>&amp;edit=<?= (int) $a['id'] ?>"><?= te('Edit') ?></a>
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
    </section>

    <section class="card">
        <h2><?= te($editing ? 'Edit action' : 'New action') ?></h2>
        <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
        <form method="post">
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
            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= te($editing ? 'Save changes' : 'Add action') ?></button>
                <?php if ($editing): ?><a class="btn" href="actions.php?client=<?= (int) $client['id'] ?>"><?= te('Cancel') ?></a><?php endif; ?>
            </div>
        </form>

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
</div>
