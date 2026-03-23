<?php
/** @var ?string $error */
$error ??= null;
?>
<div class="columns is-centered">
    <div class="column is-one-third-desktop is-half-tablet">
        <div class="box" id="webauthn-mfa-box">
            <h1 class="title is-4 has-text-centered mb-4">
                <?= __('Use Security Key') ?>
            </h1>
            <p class="has-text-centered has-text-grey mb-5">
                <?= __('Insert your security key and click Sign in.') ?>
            </p>

            <div id="webauthn-error" class="notification is-danger is-light is-hidden"></div>

            <?php if ($error): ?>
                <div class="notification is-warning is-light">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <div class="has-text-centered">
                <button id="btn-webauthn" class="button is-primary is-medium">
                    <span class="icon"><i class="fas fa-key"></i></span>
                    <span><?= __('Sign in with security key') ?></span>
                </button>
            </div>

            <div class="has-text-centered mt-4">
                <a href="/auth/login" class="is-size-7 has-text-grey">
                    <?= __('Back to login') ?>
                </a>
            </div>
        </div>
    </div>
</div>

<script>
(async function () {
    const btn     = document.getElementById('btn-webauthn');
    const errBox  = document.getElementById('webauthn-error');

    function showError(msg) {
        errBox.textContent = msg;
        errBox.classList.remove('is-hidden');
        btn.disabled = false;
        btn.querySelector('span:last-child').textContent = '<?= __('Retry') ?>';
    }

    async function fetchJson(url) {
        const r = await fetch(url, { headers: { 'Accept': 'application/json' } });
        if (!r.ok) throw new Error(await r.text());
        return r.json();
    }

    async function postJson(url, data) {
        const r = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(data),
        });
        const json = await r.json();
        if (!r.ok) throw new Error(json.error ?? 'Unbekannter Fehler');
        return json;
    }

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

    btn.addEventListener('click', async function () {
        btn.disabled = true;
        errBox.classList.add('is-hidden');
        btn.querySelector('span:last-child').textContent = '<?= __('Waiting for key…') ?>';

        try {
            const options = await fetchJson('/webauthn/auth-options');

            // Base64url → ArrayBuffer
            options.challenge = b64uDecode(options.challenge);
            if (Array.isArray(options.allowCredentials)) {
                options.allowCredentials = options.allowCredentials.map(c => ({
                    ...c,
                    id: b64uDecode(c.id),
                }));
            }

            const assertion = await navigator.credentials.get({ publicKey: options });

            const credJson = JSON.stringify({
                id:    assertion.id,
                rawId: b64uEncode(assertion.rawId),
                type:  assertion.type,
                response: {
                    authenticatorData: b64uEncode(assertion.response.authenticatorData),
                    clientDataJSON:    b64uEncode(assertion.response.clientDataJSON),
                    signature:         b64uEncode(assertion.response.signature),
                    userHandle:        assertion.response.userHandle
                        ? b64uEncode(assertion.response.userHandle)
                        : null,
                },
            });

            await postJson('/webauthn/verify-authentication', { credential: credJson });

            // Login erfolgreich → Dashboard
            window.location.href = '/dashboard';

        } catch (e) {
            if (e.name === 'NotAllowedError') {
                showError('Authentifizierung abgebrochen oder Timeout.');
            } else {
                showError(e.message ?? 'Ein unbekannter Fehler ist aufgetreten.');
            }
        }
    });

    // Automatisch starten
    btn.click();
}());
</script>
