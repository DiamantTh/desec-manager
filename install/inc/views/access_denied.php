<?php

declare(strict_types=1);

http_response_code(403);
$wrongToken = isset($_POST['install_token']) && $_POST['install_token'] !== '';
$tokenFile  = TOKEN_FILE;
?><!DOCTYPE html>
<html lang="<?= e($GLOBALS['_installer_locale'] ?? 'en') ?>" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <title><?= e(t('access.title')) ?> — DeSEC Manager</title>
    <link rel="stylesheet" href="../../assets/css/bulma.min.css">
    <link rel="icon" type="image/svg+xml" href="../../assets/img/favicon.svg">
    <style>
        :root { --primary: #1976d2; color-scheme: light; }
        body { background: linear-gradient(135deg,#e3f0ff 0%,#e8f5e9 100%); min-height:100vh; }
        .token-card { max-width:640px; margin:3rem auto; }
        pre code { font-size:.875rem; word-break:break-all; white-space:pre-wrap; }
    </style>
</head>
<body>
<section class="section">
    <div class="token-card">
        <div class="has-text-centered mb-5">
            <img src="../../assets/img/logo.svg" alt="DeSEC Manager" width="64" height="64">
            <h1 class="title is-4 mt-2" style="color:#1565c0">DeSEC Manager – <?= e(t('layout.title')) ?></h1>
        </div>

        <?php if ($wrongToken): ?>
        <div class="notification is-danger is-light mb-4" role="alert">
            <strong>❌ <?= e(t('access.invalid_token')) ?></strong>
        </div>
        <?php endif; ?>

        <div class="notification" style="background:#fff8e1;border-left:4px solid #f9a825;color:#4e3900">
            <strong>🔒 <?= e(t('access.protected')) ?></strong><br>
            <?= e(t('access.protected_hint')) ?>
        </div>

        <div class="box">
            <form method="post" action="index.php" autocomplete="off">
                <div class="field">
                    <label class="label" for="install_token"><?= e(t('access.token_label')) ?></label>
                    <div class="control">
                        <input class="input<?= $wrongToken ? ' is-danger' : '' ?>"
                               type="password"
                               id="install_token"
                               name="install_token"
                               placeholder="<?= e(t('access.token_placeholder')) ?>"
                               aria-label="<?= e(t('access.token_label')) ?>"
                               aria-describedby="token-hint"
                               required>
                    </div>
                </div>
                <button type="submit" class="button is-primary is-fullwidth mt-3"
                        style="background:#1976d2;border-color:#1565c0">
                    🔓 <?= e(t('access.unlock')) ?>
                </button>
            </form>

            <hr>
            <p class="is-size-7 has-text-grey-dark" id="token-hint">
                <strong><?= e(t('access.token_retrieve')) ?></strong>
            </p>
            <pre style="background:#f0f4ff;border-radius:6px;padding:.75rem 1rem;margin-top:.5rem"><code>cat <?= htmlspecialchars($tokenFile, ENT_QUOTES, 'UTF-8') ?></code></pre>
        </div>
    </div>
</section>
</body>
</html>
