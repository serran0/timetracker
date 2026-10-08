<div class="auth-wrap">
    <div class="card">
        <h1><?= te('Already installed') ?></h1>
        <p><?= te('Timetracker is already set up, so the installer is disabled.') ?></p>
        <p class="muted"><?= th('To run setup again, delete {file} (this does not touch the database).', ['file' => '<code>config/config.php</code>']) ?></p>
        <p><a class="btn btn-primary" href="login.php"><?= te('Go to sign in') ?></a></p>
    </div>
</div>
