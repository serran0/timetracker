<?php
/** @var array $actions */
/** @var array $form */
/** @var string[] $errors */
/** @var string[] $palette */
$editing = !empty($form['id']);
?>
<div class="page-head">
    <div>
        <h1>Time actions</h1>
        <p class="muted">Templates for the kind of work you report: normal working time, overtime, emergency call-outs and so on. The multiplier scales the client's hourly rate.</p>
    </div>
</div>

<div class="split">
    <section class="card">
        <?php if (!$actions): ?>
            <div class="empty">No actions yet.</div>
        <?php else: ?>
        <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Action</th><th class="num">Rate ×</th><th>Billable</th><th class="num">Reports</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($actions as $a): ?>
                <tr class="<?= $a['is_archived'] ? 'is-archived' : '' ?>">
                    <td>
                        <span class="dot" style="background:<?= e($a['color']) ?>"></span>
                        <strong><?= e($a['name']) ?></strong>
                        <?php if ($a['is_archived']): ?><span class="badge">Archived</span><?php endif; ?>
                    </td>
                    <td class="num">×<?= e(rtrim(rtrim(number_format((float) $a['rate_multiplier'], 2, '.', ''), '0'), '.')) ?></td>
                    <td><?= $a['is_billable'] ? 'Yes' : '<span class="muted">No</span>' ?></td>
                    <td class="num"><?= (int) $a['entry_count'] ?></td>
                    <td class="row-actions">
                        <a class="btn btn-sm" href="actions.php?edit=<?= (int) $a['id'] ?>">Edit</a>
                        <form method="post" class="inline">
                            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                            <button class="btn btn-sm" name="op" value="<?= $a['is_archived'] ? 'unarchive' : 'archive' ?>"><?= $a['is_archived'] ? 'Restore' : 'Archive' ?></button>
                            <?php if (!$a['entry_count']): ?>
                                <button class="btn btn-sm btn-danger" name="op" value="delete" data-confirm="Delete action &quot;<?= e($a['name']) ?>&quot;?">Delete</button>
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
        <h2><?= $editing ? 'Edit action' : 'New action' ?></h2>
        <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="op" value="save">
            <input type="hidden" name="id" value="<?= (int) ($form['id'] ?? 0) ?>">
            <label>Name
                <input type="text" name="name" value="<?= e($form['name']) ?>" required maxlength="120">
            </label>
            <div class="grid-2">
                <label>Colour
                    <input type="color" name="color" value="<?= e($form['color']) ?>" data-swatch-target>
                    <span class="swatches">
                        <?php foreach ($palette as $p): ?><button type="button" class="swatch" style="background:<?= e($p) ?>" data-color="<?= e($p) ?>" aria-label="<?= e($p) ?>"></button><?php endforeach; ?>
                    </span>
                </label>
                <label>Rate multiplier
                    <input type="text" inputmode="decimal" name="rate_multiplier" value="<?= e((string) $form['rate_multiplier']) ?>">
                    <small class="muted">1 = normal rate, 1.5 = overtime, 2 = emergency…</small>
                </label>
            </div>
            <label class="check-label">
                <input type="checkbox" name="is_billable" value="1" <?= !empty($form['is_billable']) ? 'checked' : '' ?>>
                Billable to the client
            </label>
            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= $editing ? 'Save changes' : 'Add action' ?></button>
                <?php if ($editing): ?><a class="btn" href="actions.php">Cancel</a><?php endif; ?>
            </div>
        </form>
    </section>
</div>
