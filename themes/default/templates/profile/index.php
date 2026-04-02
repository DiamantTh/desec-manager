<?php
/**
 * @var array<string, mixed>|null $user
 * @var list<\App\Entity\WebAuthnCredential> $webAuthnKeys
 * @var bool $totpEnabled
 * @var ?string $message
 * @var string $messageType  'is-success'|'is-danger'
 * @var string[] $availableThemes
 */
$webAuthnKeys    ??= [];
$totpEnabled     ??= false;
$message         ??= null;
$messageType     ??= 'is-success';
$availableThemes ??= ['default', 'bulma'];
?>

<?php if ($message): ?>
    <div class="notification <?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?> is-light mb-4">
        <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<?php if (!$user): ?>
    <div class="notification is-danger"><?= __('User data could not be loaded.') ?></div>
<?php else: ?>

    <!-- ===================================================================
         Profil-Informationen
         =================================================================== -->
    <div class="box">
        <h2 class="title is-4"><?= __('Profile') ?></h2>
        <div class="columns">
            <div class="column is-half">
                <p class="has-text-weight-semibold"><?= __('Username') ?></p>
                <p><?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?></p>

                <p class="has-text-weight-semibold mt-4"><?= __('Email') ?></p>
                <p><?= htmlspecialchars($user['email'] ?? '–', ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div class="column is-half">
                <p class="has-text-weight-semibold"><?= __('Created at') ?></p>
                <p><?= htmlspecialchars($user['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>

                <p class="has-text-weight-semibold mt-4"><?= __('Last login') ?></p>
                <p><?= htmlspecialchars($user['last_login'] ?? __('Never'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </div>
    </div>

    <!-- ===================================================================
         Passwort ändern
         =================================================================== -->
    <div class="box">
        <h2 class="title is-5"><?= __('Change password') ?></h2>
        <form method="post" autocomplete="off">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="change_password">
            <div class="field">
                <label class="label" for="current_password"><?= __('Current password') ?></label>
                <div class="control">
                    <input id="current_password" name="current_password" type="password" class="input" required>
                </div>
            </div>
            <div class="field">
                <label class="label" for="new_password"><?= __('New password') ?></label>
                <div class="control">
                    <input id="new_password" name="new_password" type="password" class="input" required minlength="12">
                </div>
                <p class="help"><?= __('At least 12 characters.') ?></p>
            </div>
            <div class="field">
                <label class="label" for="new_password_confirm"><?= __('Confirm new password') ?></label>
                <div class="control">
                    <input id="new_password_confirm" name="new_password_confirm" type="password" class="input" required minlength="12">
                </div>
            </div>
            <div class="field is-grouped is-justify-content-flex-end">
                <div class="control">
                    <button type="submit" class="button is-primary"><?= __('Change password') ?></button>
                </div>
            </div>
        </form>
    </div>

    <!-- ===================================================================
         Zwei-Faktor-Authentifizierung — TOTP
         =================================================================== -->
    <div class="box">
        <h2 class="title is-5">Authenticator-App (TOTP)</h2>

        <?php if ($totpEnabled): ?>
            <div class="notification is-success is-light">
                TOTP <?= __('is active') ?>. <?= __('Your account is protected with an authenticator app.') ?>
            </div>
            <form method="post" id="totp-disable-form">
                <input type="hidden" name="action" value="disable_totp">
                <button type="submit" class="button is-danger is-outlined"
                        onclick="return confirm('<?= __('Really disable TOTP?') ?>')">
                    <?= __('Disable TOTP') ?>
                </button>
            </form>
        <?php else: ?>
            <p class="mb-4"><?= __('Protect your account with an authenticator app (Google Authenticator, Bitwarden, etc.).') ?></p>

            <!-- Schritt-für-Schritt-TOTP-Setup (wird per JS befüllt) -->
            <div id="totp-setup-area" class="is-hidden">
                <div id="totp-qr-container" class="has-text-centered mb-4">
                    <canvas id="totp-qr-canvas"></canvas>
                </div>
                <p class="has-text-centered is-size-7 has-text-grey mb-2">
                    <?= __('Or enter manually:') ?>
                </p>
                <p class="has-text-centered has-text-weight-semibold mb-4" id="totp-secret-text" style="font-family:monospace; letter-spacing:0.1em;"></p>

                <div class="field">
                    <label class="label" for="totp-verify-code"><?= __('Enter code from app') ?></label>
                    <div class="control">
                        <input id="totp-verify-code" type="text" class="input" inputmode="numeric"
                               pattern="[0-9]{6,8}" maxlength="8" placeholder="123456" autocomplete="one-time-code">
                    </div>
                </div>
                <div class="field is-grouped">
                    <div class="control">
                        <button id="btn-totp-enable" class="button is-primary"><?= __('Activate') ?></button>
                    </div>
                    <div class="control">
                        <button id="btn-totp-cancel" class="button is-light"><?= __('Cancel') ?></button>
                    </div>
                </div>
                <div id="totp-enable-error" class="notification is-danger is-light mt-2 is-hidden"></div>
            </div>

            <button id="btn-totp-setup" class="button is-info is-outlined">
                <?= __('Set up TOTP') ?>
            </button>
        <?php endif; ?>
    </div>

    <!-- ===================================================================
         Zwei-Faktor-Authentifizierung — WebAuthn / FIDO2
         =================================================================== -->
    <div class="box">
        <h2 class="title is-5"><?= __('Security Keys (FIDO2 / Passkeys)') ?></h2>

        <?php if (count($webAuthnKeys) > 0): ?>
            <table class="table is-fullwidth is-striped is-hoverable mb-4">
                <thead>
                    <tr>
                        <th><?= __('Name') ?></th>
                        <th><?= __('Registered') ?></th>
                        <th><?= __('Last used') ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($webAuthnKeys as $key): ?>
                        <tr>
                            <td>
                                <span class="key-name-display" data-id="<?= htmlspecialchars($key->credentialId, ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($key->name, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($key->createdAt, ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($key->lastUsedAt ?? __('Never'), ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <div class="buttons is-right">
                                    <button class="button is-small is-info is-outlined btn-key-rename"
                                            data-id="<?= htmlspecialchars($key->credentialId, ENT_QUOTES, 'UTF-8') ?>"
                                            data-name="<?= htmlspecialchars($key->name, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= __('Rename') ?>
                                    </button>
                                    <button class="button is-small is-danger is-outlined btn-key-delete"
                                            data-id="<?= htmlspecialchars($key->credentialId, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= __('Delete') ?>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="has-text-grey mb-4"><?= __('No security key registered yet.') ?></p>
        <?php endif; ?>

        <!-- Neuen Key registrieren -->
        <div id="webauthn-register-error" class="notification is-danger is-light mb-3 is-hidden"></div>
        <button id="btn-webauthn-register" class="button is-info is-outlined">
            <?= __('Add security key') ?>
        </button>
    </div>

    <!-- ===================================================================
         Erscheinungsbild & Sprache
         =================================================================== -->
    <div class="box">
        <h2 class="title is-5"><?= __('Appearance & Language') ?></h2>
        <form method="post" autocomplete="off">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="update_preferences">

            <div class="columns">
                <div class="column is-half">
                    <div class="field">
                        <label class="label" for="pref_theme"><?= __('Theme') ?></label>
                        <div class="control">
                            <div class="select is-fullwidth">
                                <select id="pref_theme" name="theme">
                                    <?php foreach ($availableThemes as $themeOption): ?>
                                        <option value="<?= htmlspecialchars($themeOption, ENT_QUOTES, 'UTF-8') ?>"
                                            <?= ($user['theme'] ?? 'default') === $themeOption ? 'selected' : '' ?>>
                                            <?= htmlspecialchars(ucfirst($themeOption), ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="column is-half">
                    <div class="field">
                        <label class="label" for="pref_locale"><?= __('Language') ?></label>
                        <div class="control">
                            <div class="select is-fullwidth">
                                <select id="pref_locale" name="locale">
                                    <?php
                                    $locales = [
                                        'en'    => 'English',
                                        'de'    => 'Deutsch',
                                        'fr'    => 'Français',
                                        'es'    => 'Español',
                                        'it'    => 'Italiano',
                                        'nl'    => 'Nederlands',
                                        'pl'    => 'Polski',
                                        'pt'    => 'Português',
                                        'sv'    => 'Svenska',
                                        'cs'    => 'Čeština',
                                        'fi'    => 'Suomi',
                                        'hu'    => 'Magyar',
                                    ];
                                    $curLocale = $user['locale'] ?? 'en';
                                    foreach ($locales as $code => $label):
                                    ?>
                                        <option value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>"
                                            <?= $curLocale === $code ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="field is-grouped is-justify-content-flex-end">
                <div class="control">
                    <button type="submit" class="button is-primary"><?= __('Save preferences') ?></button>
                </div>
            </div>
        </form>
    </div>

<?php endif; ?>

<script>
(function () {
    'use strict';

    // =========================================================================
    // Hilfsfunktionen
    // =========================================================================
    async function apiGet(url) {
        const r = await fetch(url, { headers: { Accept: 'application/json' } });
        const j = await r.json();
        if (!r.ok) throw new Error(j.error ?? 'Server-Fehler');
        return j;
    }

    async function apiPost(url, data) {
        const r = await fetch(url, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body:    JSON.stringify(data),
        });
        const j = await r.json();
        if (!r.ok) throw new Error(j.error ?? 'Server-Fehler');
        return j;
    }

    function showMsg(el, msg) {
        el.textContent = msg;
        el.classList.remove('is-hidden');
    }

    // =========================================================================
    // TOTP-Setup
    // =========================================================================
    const btnSetup   = document.getElementById('btn-totp-setup');
    const setupArea  = document.getElementById('totp-setup-area');
    const btnEnable  = document.getElementById('btn-totp-enable');
    const btnCancel  = document.getElementById('btn-totp-cancel');
    const errTotp    = document.getElementById('totp-enable-error');
    const secretText = document.getElementById('totp-secret-text');
    let totpSecret   = '';

    if (btnSetup) {
        btnSetup.addEventListener('click', async function () {
            try {
                const data = await apiGet('/totp/setup');
                totpSecret = data.secret;
                if (secretText) secretText.textContent = data.secret;

                // QR-Code via qrcode.js rendern wenn verfügbar (optional)
                const canvas = document.getElementById('totp-qr-canvas');
                if (canvas && window.QRCode) {
                    new window.QRCode(canvas, {
                        text:   data.provisioning_uri,
                        width:  200,
                        height: 200,
                    });
                } else if (canvas) {
                    // Fallback: URI direkt anzeigen
                    canvas.style.display = 'none';
                    const link = document.createElement('a');
                    link.href  = data.provisioning_uri;
                    link.textContent = '<?= __('Open OTP URI') ?>';
                    canvas.parentNode.insertBefore(link, canvas);
                }

                setupArea.classList.remove('is-hidden');
                btnSetup.classList.add('is-hidden');
            } catch (e) {
                alert('Fehler beim Setup: ' + e.message);
            }
        });
    }

    if (btnCancel) {
        btnCancel.addEventListener('click', function () {
            setupArea.classList.add('is-hidden');
            if (btnSetup) btnSetup.classList.remove('is-hidden');
        });
    }

    if (btnEnable) {
        btnEnable.addEventListener('click', async function () {
            const code = document.getElementById('totp-verify-code').value.trim();
            if (!code) { showMsg(errTotp, 'Bitte Code eingeben.'); return; }
            errTotp.classList.add('is-hidden');
            try {
                await apiPost('/totp/enable', { code, secret: totpSecret });
                window.location.reload();
            } catch (e) {
                showMsg(errTotp, e.message);
            }
        });
    }

    // TOTP deaktivieren (Plain-Form-Submit reicht, kein JS nötig)

    // =========================================================================
    // WebAuthn Key-Verwaltung
    // =========================================================================
    const btnReg  = document.getElementById('btn-webauthn-register');
    const regErr  = document.getElementById('webauthn-register-error');

    function b64uDecode(str) {
        str = str.replace(/-/g, '+').replace(/_/g, '/');
        while (str.length % 4) str += '=';
        const bin = atob(str);
        const buf = new Uint8Array(bin.length);
        for (let i = 0; i < bin.length; i++) buf[i] = bin.charCodeAt(i);
        return buf.buffer;
    }

    function b64uEncode(buf) {
        const bytes = new Uint8Array(buf);
        let str = '';
        bytes.forEach(b => str += String.fromCharCode(b));
        return btoa(str).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
    }

    if (btnReg) {
        btnReg.addEventListener('click', async function () {
            regErr.classList.add('is-hidden');
            const keyName = prompt('<?= __('Name for this key (e.g. "YubiKey 5"):') ?>');
            if (!keyName) return;

            try {
                const opts = await apiGet('/webauthn/register-options');
                opts.challenge = b64uDecode(opts.challenge);
                opts.user.id   = b64uDecode(opts.user.id);
                if (Array.isArray(opts.excludeCredentials)) {
                    opts.excludeCredentials = opts.excludeCredentials.map(c => ({
                        ...c, id: b64uDecode(c.id),
                    }));
                }
                opts.extensions = Object.assign({}, opts.extensions, { credProps: true });

                const cred = await navigator.credentials.create({ publicKey: opts });

                const credJson = JSON.stringify({
                    id:    cred.id,
                    rawId: b64uEncode(cred.rawId),
                    type:  cred.type,
                    response: {
                        attestationObject: b64uEncode(cred.response.attestationObject),
                        clientDataJSON:    b64uEncode(cred.response.clientDataJSON),
                    },
                    extensions: cred.getClientExtensionResults(),
                });

                await apiPost('/webauthn/verify-registration', { keyName, credential: credJson });
                window.location.reload();

            } catch (e) {
                showMsg(regErr, e.message ?? 'Registrierung fehlgeschlagen.');
            }
        });
    }

    // Umbenennen
    document.querySelectorAll('.btn-key-rename').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            const id   = btn.dataset.id;
            const name = prompt('<?= __('New name:') ?>', btn.dataset.name ?? '');
            if (!name || name === btn.dataset.name) return;
            try {
                await apiPost('/webauthn/rename', { credentialId: id, name });
                window.location.reload();
            } catch (e) {
                alert('Fehler: ' + e.message);
            }
        });
    });

    // Löschen
    document.querySelectorAll('.btn-key-delete').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            if (!confirm('<?= __('Really remove security key?') ?>')) return;
            const id = btn.dataset.id;
            try {
                await apiPost('/webauthn/delete', { credentialId: id });
                window.location.reload();
            } catch (e) {
                alert('Fehler: ' + e.message);
            }
        });
    });
}());
</script>

