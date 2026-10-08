<?php
/** @var array $user */
/** @var string[] $errors */
?>
<div class="auth-wrap">
    <div class="auth-head">
        <svg viewBox="0 0 24 24" width="44" height="44" aria-hidden="true"><circle cx="12" cy="12" r="10" fill="#4f46e5"/><path d="M12 6v6l4 2.5" stroke="#fff" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <h1><?= te('Choose a new password') ?></h1>
        <p class="muted"><?= te('Signed in as {name}. You must choose your own password before you continue.', ['name' => $user['username']]) ?></p>
    </div>
    <form method="post" class="card" autocomplete="off">
        <?= csrf_field() ?>
        <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
        <label><?= te('New password') ?> <span class="muted"><?= te('(min. 10 characters)') ?></span>
            <input type="password" name="new_password" required autofocus autocomplete="new-password">
        </label>
        <label><?= te('Repeat new password') ?>
            <input type="password" name="new_password2" required autocomplete="new-password">
        </label>
        <button class="btn btn-primary btn-block" type="submit"><?= te('Save password') ?></button>
    </form>
    <form method="post" action="logout.php" class="center-note"><?= csrf_field() ?><button class="link-btn" type="submit"><?= te('Sign out') ?></button></form>
</div>
