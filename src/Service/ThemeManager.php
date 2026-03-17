<?php

declare(strict_types=1);

namespace App\Service;

/**
 * ThemeManager – verwaltet das aktive Theme des DeSEC Managers.
 *
 * Themes liegen unter themes/{name}/ und enthalten eine theme.json Metadatendatei.
 * Templates können durch theme-eigene Varianten in themes/{name}/templates/ überschrieben werden.
 * CSS und JS werden über CDN-Links und lokale Dateien eingebunden.
 *
 * Eigene Themes können außerhalb des Projekts über config['theme']['custom_path'] eingebunden werden.
 */
class ThemeManager
{
    private string $themeName;
    private string $projectRoot;

    /** @var array<string, mixed>|null */
    private ?array $cachedMeta = null;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config, string $projectRoot)
    {
        $this->projectRoot = rtrim($projectRoot, '/');
        $this->themeName   = (string) ($config['theme']['name'] ?? 'default');
    }

    public function getThemeName(): string
    {
        return $this->themeName;
    }

    // ─── Interne Helpers ────────────────────────────────────────────────────

    /**
     * Gibt den absoluten Dateisystem-Pfad zum Theme-Verzeichnis zurück.
     * Unterstützt Custom-Themes über config['theme']['custom_path'].
     */
    private function getThemePath(): ?string
    {
        $path = $this->projectRoot . '/themes/' . $this->themeName;
        return is_dir($path) ? $path : null;
    }

    /**
     * Gibt den URL-Basispfad (relativ zur Web-Root) für Theme-Assets zurück.
     */
    private function getThemeWebPath(): string
    {
        return 'themes/' . $this->themeName;
    }

    /**
     * Liest und cached theme.json.
     * @return array<string, mixed>
     */
    private function getMeta(): array
    {
        if ($this->cachedMeta !== null) {
            return $this->cachedMeta;
        }

        $themePath = $this->getThemePath();
        if ($themePath === null) {
            $this->cachedMeta = [];
            return $this->cachedMeta;
        }

        $jsonFile = $themePath . '/theme.json';
        if (!file_exists($jsonFile)) {
            $this->cachedMeta = [];
            return $this->cachedMeta;
        }

        $content = file_get_contents($jsonFile);
        $decoded = $content !== false ? json_decode($content, true) : null;
        $this->cachedMeta = is_array($decoded) ? $decoded : [];
        return $this->cachedMeta;
    }

    // ─── CSS ────────────────────────────────────────────────────────────────

    /**
     * Externe CSS-URLs (z. B. CDN) aus theme.json.
     * @return string[]
     */
    public function getExternalCss(): array
    {
        $meta  = $this->getMeta();
        $links = $meta['external_css'] ?? [];
        return is_array($links) ? $links : [];
    }

    /**
     * Lokale CSS-Dateien, relativ zur Web-Root des Projekts.
     * @return string[]
     */
    public function getLocalCss(): array
    {
        $meta  = $this->getMeta();
        $local = $meta['local_css'] ?? [];
        if (!is_array($local)) {
            return [];
        }
        $base = $this->getThemeWebPath();
        return array_map(static fn(string $f): string => $base . '/' . $f, $local);
    }

    // ─── JavaScript ─────────────────────────────────────────────────────────

    /**
     * Externe JS-URLs (CDN).
     * @return string[]
     */
    public function getExternalJs(): array
    {
        $meta  = $this->getMeta();
        $links = $meta['external_js'] ?? [];
        return is_array($links) ? $links : [];
    }

    /**
     * Lokale JS-Dateien, relativ zur Web-Root des Projekts.
     * @return string[]
     */
    public function getLocalJs(): array
    {
        $meta  = $this->getMeta();
        $local = $meta['local_js'] ?? [];
        if (!is_array($local)) {
            return [];
        }
        $base = $this->getThemeWebPath();
        return array_map(static fn(string $f): string => $base . '/' . $f, $local);
    }

    // ─── Dark Mode ──────────────────────────────────────────────────────────

    /**
     * Gibt true zurück, wenn das Theme einen Dark-Mode-Toggle unterstützt.
     */
    public function supportsDarkMode(): bool
    {
        $meta = $this->getMeta();
        return (bool) ($meta['supports_dark_mode'] ?? false);
    }

    // ─── Template-Auflösung ─────────────────────────────────────────────────

    /**
     * Löst einen Template-Bezeichner zu einem absoluten Dateipfad auf.
     *
     * Reihenfolge:
     *   1. themes/{name}/templates/{template}.php  (Theme-Override)
     *   2. templates/{template}.php                (Projekt-Standard)
     *
     * @param string $template z. B. "dashboard/index", "auth/login"
     */
    public function resolveTemplate(string $template): string
    {
        $themePath = $this->getThemePath();
        if ($themePath !== null) {
            $themeTemplate = $themePath . '/templates/' . $template . '.php';
            if (file_exists($themeTemplate)) {
                return $themeTemplate;
            }
        }
        return $this->projectRoot . '/templates/' . $template . '.php';
    }

    // ─── Theme-Info ─────────────────────────────────────────────────────────

    /**
     * Gibt alle verfügbaren Theme-Namen zurück (Unterverzeichnisse von themes/).
     * @return string[]
     */
    public function getAvailableThemes(): array
    {
        $themesDir = $this->projectRoot . '/themes';
        if (!is_dir($themesDir)) {
            return [];
        }
        $themes = [];
        foreach (scandir($themesDir) ?: [] as $entry) {
            if ($entry[0] === '.') {
                continue;
            }
            if (is_dir($themesDir . '/' . $entry)) {
                $themes[] = $entry;
            }
        }
        return $themes;
    }

    /**
     * Gibt den menschenlesbaren Namen des aktuellen Themes zurück.
     */
    public function getDisplayName(): string
    {
        $meta = $this->getMeta();
        return (string) ($meta['name'] ?? $this->themeName);
    }
}
