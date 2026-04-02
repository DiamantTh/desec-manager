<?php

declare(strict_types=1);

namespace App\Service;

/**
 * ThemeManager – verwaltet das aktive Theme des DeSEC Managers.
 *
 * Themes liegen unter themes/{name}/ und enthalten eine theme.json Metadatendatei.
 * Templates can be overridden by theme-specific variants in themes/{name}/templates/.
 * CSS and JS are included via CDN links and local files.
 *
 * Custom themes can be included outside the project via config['theme']['custom_path'].
 */
class ThemeManager
{
    private string $themeName;
    private string $projectRoot;
    private string $appName;

    /** @var array<string, mixed>|null */
    private ?array $cachedMeta = null;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config, string $projectRoot)
    {
        $this->projectRoot = rtrim($projectRoot, '/');
        $this->themeName   = (string) ($config['theme']['name'] ?? 'default');
        $this->appName     = (string) ($config['application']['name'] ?? 'DeSEC Manager');
    }

    public function getAppName(): string
    {
        return $this->appName;
    }

    public function getThemeName(): string
    {
        return $this->themeName;
    }

    /**
     * Überschreibt das Theme (z. B. für benutzerspezifische Voreinstellungen).
     * Existiert das gewählte Theme-Verzeichnis nicht, bleibt das bisherige aktiv.
     */
    public function setThemeName(string $name): void
    {
        $name = (string) preg_replace('/[^a-z0-9_-]/i', '', $name);
        if ($name === '') {
            return;
        }
        $candidate = $this->projectRoot . '/themes/' . $name;
        if (is_dir($candidate)) {
            $this->themeName   = $name;
            $this->cachedMeta  = null;   // Cache invalidieren
        }
    }

    // ─── Interne Helpers ────────────────────────────────────────────────────

    /**
     * Returns the absolute file system path to the theme directory.
     * Supports custom themes via config['theme']['custom_path'].
     */
    private function getThemePath(): ?string
    {
        $path = $this->projectRoot . '/themes/' . $this->themeName;
        return is_dir($path) ? $path : null;
    }

    /**
     * Returns the URL base path (relative to the web root) for theme assets.
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

    /**
     * Svelte-Bundle-Dateien (type="module"), relativ zur Web-Root.
     * Werden aus theme.json[svelte_bundles] gelesen.
     * @return string[]
     */
    public function getSvelteBundles(): array
    {
        $meta   = $this->getMeta();
        $bundles = $meta['svelte_bundles'] ?? [];
        if (!is_array($bundles)) {
            return [];
        }
        $base = $this->getThemeWebPath();
        return array_map(static fn(string $f): string => $base . '/' . $f, $bundles);
    }

    // ─── Dark Mode ──────────────────────────────────────────────────────────

    /**
     * Returns true if the theme supports a dark mode toggle.
     */
    public function supportsDarkMode(): bool
    {
        $meta = $this->getMeta();
        return (bool) ($meta['supports_dark_mode'] ?? false);
    }

    // ─── Template resolution ─────────────────────────────────────────────────

    /**
     * Resolves a template identifier to an absolute file path.
     *
     * 3-level resolution order:
     *   1. themes/{active}/templates/{template}.php   – Custom theme (skipped if active theme = default)
     *   2. themes/default/templates/{template}.php   – Default-Theme (Basis zum Anpassen per Kopie)
     *   3. templates/{template}.php                  – Core-Fallback (nicht anfassen)
     *
     * @param string $template z. B. "dashboard/index", "auth/login"
     */
    public function resolveTemplate(string $template): string
    {
        // Level 1: active custom theme (skipped when it is the default theme)
        if ($this->themeName !== 'default') {
            $themePath = $this->getThemePath();
            if ($themePath !== null) {
                $override = $themePath . '/templates/' . $template . '.php';
                if (file_exists($override)) {
                    return $override;
                }
            }
        }

        // Ebene 2: Default-Theme (editierbare Basis)
        $defaultTpl = $this->projectRoot . '/themes/default/templates/' . $template . '.php';
        if (file_exists($defaultTpl)) {
            return $defaultTpl;
        }

        // Ebene 3: Core-Fallback (templates/ – nur lesen, nicht bearbeiten)
        return $this->projectRoot . '/templates/' . $template . '.php';
    }

    /**
     * Resolves an optional template partial.
     * Returns null if no partial is found → extension point is simply skipped.
     *
     * Partials liegen in themes/{name}/partials/ und dienen als Hook-Punkte,
     * allowing templates to be extended without core changes.
     *
     * 2-level resolution:
     *   1. themes/{aktiv}/partials/{partial}.php   – Theme-eigener Hook
     *   2. themes/default/partials/{partial}.php   – Default-Hook (Fallback)
     *
     * Beispiel im Template:
     *   <?php $this->renderPartial('domains/extra_columns', ['row' => $domain]) ?>
     *
     * @param string $partial z. B. "domains/extra_columns", "records/custom_fields"
     * @return string|null  Absoluter Pfad oder null wenn nicht vorhanden
     */
    public function resolvePartial(string $partial): ?string
    {
        if ($this->themeName !== 'default') {
            $themePath = $this->getThemePath();
            if ($themePath !== null) {
                $path = $themePath . '/partials/' . $partial . '.php';
                if (file_exists($path)) {
                    return $path;
                }
            }
        }

        $defaultPath = $this->projectRoot . '/themes/default/partials/' . $partial . '.php';
        if (file_exists($defaultPath)) {
            return $defaultPath;
        }

        return null;
    }

    // ─── Theme-Info ─────────────────────────────────────────────────────────

    /**
     * Returns all available theme names (subdirectories of themes/).
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
     * Returns the human-readable name of the current theme.
     */
    public function getDisplayName(): string
    {
        $meta = $this->getMeta();
        return (string) ($meta['name'] ?? $this->themeName);
    }
}
