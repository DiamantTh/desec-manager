/**
 * deSEC Manager — Default Theme JS
 * Dark-Mode Toggle: setzt data-theme="dark"|"light" am <html>-Element.
 * Das Theme wird ausschließlich manuell gesteuert (kein OS-Follow).
 * Preference wird in localStorage gespeichert.
 */
(function () {
  'use strict';

  const STORAGE_KEY = 'dsec-theme';
  const DARK        = 'dark';
  const LIGHT       = 'light';

  /**
   * Gibt die gespeicherte Präferenz zurück.
   * Kein OS-Fallback — Standard ist immer Light Mode.
   * @returns {'dark'|'light'}
   */
  function getPreference() {
    const stored = localStorage.getItem(STORAGE_KEY);
    if (stored === DARK || stored === LIGHT) return stored;
    return LIGHT;
  }

  /**
   * Wendet das Theme an und aktualisiert den Toggle-Button.
   * @param {'dark'|'light'} theme
   */
  function applyTheme(theme) {
    document.documentElement.dataset.theme = theme;
    localStorage.setItem(STORAGE_KEY, theme);

    const btn  = document.getElementById('dsec-theme-toggle');
    const icon = document.getElementById('dsec-theme-icon');
    if (btn)  btn.setAttribute('aria-label', theme === DARK ? 'Light Mode aktivieren' : 'Dark Mode aktivieren');
    if (icon) icon.textContent = theme === DARK ? '☀️' : '🌙';
  }

  // Sofort anwenden (vor dem Rendern, verhindert Flash)
  applyTheme(getPreference());

  document.addEventListener('DOMContentLoaded', function () {
    // Toggle-Button registrieren
    const btn = document.getElementById('dsec-theme-toggle');
    if (btn) {
      btn.addEventListener('click', function () {
        const current = document.documentElement.dataset.theme === DARK ? DARK : LIGHT;
        applyTheme(current === DARK ? LIGHT : DARK);
      });
    }
  });
}());
