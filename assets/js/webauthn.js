/**
 * WebAuthn-Integration für DeSEC Manager
 *
 * Sicherheitsanforderungen:
 *   - userVerification: 'required'  → PIN oder Biometrie zwingend
 *   - residentKey via Server-Optionen → Passkey-Modus
 *   - credProps-Extension → Browser meldet ob UV wirklich konfiguriert wurde
 *
 * Der Server (WebAuthnService) verwendet webauthn-lib v5, das seine Optionen
 * als Standard-WebAuthn-JSON serialisiert (base64url-kodierte Binary-Felder).
 */
class WebAuthnHelper {

    // =========================================================================
    // Öffentliche Methoden
    // =========================================================================

    /**
     * Registriert einen neuen FIDO2-Key / Passkey.
     *
     * Zeigt vorher einen Dialog für den Key-Namen (Nutzer-Beschriftung).
     * Fordert den Browser auf, die credProps-Extension zu liefern,
     * damit der Server weiß ob UV wirklich eingerichtet wurde.
     *
     * @returns {Promise<Object>}  Server-Antwort nach erfolgreicher Registrierung
     */
    async register() {
        const keyName = await this.promptKeyName();
        if (!keyName) {
            throw new Error('Registrierung abgebrochen: kein Name vergeben.');
        }

        const options = await this.fetchJson('/webauthn/register-options');
        this.decodeCreationOptions(options);

        // credProps-Extension: Browser meldet residentKey-Status zurück
        options.extensions = Object.assign({}, options.extensions, { credProps: true });

        const credential = await navigator.credentials.create({ publicKey: options });
        const credJson   = this.encodeAttestationResponse(credential);

        return await this.postJson('/webauthn/verify-registration', {
            keyName:    keyName,
            credential: credJson,
        });
    }

    /**
     * Authentifiziert den Nutzer mit einem vorhandenen FIDO2-Key.
     *
     * Zeigt ggf. die Namen der erlaubten Keys an (aus server-seitigen Metadaten).
     *
     * @returns {Promise<Object>}  Server-Antwort nach erfolgreicher Authentifizierung
     */
    async authenticate() {
        const options = await this.fetchJson('/webauthn/auth-options');
        this.decodeRequestOptions(options);

        const assertion = await navigator.credentials.get({ publicKey: options });
        const assertJson = this.encodeAssertionResponse(assertion);

        return await this.postJson('/webauthn/verify-authentication', { credential: assertJson });
    }

    // =========================================================================
    // Options-Dekodierung (base64url → ArrayBuffer für Browser-API)
    // =========================================================================

    /** @param {PublicKeyCredentialCreationOptions} options */
    decodeCreationOptions(options) {
        options.challenge = this.b64uDecode(options.challenge);
        options.user.id   = this.b64uDecode(options.user.id);

        if (Array.isArray(options.excludeCredentials)) {
            options.excludeCredentials = options.excludeCredentials.map(c => ({
                ...c,
                id: this.b64uDecode(c.id),
            }));
        }
    }

    /** @param {PublicKeyCredentialRequestOptions} options */
    decodeRequestOptions(options) {
        options.challenge = this.b64uDecode(options.challenge);

        if (Array.isArray(options.allowCredentials)) {
            options.allowCredentials = options.allowCredentials.map(c => ({
                ...c,
                id: this.b64uDecode(c.id),
                // transports wird vom Server bereits korrekt befüllt
            }));
        }
    }

    // =========================================================================
    // Antwort-Enkodierung (ArrayBuffer → base64url für Server-JSON)
    // =========================================================================

    /** @param {PublicKeyCredential} credential */
    encodeAttestationResponse(credential) {
        const r = credential.response;
        const transports = (typeof r.getTransports === 'function') ? r.getTransports() : [];
        const clientExts = credential.getClientExtensionResults ? credential.getClientExtensionResults() : {};

        return {
            id:   credential.id,
            type: credential.type,
            rawId: this.b64uEncode(credential.rawId),
            response: {
                clientDataJSON:    this.b64uEncode(r.clientDataJSON),
                attestationObject: this.b64uEncode(r.attestationObject),
                transports:        transports,
            },
            clientExtensionResults: clientExts,
        };
    }

    /** @param {PublicKeyCredential} assertion */
    encodeAssertionResponse(assertion) {
        const r = assertion.response;
        return {
            id:   assertion.id,
            type: assertion.type,
            rawId: this.b64uEncode(assertion.rawId),
            response: {
                clientDataJSON:    this.b64uEncode(r.clientDataJSON),
                authenticatorData: this.b64uEncode(r.authenticatorData),
                signature:         this.b64uEncode(r.signature),
                userHandle:        r.userHandle ? this.b64uEncode(r.userHandle) : null,
            },
            clientExtensionResults: {},
        };
    }

    // =========================================================================
    // UX-Hilfsmethoden
    // =========================================================================

    /**
     * Fragt den Nutzer nach einem benutzerdefinierten Key-Namen.
     * Fällt auf einen sinnvollen Standard zurück, wenn keiner eingegeben wird.
     *
     * @returns {Promise<string|null>}  Name oder null wenn abgebrochen
     */
    async promptKeyName() {
        const defaultName = this.guessDefaultKeyName();
        const name = window.prompt(
            'Namen für diesen Sicherheitsschlüssel vergeben:\n' +
            '(z. B. "YubiKey 5C" oder "iPhone Face ID")',
            defaultName
        );

        if (name === null) return null;          // Nutzer hat abgebrochen
        return name.trim() || defaultName;
    }

    /**
     * Heuristik für einen sinnvollen Standard-Key-Namen.
     * Nutzt User-Agent-Informationen falls verfügbar.
     */
    guessDefaultKeyName() {
        const ua = navigator.userAgent;
        if (/iPhone|iPad/.test(ua))  return 'iPhone / iPad';
        if (/Android/.test(ua))      return 'Android-Gerät';
        if (/Mac/.test(ua))          return 'Mac (Touch ID)';
        if (/Windows/.test(ua))      return 'Windows Hello';
        return 'Sicherheitsschlüssel';
    }

    // =========================================================================
    // HTTP-Hilfsmethoden
    // =========================================================================

    async fetchJson(url) {
        const res = await fetch(url, { credentials: 'same-origin' });
        if (!res.ok) {
            const err = await res.json().catch(() => ({ error: res.statusText }));
            throw new Error(err.error || `HTTP ${res.status}`);
        }
        return res.json();
    }

    async postJson(url, body) {
        const res = await fetch(url, {
            method:      'POST',
            credentials: 'same-origin',
            headers:     { 'Content-Type': 'application/json' },
            body:        JSON.stringify(body),
        });
        if (!res.ok) {
            const err = await res.json().catch(() => ({ error: res.statusText }));
            throw new Error(err.error || `HTTP ${res.status}`);
        }
        return res.json();
    }

    // =========================================================================
    // Base64url-Kodierung / -Dekodierung
    // =========================================================================

    /** base64url-String → ArrayBuffer */
    b64uDecode(str) {
        const padded = str.replace(/-/g, '+').replace(/_/g, '/')
            + '==='.slice((str.length + 3) % 4);
        const binary = atob(padded);
        const bytes  = new Uint8Array(binary.length);
        for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
        return bytes.buffer;
    }

    /** ArrayBuffer → base64url-String (ohne Padding) */
    b64uEncode(buffer) {
        const bytes  = new Uint8Array(buffer instanceof ArrayBuffer ? buffer : buffer);
        let   binary = '';
        for (let i = 0; i < bytes.byteLength; i++) binary += String.fromCharCode(bytes[i]);
        return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
    }
}

// =========================================================================
// Singleton + Event-Handler
// =========================================================================

const webAuthn = new WebAuthnHelper();

document.addEventListener('DOMContentLoaded', () => {

    // --- Registrierung ---
    const registerButton = document.querySelector('#register-webauthn');
    if (registerButton) {
        registerButton.addEventListener('click', async () => {
            registerButton.classList.add('is-loading');
            registerButton.disabled = true;
            try {
                await webAuthn.register();
                showNotification('Sicherheitsschlüssel erfolgreich registriert!', 'is-success');
                setTimeout(() => location.reload(), 1200);
            } catch (error) {
                if (error.name === 'NotAllowedError') {
                    showNotification('Vorgang abgebrochen oder Zeitüberschreitung.', 'is-warning');
                } else if (error.name === 'InvalidStateError') {
                    showNotification('Dieser Schlüssel ist bereits registriert.', 'is-warning');
                } else {
                    showNotification(error.message, 'is-danger');
                }
            } finally {
                registerButton.classList.remove('is-loading');
                registerButton.disabled = false;
            }
        });
    }

    // --- Authentifizierung ---
    const loginButton = document.querySelector('#login-webauthn');
    if (loginButton) {
        loginButton.addEventListener('click', async () => {
            loginButton.classList.add('is-loading');
            loginButton.disabled = true;
            try {
                const result = await webAuthn.authenticate();
                if (result.success) {
                    location.href = result.redirectUrl || '/dashboard';
                } else {
                    showNotification(result.error || 'Authentifizierung fehlgeschlagen.', 'is-danger');
                }
            } catch (error) {
                if (error.name === 'NotAllowedError') {
                    showNotification('Vorgang abgebrochen oder Zeitüberschreitung.', 'is-warning');
                } else {
                    showNotification(error.message, 'is-danger');
                }
            } finally {
                loginButton.classList.remove('is-loading');
                loginButton.disabled = false;
            }
        });
    }
});

