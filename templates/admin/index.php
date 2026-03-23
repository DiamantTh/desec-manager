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
                    <?= __('Administrators') ?>
                </p>
            </header>
            <div class="card-content">
                <?php if ($admins): ?>
                    <table class="table is-fullwidth is-striped">
                        <thead>
                            <tr>
                                <th><?= __('Username') ?></th>
                                <th><?= __('Email') ?></th>
                                <th><?= __('Last Login') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($admins as $admin): ?>
                                <tr>
                                    <td><?= htmlspecialchars($admin['username'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($admin['email'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($admin['last_login'] ?? __('Never'), ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="has-text-grey"><?= __('No administrators found.') ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="column">
        <div class="card">
            <header class="card-header">
                <p class="card-header-title"><?= __('Add administrator') ?></p>
            </header>
            <div class="card-content">
                <form method="post">
                    <input type="hidden" name="action" value="add">
                    <div class="field">
                        <label class="label" for="username"><?= __('Username') ?></label>
                        <div class="control">
                            <input id="username" name="username" class="input" required>
                        </div>
                    </div>
                    <div class="field">
                        <label class="label" for="email"><?= __('Email') ?></label>
                        <div class="control">
                            <input id="email" name="email" type="email" class="input" required>
                        </div>
                    </div>
                    <div class="field">
                        <label class="label" for="password"><?= __('Password') ?></label>
                        <div class="control">
                            <input id="password" name="password" type="password" class="input" required>
                        </div>
                        <p class="help"><?= __('The password will be hashed immediately with Argon2id.') ?></p>
                    </div>
                    <div class="field">
                        <div class="control">
                            <button type="submit" class="button is-primary is-fullwidth">
                                <?= __('Create administrator') ?>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
