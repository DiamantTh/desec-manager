<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Kryptographisch sicherer Passwort-Generator.
 *
 * Zwei Modi:
 *   random()     → zufällige Zeichenfolge aus erweitertem ASCII-Alphabet
 *   passphrase() → XKCD-style Passphrase (Wort-Wort-Wort-Wort)
 *
 * Verwendet ausschließlich random_int() (CSPRNG) — kein mt_rand().
 *
 * Wortlisten werden aus Dateien in data/wordlists/{lang}.txt geladen.
 * Quellen:
 *   en  – EFF Large Wordlist (7776 Wörter) — https://www.eff.org/deeplinks/2016/07/new-wordlists-random-passphrases
 *   de  – dys2p wordlists-de (7776 Wörter, kein Umlaut) — https://github.com/dys2p/wordlists-de
 *   fr  – BIP-39 French, ASCII-gefiltert (1682 Wörter) — https://github.com/bitcoin/bips/blob/master/bip-0039/french.txt
 *   es  – BIP-39 Spanish, ASCII-gefiltert (1714 Wörter) — https://github.com/bitcoin/bips/blob/master/bip-0039/spanish.txt
 *   it  – BIP-39 Italian (2048 Wörter) — https://github.com/bitcoin/bips/blob/master/bip-0039/italian.txt
 *   pt  – BIP-39 Portuguese (2048 Wörter) — https://github.com/bitcoin/bips/blob/master/bip-0039/portuguese.txt
 *   cs  – BIP-39 Czech (2048 Wörter) — https://github.com/bitcoin/bips/blob/master/bip-0039/czech.txt
 *
 * Entropie (EN/DE 7776 Wörter = 12,9 bit/Wort):
 *   4 Wörter: ~51 bit
 *   5 Wörter: ~64 bit  (empfohlen)
 *   6 Wörter: ~77 bit
 *
 * Entropie (FR/ES/IT/PT/CS ~2048 Wörter = 11 bit/Wort):
 *   5 Wörter: ~55 bit  (empfohlen)
 *   6 Wörter: ~66 bit
 */
final class PasswordGenerator
{
    // -------------------------------------------------------------------------
    // Zeichensätze für Zufalls-Passwörter
    // -------------------------------------------------------------------------
    private const ALPHA   = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    private const DIGITS  = '0123456789';
    private const SYMBOLS = '!@#$%&*-+=?';

    private const WORDLIST_DIR = __DIR__ . '/../../data/wordlists';

    private const SUPPORTED_LANGS = ['en', 'de', 'fr', 'es', 'it', 'pt', 'cs'];

    /** @var string[] */
    private array $wordlistCache = [];

    public function __construct(private readonly string $lang = 'en') {}

    // -------------------------------------------------------------------------
    // VERALTET: Embedded WORDLIST — wird nicht mehr verwendet.
    // Nur zur Rückwärtskompatibilität falls jemand direkt darauf zugreift.
    // -------------------------------------------------------------------------
    /** @deprecated Nicht mehr verwendet; Wortliste wird aus data/wordlists/{lang}.txt geladen */
    private const WORDLIST = [
        'acre', 'acid', 'aged', 'arch', 'area', 'army', 'axle', 'baby', 'back',
        'ball', 'band', 'bank', 'barn', 'base', 'bath', 'bean', 'beat', 'beef',
        'beer', 'bell', 'bill', 'bird', 'bite', 'blow', 'blue', 'bolt', 'bond',
        'bone', 'book', 'boss', 'bowl', 'brew', 'bulk', 'burn', 'bush', 'cage',
        'cake', 'call', 'calm', 'cane', 'cape', 'card', 'care', 'case', 'cash',
        'cave', 'cell', 'chip', 'city', 'clay', 'club', 'coal', 'coil', 'cold',
        'comb', 'cone', 'cool', 'core', 'corn', 'cost', 'cove', 'crab', 'crew',
        'crop', 'cube', 'cure', 'dark', 'data', 'date', 'dawn', 'dead', 'deal',
        'debt', 'deer', 'desk', 'diet', 'dome', 'door', 'dove', 'down', 'dusk',
        'dust', 'duty', 'each', 'earn', 'edge', 'face', 'fact', 'fail', 'fall',
        'fame', 'farm', 'feel', 'film', 'find', 'fine', 'fire', 'firm', 'fish',
        'flat', 'flow', 'foam', 'fold', 'font', 'form', 'fort', 'four', 'free',
        'fuel', 'full', 'fund', 'fuse', 'gale', 'gaze', 'gear', 'glow', 'glue',
        'goal', 'gold', 'golf', 'gone', 'gray', 'grip', 'grow', 'gulf', 'gust',
        'hail', 'hair', 'half', 'hall', 'hand', 'hang', 'hard', 'harm', 'harp',
        'hawk', 'heal', 'heap', 'heat', 'heel', 'help', 'high', 'hill', 'hire',
        'hold', 'hole', 'home', 'hood', 'hook', 'horn', 'hull', 'hunt', 'hurt',
        'idea', 'idle', 'jest', 'join', 'keep', 'kick', 'kind', 'king', 'knee',
        'lack', 'lake', 'lamp', 'land', 'lane', 'lash', 'last', 'late', 'lawn',
        'lead', 'leaf', 'lean', 'leap', 'left', 'lend', 'like', 'line', 'list',
        'live', 'load', 'lock', 'loft', 'lore', 'loss', 'loud', 'lure', 'meal',
        'meet', 'melt', 'mild', 'milk', 'mill', 'mine', 'mint', 'mist', 'mode',
        'mole', 'moon', 'moor', 'more', 'move', 'mule', 'nail', 'name', 'navy',
        'near', 'neck', 'need', 'nest', 'next', 'nine', 'noon', 'nose', 'note',
        'once', 'only', 'open', 'oven', 'over', 'pale', 'pine', 'plan', 'play',
        'plot', 'plow', 'plum', 'pool', 'pose', 'post', 'pray', 'prey', 'prop',
        'pull', 'pump', 'pure', 'push', 'rail', 'rain', 'ramp', 'read', 'reed',
        'reel', 'rely', 'rent', 'rest', 'rice', 'rich', 'ride', 'rise', 'risk',
        'road', 'roam', 'rock', 'role', 'roll', 'roof', 'root', 'rope', 'rose',
        'rung', 'rush', 'rust', 'safe', 'sail', 'salt', 'sand', 'save', 'seal',
        'seed', 'self', 'sell', 'shed', 'ship', 'shoe', 'shop', 'show', 'side',
        'sign', 'silk', 'sing', 'sink', 'slim', 'slip', 'slow', 'snap', 'snow',
        'soap', 'soft', 'soil', 'soul', 'soup', 'span', 'spin', 'spot', 'star',
        'stay', 'stem', 'step', 'stir', 'suit', 'tail', 'tale', 'tall', 'tank',
        'teal', 'team', 'tear', 'tell', 'tile', 'time', 'toll', 'tomb', 'tone',
        'tool', 'toss', 'trap', 'tray', 'tree', 'trim', 'true', 'tube', 'tuft',
        'tuna', 'turf', 'twin', 'type', 'vale', 'vane', 'vast', 'veil', 'vein',
        'view', 'vine', 'void', 'volt', 'wake', 'wall', 'want', 'warm', 'wave',
        'weak', 'well', 'wide', 'wild', 'will', 'wind', 'wine', 'wing', 'wire',
        'wise', 'wish', 'wolf', 'wood', 'wool', 'word', 'work', 'wrap', 'yard',
        // 2-Silber ergänzen (höhere Entropie)
        'acorn', 'actor', 'after', 'again', 'agent', 'ahead', 'alarm', 'album',
        'alone', 'along', 'altar', 'amber', 'ample', 'angel', 'ankle', 'apple',
        'arena', 'argue', 'arise', 'array', 'atlas', 'bacon', 'badge', 'baker',
        'basic', 'basin', 'batch', 'beach', 'beard', 'below', 'bench', 'birch',
        'black', 'blade', 'bland', 'blast', 'blink', 'blaze', 'blend', 'bliss',
        'block', 'blood', 'bloom', 'board', 'boost', 'bound', 'boxer', 'brain',
        'brand', 'brave', 'bread', 'break', 'breed', 'brief', 'brine', 'brook',
        'brown', 'brush', 'burst', 'cabin', 'cable', 'camel', 'candy', 'cedar',
        'chain', 'chalk', 'charm', 'chess', 'chest', 'child', 'civil', 'claim',
        'clash', 'class', 'clean', 'clear', 'clerk', 'cliff', 'clock', 'cloth',
        'cloud', 'coach', 'coast', 'cobra', 'coral', 'count', 'court', 'cover',
        'craft', 'crane', 'creek', 'crisp', 'cross', 'crowd', 'crown', 'crust',
        'curve', 'cycle', 'daisy', 'dance', 'depth', 'disco', 'ditch', 'dodge',
        'draft', 'drama', 'drink', 'drive', 'drops', 'drums', 'eagle', 'earth',
        'elbow', 'ember', 'event', 'extra', 'faint', 'faith', 'fancy', 'feast',
        'fence', 'ferry', 'fever', 'field', 'flame', 'flask', 'flesh', 'float',
        'flock', 'flood', 'floor', 'flour', 'flute', 'forge', 'forth', 'found',
        'frame', 'fresh', 'front', 'frost', 'fruit', 'ghost', 'giant', 'glass',
        'globe', 'gloom', 'glove', 'grace', 'grade', 'grain', 'grape', 'grasp',
        'grass', 'graze', 'green', 'grief', 'grind', 'grove', 'guard', 'guild',
        'hasty', 'haven', 'hazel', 'heart', 'heavy', 'hedge', 'herbs', 'heron',
        'hinge', 'holly', 'honor', 'horse', 'hotel', 'house', 'hover', 'hyena',
        'inlet', 'ivory', 'jewel', 'judge', 'kayak', 'label', 'lance', 'latch',
        'layer', 'lemon', 'level', 'light', 'linen', 'liver', 'logic', 'lotus',
        'lunar', 'magic', 'maple', 'march', 'marsh', 'medal', 'merit', 'metal',
        'minor', 'moist', 'money', 'month', 'moral', 'mossy', 'mount', 'mouse',
        'mouth', 'mulch', 'music', 'nerve', 'night', 'noble', 'north', 'novel',
        'ocean', 'offer', 'other', 'otter', 'outer', 'paint', 'panel', 'patch',
        'pause', 'peach', 'petal', 'phase', 'piano', 'pilot', 'place', 'plain',
        'plant', 'plump', 'plush', 'polar', 'poppy', 'porch', 'power', 'press',
        'prime', 'prism', 'prose', 'pulse', 'punch', 'purse', 'radar', 'ranch',
        'rapid', 'raven', 'reach', 'realm', 'rebel', 'regal', 'reign', 'relay',
        'ridge', 'rivet', 'robin', 'rocky', 'rouge', 'round', 'royal', 'rugby',
        'ruler', 'rural', 'scent', 'scout', 'shade', 'shaft', 'shape', 'shark',
        'sharp', 'shelf', 'shine', 'shrub', 'sight', 'skill', 'slate', 'sleep',
        'slice', 'slope', 'smart', 'smell', 'smoke', 'snail', 'snake', 'solid',
        'sonic', 'spark', 'spawn', 'split', 'stain', 'stamp', 'steam', 'steep',
        'stiff', 'still', 'sting', 'stock', 'stomp', 'stool', 'storm', 'stout',
        'straw', 'stern', 'stone', 'strand', 'strap', 'stray', 'surge', 'swamp',
        'sweet', 'swift', 'swirl', 'table', 'talon', 'taste', 'tempo', 'tense',
        'theme', 'thick', 'thing', 'think', 'thorn', 'three', 'thumb', 'tiger',
        'tinge', 'token', 'tonic', 'topaz', 'torch', 'total', 'touch', 'tough',
        'tower', 'track', 'trade', 'trail', 'train', 'trait', 'trend', 'trick',
        'troop', 'trout', 'truce', 'trunk', 'trust', 'tooth', 'vapor', 'vigor',
        'viola', 'viper', 'upper', 'urban', 'usher', 'value', 'vivid', 'vocal',
        'waltz', 'watch', 'water', 'weave', 'wedge', 'weird', 'wheat', 'wheel',
        'witch', 'woman', 'world', 'wreck', 'wrist', 'young', 'youth', 'zebra',
    ];

    // =========================================================================
    // Öffentliche API
    // =========================================================================

    /**
     * Erzeugt ein zufälliges Passwort.
     *
     * @param int  $length  Zeichenanzahl (empfohlen: ≥ 16)
     * @param bool $symbols Sonderzeichen einschließen (Standard: true)
     */
    public function random(int $length = 20, bool $symbols = true): string
    {
        $charset = self::ALPHA . self::DIGITS . ($symbols ? self::SYMBOLS : '');
        $max     = strlen($charset) - 1;
        $result  = '';

        for ($i = 0; $i < $length; $i++) {
            $result .= $charset[random_int(0, $max)];
        }

        return $result;
    }

    /**
     * Erzeugt eine XKCD-style Passphrase aus mehreren zufälligen Wörtern.
     *
     * Die Wortliste wird aus data/wordlists/{lang}.txt geladen (Sprache via Konstruktor).
     * Fallback auf 'en' wenn die gewünschte Sprachdatei nicht existiert.
     *
     * @param int    $wordCount  Anzahl der Wörter (Standard: 5)
     * @param string $separator  Trennzeichen (Standard: -)
     */
    public function passphrase(int $wordCount = 5, string $separator = '-'): string
    {
        $list  = $this->getWordlist();
        $max   = count($list) - 1;
        $words = [];

        for ($i = 0; $i < $wordCount; $i++) {
            $words[] = $list[random_int(0, $max)];
        }

        return implode($separator, $words);
    }

    /**
     * Gibt mehrere Vorschläge pro Typ zurück.
     *
     * @return array<string, list<string>>  Keys: 'random', 'passphrase'
     */
    public function suggestions(int $count = 5): array
    {
        $result = ['random' => [], 'passphrase' => []];

        for ($i = 0; $i < $count; $i++) {
            $result['random'][]     = $this->random(20, true);
            $result['passphrase'][] = $this->passphrase(5, '-');
        }

        return $result;
    }

    /**
     * Aktuell geladene Sprache (nach Fallback-Auflösung).
     */
    public function getActiveLang(): string
    {
        $path = self::WORDLIST_DIR . '/' . $this->lang . '.txt';
        return file_exists($path) ? $this->lang : 'en';
    }

    /**
     * Anzahl der Wörter in der aktiven Wortliste (für Entropie-Berechnung).
     */
    public function wordlistSize(): int
    {
        return count($this->getWordlist());
    }

    // =========================================================================
    // Hilfsmethoden (privat)
    // =========================================================================

    /**
     * Lädt die Wortliste für die konfigurierte Sprache (lazy, gecacht).
     *
     * Unterstützte Sprachen: en, de, fr, es, it, pt, cs
     * Fallback: en
     *
     * @return string[]
     */
    private function getWordlist(): array
    {
        if ($this->wordlistCache !== []) {
            return $this->wordlistCache;
        }

        $lang = in_array($this->lang, self::SUPPORTED_LANGS, true) ? $this->lang : 'en';
        $path = self::WORDLIST_DIR . '/' . $lang . '.txt';

        if (!file_exists($path)) {
            $path = self::WORDLIST_DIR . '/en.txt';
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $this->wordlistCache = $lines !== false ? $lines : [];

        return $this->wordlistCache;
    }
}
