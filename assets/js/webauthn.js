// WebAuthn Integration
class WebAuthnHelper {
    constructor() {
        this.config = {
            timeout: 60000,
            attestation: 'direct',
            userVerification: 'preferred'
        };
    }
    
    async register() {
        try {
            // 1. Hole Registration Options vom Server
            const options = await this.getRegistrationOptions();
            
            // 2. Decode Challenge
            options.challenge = this.base64ToArrayBuffer(options.challenge);
            options.user.id = this.base64ToArrayBuffer(options.user.id);
            
            if (options.excludeCredentials) {
                options.excludeCredentials = options.excludeCredentials.map(cred => ({
                    ...cred,
                    id: this.base64ToArrayBuffer(cred.id)
                }));
            }
            
            // 3. Create Credentials
            const credential = await navigator.credentials.create({
                publicKey: options
            });
            
            // 4. Prepare Response
            const response = {
                id: credential.id,
                type: credential.type,
                rawId: this.arrayBufferToBase64(credential.rawId),
                response: {
                    clientDataJSON: this.arrayBufferToBase64(
                        credential.response.clientDataJSON
                    ),
                    attestationObject: this.arrayBufferToBase64(
                        credential.response.attestationObject
                    )
                }
            };
            
            // 5. Send to Server
            return await this.verifyRegistration(response);
            
        } catch (error) {
            console.error('WebAuthn Registration Error:', error);
            throw new Error('WebAuthn Registrierung fehlgeschlagen: ' + error.message);
        }
    }
    
    async authenticate() {
        try {
            // 1. Hole Authentication Options vom Server
            const options = await this.getAuthenticationOptions();
            
            // 2. Decode Challenge
            options.challenge = this.base64ToArrayBuffer(options.challenge);
            
            if (options.allowCredentials) {
                options.allowCredentials = options.allowCredentials.map(cred => ({
                    ...cred,
                    id: this.base64ToArrayBuffer(cred.id)
                }));
            }
            
            // 3. Get Assertion
            const assertion = await navigator.credentials.get({
                publicKey: options
            });
            
            // 4. Prepare Response
            const response = {
                id: assertion.id,
                type: assertion.type,
                rawId: this.arrayBufferToBase64(assertion.rawId),
                response: {
                    clientDataJSON: this.arrayBufferToBase64(
                        assertion.response.clientDataJSON
                    ),
                    authenticatorData: this.arrayBufferToBase64(
                        assertion.response.authenticatorData
                    ),
                    signature: this.arrayBufferToBase64(
                        assertion.response.signature
                    ),
                    userHandle: assertion.response.userHandle ? 
                        this.arrayBufferToBase64(assertion.response.userHandle) : null
                }
            };
            
            // 5. Verify with Server
            return await this.verifyAuthentication(response);
            
        } catch (error) {
            console.error('WebAuthn Authentication Error:', error);
            throw new Error('WebAuthn Authentifizierung fehlgeschlagen: ' + error.message);
        }
    }
    
    // Helper Methods
    async getRegistrationOptions() {
        const response = await fetch('api/webauthn/register-options');
        if (!response.ok) {
            throw new Error('Fehler beim Abrufen der Registrierungsoptionen');
        }
        return await response.json();
    }
    
    async verifyRegistration(credential) {
        const response = await fetch('api/webauthn/verify-registration', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(credential)
        });
        
        if (!response.ok) {
            throw new Error('Registrierungsverifikation fehlgeschlagen');
        }
        
        return await response.json();
    }
    
    async getAuthenticationOptions() {
        const response = await fetch('api/webauthn/auth-options');
        if (!response.ok) {
            throw new Error('Fehler beim Abrufen der Authentifizierungsoptionen');
        }
        return await response.json();
    }
    
    async verifyAuthentication(assertion) {
        const response = await fetch('api/webauthn/verify-authentication', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(assertion)
        });
        
        if (!response.ok) {
            throw new Error('Authentifizierungsverifikation fehlgeschlagen');
        }
        
        return await response.json();
    }
    
    // Encoding Utilities
    base64ToArrayBuffer(base64) {
        const binary = window.atob(base64.replace(/-/g, '+').replace(/_/g, '/'));
        const bytes = new Uint8Array(binary.length);
        for (let i = 0; i < binary.length; i++) {
            bytes[i] = binary.charCodeAt(i);
        }
        return bytes.buffer;
    }
    
    arrayBufferToBase64(buffer) {
        const bytes = new Uint8Array(buffer);
        let binary = '';
        for (let i = 0; i < bytes.byteLength; i++) {
            binary += String.fromCharCode(bytes[i]);
        }
        return window.btoa(binary)
            .replace(/\+/g, '-')
            .replace(/\//g, '_')
            .replace(/=/g, '');
    }
}

// Initialize
const webAuthn = new WebAuthnHelper();

// Event Handlers
document.addEventListener('DOMContentLoaded', () => {
    const registerButton = document.querySelector('#register-webauthn');
    if (registerButton) {
        registerButton.addEventListener('click', async () => {
            try {
                registerButton.classList.add('is-loading');
                await webAuthn.register();
                showNotification('WebAuthn Registrierung erfolgreich!', 'is-success');
                location.reload();
            } catch (error) {
                showNotification(error.message, 'is-danger');
            } finally {
                registerButton.classList.remove('is-loading');
            }
        });
    }
    
    const loginButton = document.querySelector('#login-webauthn');
    if (loginButton) {
        loginButton.addEventListener('click', async () => {
            try {
                loginButton.classList.add('is-loading');
                const result = await webAuthn.authenticate();
                if (result.success) {
                    location.href = '?route=dashboard';
                }
            } catch (error) {
                showNotification(error.message, 'is-danger');
            } finally {
                loginButton.classList.remove('is-loading');
            }
        });
    }
});
