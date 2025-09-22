<?php /** @var ?string $error */ ?>
<div class="columns is-centered">
    <div class="column is-one-third-desktop is-half-tablet">
        <div class="box">
            <h1 class="title is-4 has-text-centered mb-5">
                <strong><?= htmlspecialchars($config['application']['name'] ?? 'deSEC Manager', ENT_QUOTES, 'UTF-8') ?></strong>
            </h1>

            <?php if ($error): ?>
                <div class="notification is-danger is-light">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form method="post" autocomplete="off">
                <div class="field">
                    <label class="label" for="username">Benutzername</label>
                    <div class="control">
                        <input id="username" name="username" type="text" class="input" required autofocus>
                    </div>
                </div>

                <div class="field">
                    <label class="label" for="password">Passwort</label>
                    <div class="control">
                        <input id="password" name="password" type="password" class="input" required>
                    </div>
                </div>

                <div class="field is-grouped is-justify-content-flex-end">
                    <div class="control">
                        <button type="submit" class="button is-primary">
                            <span class="icon"><i class="fas fa-sign-in-alt"></i></span>
                            <span>Anmelden</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
        <p class="has-text-centered is-size-7 has-text-grey mt-2">
            Standard-Zugangsdaten werden während der Installation erstellt.
        </p>
    </div>
</div>
