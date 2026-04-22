<?php

declare(strict_types=1);

/**
 * Installer – Phase 1: System-Check
 * Handler + View
 */

/**
 * POST-Handler für Schritt 1.
 *
 * @return string[]
 */
function processStep1(): array
{
    if (!VENDOR_OK) {
        return [t('step1.vendor_missing')];
    }

    $reqs = getRequirements();
    foreach ($reqs as $key => $req) {
        if ($key === 'already_installed') {
            continue;
        }
        if (!$req['ok']) {
            return [t('step1.reqs_not_met')];
        }
    }

    $_SESSION['install_step'] = 2;
    return [];
}

/**
 * View für Schritt 1.
 *
 * @param array<string, array{ok: bool, label: string, detail: string, critical: bool}> $reqs
 * @param bool $allCriticalOk
 */
function renderStep1(array $reqs, bool $allCriticalOk): void
{
    ?>
    <h2 class="title is-5"><?= e(t('step1.heading')) ?></h2>
    <p class="mb-4 has-text-grey is-size-7"><?= e(t('step1.subheading')) ?></p>

    <table class="table is-fullwidth is-striped is-hoverable">
        <thead>
            <tr>
                <th><?= e(t('step1.col_check')) ?></th>
                <th><?= e(t('step1.col_status')) ?></th>
                <th><?= e(t('step1.col_detail')) ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($reqs as $req): ?>
        <tr>
            <td>
                <?= e($req['label']) ?>
                <?php if ($req['critical']): ?>
                <span class="tag is-info is-light is-size-7 tag-required"><?= e(t('step1.required')) ?></span>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($req['ok']): ?>
                <span class="tag is-success"><?= e(t('step1.ok')) ?></span>
                <?php elseif ($req['critical']): ?>
                <span class="tag is-danger"><?= e(t('step1.missing')) ?></span>
                <?php else: ?>
                <span class="tag is-warning"><?= e(t('step1.notice')) ?></span>
                <?php endif; ?>
            </td>
            <td style="font-size:.85rem"><?= $req['detail'] ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php if (!VENDOR_OK): ?>
    <div class="notification is-warning warn-left mb-4">
        <strong><?= e(t('step1.vendor_heading')) ?></strong><br>
        <?= e(t('step1.vendor_body')) ?><br>
        <pre><code>composer install --no-dev</code></pre>
        <?= e(t('step1.vendor_reload')) ?>
    </div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="_csrf" value="<?= e(CSRF_TOKEN) ?>">
        <button type="submit" class="button is-primary"
            <?= !$allCriticalOk ? 'disabled title="' . e(t('step1.btn_disabled_title')) . '"' : '' ?>>
            <?= e(t('step1.btn_next')) ?>
        </button>
    </form>
    <?php
}
