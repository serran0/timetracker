<div class="auth-wrap">
    <div class="auth-head">
        <svg viewBox="0 0 24 24" width="44" height="44" aria-hidden="true"><circle cx="12" cy="12" r="10" fill="#4f46e5"/><path d="M12 6v6l4 2.5" stroke="#fff" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <h1>Timetracker</h1>
        <p class="muted">Sign in to your workspace</p>
    </div>
    <form method="post" class="card" autocomplete="on">
        <?= csrf_field() ?>
        <input type="hidden" name="next" value="<?= e($next) ?>">
        <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
        <label>Username
            <input type="text" name="username" value="<?= e($username) ?>" required autofocus autocomplete="username">
        </label>
        <label>Password
            <input type="password" name="password" required autocomplete="current-password">
        </label>
        <button class="btn btn-primary btn-block" type="submit">Sign in</button>
    </form>
</div>
