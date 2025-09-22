<?php if ($message): ?>
    <div class="notification <?= $messageType ?>">
        <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<div class="columns">
    <div class="column is-two-thirds">
        <div class="card">
            <header class="card-header">
                <p class="card-header-title">
                    <span class="icon"><i class="fas fa-users-cog"></i></span>
                    <span class="ml-2">Administratoren</span>
                </p>
            </header>
            <div class="card-content">
                <?php if ($admins): ?>
                    <table class="table is-fullwidth is-striped">
                        <thead>
                            <tr>
                                <th>Benutzername</th>
                                <th>E-Mail</th>
                                <th>Letzter Login</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($admins as $admin): ?>
                                <tr>
                                    <td><?= htmlspecialchars($admin['username'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($admin['email'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($admin['last_login'] ?? 'Noch nie', ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="has-text-grey">Noch keine Administratoren vorhanden.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="column">
        <div class="card">
            <header class="card-header">
                <p class="card-header-title">Administrator hinzufügen</p>
            </header>
            <div class="card-content">
                <form method="post">
                    <input type="hidden" name="action" value="add">
                    <div class="field">
                        <label class="label" for="username">Benutzername</label>
                        <div class="control">
                            <input id="username" name="username" class="input" required>
                        </div>
                    </div>
                    <div class="field">
                        <label class="label" for="email">E-Mail</label>
                        <div class="control">
                            <input id="email" name="email" type="email" class="input" required>
                        </div>
                    </div>
                    <div class="field">
                        <label class="label" for="password">Passwort</label>
                        <div class="control">
                            <input id="password" name="password" type="password" class="input" required>
                        </div>
                        <p class="help">Das Passwort wird sofort mit Argon2id gehasht.</p>
                    </div>
                    <div class="field">
                        <div class="control">
                            <button type="submit" class="button is-primary is-fullwidth">
                                <span class="icon"><i class="fas fa-plus"></i></span>
                                <span>Administrator anlegen</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
