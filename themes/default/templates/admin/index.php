<?php
/**
 * @var list<array<string, mixed>> $users
 * @var int    $currentId
 * @var string $csrfToken
 * @var string|null $message
 * @var string $messageType  'is-success'|'is-danger'
 */
$users       ??= [];
$currentId   ??= 0;
$csrfToken   ??= '';
$message     ??= null;
$messageType ??= 'is-success';
?>

<?php if ($message): ?>
    <div class="notification <?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?> is-light">
        <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<!-- ===================================================================
     Benutzerverwaltung
     =================================================================== -->
<div class="box">
    <h2 class="title is-4"><?= __('User Management') ?></h2>

    <?php if ($users): ?>
        <div class="table-container">
            <table class="table is-fullwidth is-striped is-hoverable">
                <thead>
                    <tr>
                        <th><?= __('Username') ?></th>
                        <th><?= __('Email') ?></th>
                        <th><?= __('Admin') ?></th>
                        <th><?= __('Status') ?></th>
                        <th>TOTP</th>
                        <th><?= __('Created') ?></th>
                        <th><?= __('Last Login') ?></th>
                        <th class="has-text-right"><?= __('Actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <?php
                        $uid     = (int) $u['id'];
                        $isSelf  = $uid === $currentId;
                        $isAdmin = !empty($u['is_admin']);
                        $active  = !empty($u['is_active']);
                        ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8') ?>
                                <?php if ($isSelf): ?>
                                    <span class="tag is-info is-light is-small ml-1"><?= __('You') ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($u['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <span class="tag <?= $isAdmin ? 'is-warning' : 'is-light' ?>">
                                    <?= $isAdmin ? __('Admin') : __('User') ?>
                                </span>
                            </td>
                            <td>
                                <span class="tag <?= $active ? 'is-success' : 'is-danger' ?> is-light">
                                    <?= $active ? __('Active') : __('Inactive') ?>
                                </span>
                            </td>
                            <td>
                                <span class="tag <?= !empty($u['totp_enabled']) ? 'is-success' : 'is-light' ?>">
                                    <?= !empty($u['totp_enabled']) ? __('On') : __('Off') ?>
                                </span>
                            </td>
                            <td class="is-size-7 has-text-grey">
                                <?= htmlspecialchars($u['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td class="is-size-7 has-text-grey">
                                <?= htmlspecialchars($u['last_login'] ?? __('Never'), ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td>
                                <div class="buttons is-right are-small">
                                    <?php if (!$isSelf): ?>
                                        <?php if ($active): ?>
                                            <form method="post" class="is-inline">
                                                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="action" value="deactivate">
                                                <input type="hidden" name="id" value="<?= $uid ?>">
                                                <button type="submit" class="button is-warning is-outlined"
                                                        title="<?= __('Deactivate') ?>">
                                                    <?= __('Deactivate') ?>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="post" class="is-inline">
                                                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="action" value="activate">
                                                <input type="hidden" name="id" value="<?= $uid ?>">
                                                <button type="submit" class="button is-success is-outlined">
                                                    <?= __('Activate') ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($isAdmin): ?>
                                            <form method="post" class="is-inline">
                                                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="action" value="demote">
                                                <input type="hidden" name="id" value="<?= $uid ?>">
                                                <button type="submit" class="button is-light">
                                                    <?= __('Revoke Admin') ?>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="post" class="is-inline">
                                                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="action" value="promote">
                                                <input type="hidden" name="id" value="<?= $uid ?>">
                                                <button type="submit" class="button is-info is-outlined">
                                                    <?= __('Make Admin') ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <form method="post" class="is-inline"
                                              onsubmit="return confirm('<?= __('Really delete this user? This cannot be undone.') ?>')">
                                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $uid ?>">
                                            <button type="submit" class="button is-danger is-outlined">
                                                <?= __('Delete') ?>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="has-text-grey is-size-7"><?= __('(own account)') ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="has-text-grey"><?= __('No users found.') ?></p>
    <?php endif; ?>
</div>

<!-- ===================================================================
     Neuen Benutzer anlegen
     =================================================================== -->
<div class="box">
    <h2 class="title is-5"><?= __('Create User') ?></h2>
    <form method="post" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="action" value="add">

        <div class="columns">
            <div class="column">
                <div class="field">
                    <label class="label" for="username"><?= __('Username') ?></label>
                    <div class="control">
                        <input id="username" name="username" class="input" required autocomplete="off">
                    </div>
                </div>
            </div>
            <div class="column">
                <div class="field">
                    <label class="label" for="email"><?= __('Email') ?></label>
                    <div class="control">
                        <input id="email" name="email" type="email" class="input" required>
                    </div>
                </div>
            </div>
        </div>

        <div class="columns">
            <div class="column">
                <div class="field">
                    <label class="label" for="password"><?= __('Password') ?></label>
                    <div class="control">
                        <input id="password" name="password" type="password" class="input" required
                               minlength="12" autocomplete="new-password">
                    </div>
                    <p class="help"><?= __('At least 12 characters. Will be hashed with Argon2id.') ?></p>
                </div>
            </div>
            <div class="column is-narrow" style="display:flex; align-items:center; padding-top:1.5rem;">
                <div class="field">
                    <div class="control">
                        <label class="checkbox">
                            <input type="checkbox" name="is_admin" value="1">
                            <?= __('Admin privileges') ?>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <div class="field">
            <div class="control">
                <button type="submit" class="button is-primary">
                    <?= __('Create User') ?>
                </button>
            </div>
        </div>
    </form>
</div>
