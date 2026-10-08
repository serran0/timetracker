<?php
/** @var array $clients */
/** @var array $form */
/** @var string[] $errors */
/** @var string[] $palette */
/** @var array $user */
$editing = !empty($form['id']);
?>
<div class="page-head">
    <div>
        <h1><?= te('Clients') ?></h1>
        <p class="muted"><?= te('The customers you bill. Each client gets a colour that is used in the calendar views.') ?></p>
    </div>
</div>

<div class="split">
    <section class="card">
        <?php if (!$clients): ?>
            <div class="empty"><?= te('No clients yet. Add your first client to start tracking time.') ?></div>
        <?php else: ?>
        <div class="table-wrap">
        <table class="table">
            <?php
            /** Sortable column header: the first click sorts ascending (numbers: highest first), a second click reverses. */
            $th = static function (string $key, string $label, bool $num = false) use ($sort, $dir): string {
                $active = $sort === $key;
                $next = $active ? ($dir === 'asc' ? 'desc' : 'asc') : ($num ? 'desc' : 'asc');
                $arrow = $active ? '<span class="sort-arrow" aria-hidden="true">' . ($dir === 'asc' ? '▲' : '▼') . '</span>' : '';
                return '<th class="' . ($num ? 'num ' : '') . ($active ? 'sorted' : '') . '"' . ($active ? ' aria-sort="' . ($dir === 'asc' ? 'ascending' : 'descending') . '"' : '') . '>'
                    . '<a class="sort-link" href="' . e(url('clients.php', ['sort' => $key, 'dir' => $next])) . '">' . e(t($label)) . $arrow . '</a></th>';
            };
            ?>
            <thead><tr><?= $th('name', 'Client') ?><?= $th('rate', 'Rate / h', true) ?><th class="num"><?= te('VAT') ?></th><?= $th('actions', 'Actions', true) ?><?= $th('reports', 'Reports', true) ?><th></th></tr></thead>
            <tbody>
            <?php foreach ($clients as $c): ?>
                <tr class="<?= $c['is_archived'] ? 'is-archived' : '' ?>">
                    <td>
                        <span class="dot" style="background:<?= e($c['color']) ?>"></span>
                        <strong><?= e($c['name']) ?></strong>
                        <?php if ($c['is_archived']): ?><span class="badge"><?= te('Archived') ?></span><?php endif; ?>
                        <?php if ($c['reference']): ?><br><small class="muted"><?= e($c['reference']) ?></small><?php endif; ?>
                    </td>
                    <td class="num"><?= $c['hourly_rate'] !== null ? e(fmt_money((float) $c['hourly_rate'], $user['currency'])) : '<span class="muted">–</span>' ?></td>
                    <td class="num"><?= (float) $c['vat_percent'] > 0 ? e(rtrim(rtrim(number_format((float) $c['vat_percent'], 2, TimeTracker\I18n::decimalMark(), ''), '0'), TimeTracker\I18n::decimalMark())) . ' %' : '<span class="muted">–</span>' ?></td>
                    <td class="num"><a href="actions.php?client=<?= (int) $c['id'] ?>"><?= (int) $c['action_count'] ?></a></td>
                    <td class="num"><?= (int) $c['entry_count'] ?></td>
                    <td class="row-actions">
                        <a class="btn btn-sm" href="clients.php?edit=<?= (int) $c['id'] ?>"><?= te('Edit') ?></a>
                        <form method="post" class="inline">
                            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                            <button class="btn btn-sm" name="op" value="<?= $c['is_archived'] ? 'unarchive' : 'archive' ?>"><?= te($c['is_archived'] ? 'Restore' : 'Archive') ?></button>
                            <?php if (!$c['entry_count']): ?>
                                <button class="btn btn-sm btn-danger" name="op" value="delete" data-confirm="<?= te('Delete client "{name}"?', ['name' => $c['name']]) ?>"><?= te('Delete') ?></button>
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
        <h2><?= te($editing ? 'Edit client' : 'New client') ?></h2>
        <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="op" value="save">
            <input type="hidden" name="id" value="<?= (int) ($form['id'] ?? 0) ?>">
            <label><?= te('Name') ?>
                <input type="text" name="name" value="<?= e($form['name']) ?>" required maxlength="160">
            </label>
            <label><?= te('Reference') ?> <span class="muted"><?= te('(PO number, customer id… optional)') ?></span>
                <input type="text" name="reference" value="<?= e($form['reference'] ?? '') ?>" maxlength="160">
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
                <label><?= te('Hourly rate ({currency})', ['currency' => $user['currency']]) ?>
                    <input type="text" inputmode="decimal" name="hourly_rate" value="<?= $form['hourly_rate'] !== null ? e((string) $form['hourly_rate']) : '' ?>" placeholder="<?= te('e.g. 95') ?>">
                </label>
                <label><?= te('VAT (%)') ?>
                    <input type="text" inputmode="decimal" name="vat_percent" value="<?= (float) ($form['vat_percent'] ?? 0) > 0 ? e((string) (float) $form['vat_percent']) : '' ?>" placeholder="<?= te('e.g. 25') ?>">
                    <small class="muted"><?= te('Amounts for this client are also shown including VAT, in parentheses. Leave empty for none.') ?></small>
                </label>
            </div>
            <label><?= te('Notes') ?>
                <textarea name="notes" rows="3"><?= e($form['notes'] ?? '') ?></textarea>
            </label>
            <?php if (!$editing): ?>
            <label><?= te('Time actions for this client') ?>
                <select name="seed">
                    <option value="standard"><?= te('Standard set (normal, overtime, emergency, travel, non-billable)') ?></option>
                    <?php foreach ($clients as $c): if (!$c['is_archived'] && $c['action_count'] > 0): ?>
                        <option value="copy:<?= (int) $c['id'] ?>"><?= te('Copy from {client}', ['client' => $c['name']]) ?></option>
                    <?php endif; endforeach; ?>
                    <option value="none"><?= te('None – I will add them myself') ?></option>
                </select>
            </label>
            <?php endif; ?>
            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= te($editing ? 'Save changes' : 'Add client') ?></button>
                <?php if ($editing): ?><a class="btn" href="clients.php"><?= te('Cancel') ?></a><?php endif; ?>
            </div>
        </form>
    </section>
</div>
