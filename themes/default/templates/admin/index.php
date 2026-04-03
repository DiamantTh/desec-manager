<?php
/**
 * @var list<array<string, mixed>> $users
 * @var list<array<string, mixed>> $sessions
 * @var int    $currentId
 * @var string $csrfToken
 * @var string|null $message
 * @var string $messageType  'is-success'|'is-danger'
 */
$users       ??= [];
$sessions    ??= [];
$currentId   ??= 0;
$csrfToken   ??= '';
$message     ??= null;
$messageType ??= 'is-success';

$now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

/** Helper: Boolean-Spalte als Icon ausgeben */
$icon = static function (bool $val): string {
    return $val
        ? '<span class="icon has-text-success" title="Ja"><span>&#10003;</span></span>'
        : '<span class="icon has-text-danger"  title="Nein"><span>&#10007;</span></span>';
};
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

<!-- ===================================================================
     Aktive Sessions
     =================================================================== -->
<div class="box">
    <h2 class="title is-4"><?= __('Currently active sessions') ?></h2>
    <p class="has-text-grey is-size-7 mb-4">
        <?= __('Sessions can be invalidated here. The user will be logged out on their next request.') ?>
        <?= __('Sessions without a tracking token (created before this feature was added) are shown without action.') ?>
    </p>

    <?php if ($sessions): ?>
        <div class="table-container">
            <table class="table is-fullwidth is-striped is-hoverable is-size-7">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th><?= __('User') ?></th>
                        <th title="<?= __('Session is valid and not expired') ?>"><?= __('Valid') ?></th>
                        <th title="<?= __('Connection was established via TLS/HTTPS') ?>">TLS</th>
                        <th title="<?= __('Two-factor authentication was used') ?>">2FA</th>
                        <th><?= __('Login at') ?></th>
                        <th><?= __('Valid until') ?></th>
                        <th><?= __('Client IP') ?></th>
                        <th><?= __('User Agent') ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sessions as $s): ?>
                        <?php
                        $sid        = (int) $s['id'];
                        $isValid    = (bool) $s['is_valid'] && ((string)($s['valid_until'] ?? '')) >= $now;
                        $isTls      = (bool) $s['is_tls'];
                        $mfaUsed    = (bool) $s['mfa_used'];
                        $uname      = htmlspecialchars((string)($s['username'] ?? ''), ENT_QUOTES, 'UTF-8');
                        $loginAt    = htmlspecialchars((string)($s['login_at']    ?? ''), ENT_QUOTES, 'UTF-8');
                        $validUntil = htmlspecialchars((string)($s['valid_until'] ?? ''), ENT_QUOTES, 'UTF-8');
                        $ip         = htmlspecialchars((string)($s['client_ip']   ?? ''), ENT_QUOTES, 'UTF-8');
                        $ua         = htmlspecialchars((string)($s['user_agent']  ?? ''), ENT_QUOTES, 'UTF-8');
                        // UA kürzen für Anzeige
                        $uaShort    = mb_strlen($ua) > 60 ? mb_substr($ua, 0, 57) . '...' : $ua;
                        ?>
                        <tr class="<?= $isValid ? '' : 'has-text-grey' ?>">
                            <td><?= $sid ?></td>
                            <td><?= $uname ?></td>
                            <td class="has-text-centered"><?= $icon($isValid) ?></td>
                            <td class="has-text-centered"><?= $icon($isTls) ?></td>
                            <td class="has-text-centered"><?= $icon($mfaUsed) ?></td>
                            <td><?= $loginAt ?></td>
                            <td><?= $validUntil ?></td>
                            <td><code><?= $ip ?></code></td>
                            <td title="<?= $ua ?>"><?= $uaShort ?></td>
                            <td>
                                <?php if ($isValid): ?>
                                    <form method="post">
                                        <input type="hidden" name="csrf"   value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="action" value="invalidate_session">
                                        <input type="hidden" name="id"     value="<?= $sid ?>">
                                        <button type="submit" class="button is-danger is-small is-outlined"
                                                title="<?= __('Invalidate session') ?>"
                                                onclick="return confirm('<?= __('Really invalidate this session?') ?>')">
                                            <?= __('Invalidate') ?>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="tag is-light is-small"><?= __('Expired') ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="has-text-grey"><?= __('No sessions recorded yet.') ?></p>
    <?php endif; ?>
</div>
