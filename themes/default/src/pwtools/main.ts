/**
 * pwtools.bundle.js — Passwort-Tools für Admin und Profil
 *
 * Exponiert global:
 *   window.zxcvbn(password)  → zxcvbn-Stärkebewertung
 *   window.PwGen.random(length?, symbols?)     → Zufallspasswort
 *   window.PwGen.passphrase(wordCount?, sep?)  → Passphrase
 *   window.PwGen.suggestions(count?)           → Zusammenstellung
 *
 * Wortliste: EFF Large Wordlist (7772 Wörter, ASCII-clean)
 * Quelle: https://www.eff.org/deeplinks/2016/07/new-wordlists-random-passphrases
 * Browser-UI nutzt immer Englisch; für andere Sprachen → config.toml passphrase_lang (CLI/Server)
 */

import zxcvbn from 'zxcvbn';
import { WORDLIST_EN } from './wordlist-en';

// Zxcvbn global bereitstellen (für Strength-Meter in Inline-JS)
(window as any).zxcvbn = zxcvbn;

// ---------------------------------------------------------------------------
// Zeichensätze
// ---------------------------------------------------------------------------
const ALPHA   = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
const DIGITS  = '0123456789';
const SYMBOLS = '!@#$%&*-+=?';

/** Kryptographisch sicherer zufälliger Integer [0, max] */
function randomInt(max: number): number {
    const buf = new Uint32Array(1);
    // Rejection sampling, um Modulo-Bias zu vermeiden
    const limit = 0x100000000 - (0x100000000 % (max + 1));
    let val: number;
    do {
        crypto.getRandomValues(buf);
        val = buf[0];
    } while (val >= limit);
    return val % (max + 1);
}

/** Erzeugt ein zufälliges Passwort */
function generateRandom(length = 20, symbols = true): string {
    const charset = ALPHA + DIGITS + (symbols ? SYMBOLS : '');
    let result = '';
    for (let i = 0; i < length; i++) {
        result += charset[randomInt(charset.length - 1)];
    }
    return result;
}

/** Erzeugt eine Passphrase aus zufälligen Wörtern */
function generatePassphrase(wordCount = 5, separator = '-'): string {
    const words: string[] = [];
    for (let i = 0; i < wordCount; i++) {
        words.push(WORDLIST_EN[randomInt(WORDLIST_EN.length - 1)]);
    }
    return words.join(separator);
}

/** Gibt mehrere Vorschläge zurück */
function suggestions(count = 5): { random: string[]; passphrase: string[] } {
    const result = { random: [] as string[], passphrase: [] as string[] };
    for (let i = 0; i < count; i++) {
        result.random.push(generateRandom(20, true));
        result.passphrase.push(generatePassphrase(5, '-'));
    }
    return result;
}

// Global bereitstellen
(window as any).PwGen = { random: generateRandom, passphrase: generatePassphrase, suggestions };
