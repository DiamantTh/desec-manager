<?php
/** @var ?string $error */
$error ??= null;
?>
<div class="columns is-centered">
    <div class="column is-one-third-desktop is-half-tablet">
        <div class="box">
            <h1 class="title is-4 has-text-centered mb-4">
                <?= __('Two-Factor Authentication') ?>
            </h1>
            <p class="has-text-centered has-text-grey mb-5">
                <?= __('Please enter the current code from your authenticator app.') ?>
            </p>

            <?php if ($error): ?>
                <div class="notification is-danger is-light">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form method="post" autocomplete="off">
                <div class="field">
                    <label class="label" for="totp_code"><?= __('Authentication code') ?></label>
                    <div class="control">
                        <input
                            id="totp_code"
                            name="code"
                            type="text"
                            class="input"
                            inputmode="numeric"
                            pattern="[0-9]{6,8}"
                            minlength="6"
                            maxlength="8"
                            placeholder="000000"
                            required
                            autofocus
                            autocomplete="one-time-code"
                        >
                    </div>
                </div>

                <div class="field is-grouped is-justify-content-flex-end mt-4">
                    <div class="control">
                        <a href="/auth/login" class="button is-light"><?= __('Cancel') ?></a>
                    </div>
                    <div class="control">
                        <button type="submit" class="button is-primary"><?= __('Confirm') ?></button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
