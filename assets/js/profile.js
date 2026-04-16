/**
 * profile.js — Profil-Seite Passwort-Stärkemeter
 *
 * Erfordert pwtools.bundle.js (window.zxcvbn, window.PwGen).
 */

document.addEventListener('DOMContentLoaded', () => {

    // Stärkemeter für neue-Passwort-Felder
    document.querySelectorAll('input[type="password"][data-strength-meter]').forEach(field => {
        const meterId = field.dataset.strengthMeter;
        const meter   = document.getElementById(meterId);
        if (!meter) return;

        field.addEventListener('input', () => {
            const pw = field.value;
            if (!pw || typeof window.zxcvbn !== 'function') {
                meter.style.display = 'none';
                return;
            }
            const result = window.zxcvbn(pw);
            const score  = result.score;
            const colors = ['#e53e3e', '#ed8936', '#ecc94b', '#48bb78', '#38a169'];
            const labels = ['Sehr schwach', 'Schwach', 'Mittel', 'Stark', 'Sehr stark'];

            meter.style.display = 'block';
            meter.querySelector('.pw-strength-bar').style.width      = ((score + 1) * 20) + '%';
            meter.querySelector('.pw-strength-bar').style.background = colors[score];
            meter.querySelector('.pw-strength-label').textContent    = labels[score];

            const hint = meter.querySelector('.pw-strength-hint');
            if (hint) {
                const suggestions = result.feedback?.suggestions ?? [];
                hint.textContent = suggestions.join(' ');
            }
        });
    });
});
