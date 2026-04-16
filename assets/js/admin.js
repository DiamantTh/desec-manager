/**
 * admin.js — Admin-Panel Passwort-Helfer
 *
 * Erfordert pwtools.bundle.js (window.zxcvbn, window.PwGen).
 *
 * Funktionen:
 *  - Stärkemeter für jedes Passwort-Feld mit data-strength-meter
 *  - Generator-Panel für Felder mit data-gen-target
 */

document.addEventListener('DOMContentLoaded', () => {

    // -----------------------------------------------------------------------
    // Stärkemeter — alle Felder mit data-strength-meter="<meter-id>"
    // -----------------------------------------------------------------------
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
            const score  = result.score; // 0–4
            const colors = ['#e53e3e', '#ed8936', '#ecc94b', '#48bb78', '#38a169'];
            const labels = ['Sehr schwach', 'Schwach', 'Mittel', 'Stark', 'Sehr stark'];

            meter.style.display = 'block';
            meter.querySelector('.pw-strength-bar').style.width  = ((score + 1) * 20) + '%';
            meter.querySelector('.pw-strength-bar').style.background = colors[score];
            meter.querySelector('.pw-strength-label').textContent = labels[score];

            const hint = meter.querySelector('.pw-strength-hint');
            if (hint) {
                const suggestions = result.feedback?.suggestions ?? [];
                hint.textContent = suggestions.join(' ');
            }
        });
    });

    // -----------------------------------------------------------------------
    // Generator-Panel — Buttons mit data-gen-target="<field-id>"
    // -----------------------------------------------------------------------
    document.querySelectorAll('button[data-gen-target]').forEach(btn => {
        btn.addEventListener('click', e => {
            e.preventDefault();
            const targetId = btn.dataset.genTarget;
            const field    = document.getElementById(targetId);
            const panelId  = btn.dataset.genPanel;
            const panel    = panelId ? document.getElementById(panelId) : null;
            if (!field || typeof window.PwGen === 'undefined') return;

            // Panel ein-/ausblenden
            if (panel) {
                const isOpen = panel.style.display !== 'none';
                panel.style.display = isOpen ? 'none' : 'block';
                if (!isOpen) renderSuggestions(panel, field);
                return;
            }

            // Ohne Panel: direkt ein Zufallspasswort einfügen
            field.value = window.PwGen.random(20, true);
            field.dispatchEvent(new Event('input', { bubbles: true }));
        });
    });

    // -----------------------------------------------------------------------
    // Vorschlussliste rendern
    // -----------------------------------------------------------------------
    function renderSuggestions(panel, targetField) {
        const list = panel.querySelector('.pw-suggestions');
        if (!list) return;
        list.innerHTML = '';

        const s = window.PwGen.suggestions(3); // 3 je Typ
        const allSuggestions = [
            ...s.random.map(pw => ({ pw, type: 'Zufällig' })),
            ...s.passphrase.map(pw => ({ pw, type: 'Passphrase' })),
        ];

        allSuggestions.forEach(({ pw, type }) => {
            const li   = document.createElement('li');
            li.className = 'pw-suggestion-item';

            const badge = document.createElement('span');
            badge.className = 'pw-suggestion-type';
            badge.textContent = type;

            const code  = document.createElement('code');
            code.className = 'pw-suggestion-value';
            code.textContent = pw;

            const useBtn = document.createElement('button');
            useBtn.type = 'button';
            useBtn.className = 'button is-small is-info is-light pw-suggestion-use';
            useBtn.textContent = 'Übernehmen';
            useBtn.addEventListener('click', () => {
                targetField.value = pw;
                targetField.dispatchEvent(new Event('input', { bubbles: true }));
                panel.style.display = 'none';
            });

            const refreshBtn = document.createElement('button');
            refreshBtn.type = 'button';
            refreshBtn.className = 'button is-small is-light pw-suggestion-refresh';
            refreshBtn.title = 'Neue Vorschläge';
            refreshBtn.textContent = '↻';
            refreshBtn.addEventListener('click', () => renderSuggestions(panel, targetField));

            li.append(badge, code, useBtn);
            list.appendChild(li);

            // Refresh-Button nur einmal am Ende
            if (allSuggestions.indexOf(allSuggestions.find(x => x.pw === pw)) === allSuggestions.length - 1) {
                const refreshLi = document.createElement('li');
                refreshLi.append(refreshBtn);
                list.appendChild(refreshLi);
            }
        });

        // Refresh-Button unten
        const refreshLi = document.createElement('li');
        refreshLi.style.marginTop = '0.5rem';
        const refreshBtn = document.createElement('button');
        refreshBtn.type = 'button';
        refreshBtn.className = 'button is-small is-light';
        refreshBtn.textContent = '↻ Neue Vorschläge';
        refreshBtn.addEventListener('click', () => renderSuggestions(panel, targetField));
        refreshLi.appendChild(refreshBtn);
        list.appendChild(refreshLi);
    }
});

