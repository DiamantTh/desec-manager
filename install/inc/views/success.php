<?php

declare(strict_types=1);

/**
 * Installer – Erfolgs-View (nach abgeschlossener Installation)
 */

/** @var array{admin_user: string, admin_pass: string} $result */
$result = $_SESSION['install_result'] ?? [];
unset($_SESSION['install_result']);
?>

<div class="notification is-success" role="status">
    <strong>✅ <?= e(t('success.heading')) ?></strong>
</div>

<div class="box" style="border: 2px solid #4caf50">
    <p class="mb-2">
        <strong><?= e(t('success.admin_user')) ?>:</strong>
        <code><?= e($result['admin_user'] ?? '') ?></code>
    </p>
    <p>
        <strong><?= e(t('success.admin_pass')) ?>:</strong>
        <code style="background:#e8f5e9;padding:.3em .6em;border-radius:4px">
            <?= e($result['admin_pass'] ?? '') ?>
        </code>
    </p>
    <p class="mt-3 is-size-7 has-text-danger">⚠️ <?= e(t('success.pass_warning')) ?></p>
</div>

<div class="notification is-danger is-light mt-3 warn-left">
    <strong><?= e(t('success.security_hint')) ?>:</strong>
    <?= e(t('success.security_body')) ?>
</div>

<div class="buttons mt-3">
    <form method="post" style="display:inline">
        <input type="hidden" name="_csrf" value="<?= e(CSRF_TOKEN) ?>">
        <input type="hidden" name="action" value="cleanup">
        <button type="submit" class="button is-danger"
                onclick="return confirm('<?= e(t('success.confirm_delete')) ?>')">
            🗑 <?= e(t('success.delete_installer')) ?>
        </button>
    </form>
    <a href="../../index.php" class="button is-primary">→ <?= e(t('layout.to_app')) ?></a>
</div>
