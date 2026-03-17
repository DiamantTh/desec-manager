<?php

namespace App\Controller;

use App\Service\ThemeManager;

abstract class AbstractPageController extends BaseController
{
    protected ThemeManager $theme;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->theme = new ThemeManager($config, dirname(__DIR__, 2));
    }

    /**
     * Rendert ein Template. Löst es über ThemeManager auf (3 Ebenen).
     * Im Template-Scope stehen zur Verfügung:
     *   – alle Variablen aus $data
     *   – $theme  → ThemeManager-Instanz (für Metadaten im Template)
     *
     * @param array<string, mixed> $data
     */
    protected function renderTemplate(string $template, array $data = []): void
    {
        $templatePath = $this->theme->resolveTemplate($template);
        if (!file_exists($templatePath)) {
            http_response_code(500);
            echo "Template nicht gefunden: {$template}";
            return;
        }

        $data['theme'] = $this->theme;
        extract($data, EXTR_SKIP);
        require $templatePath;
    }

    /**
     * Bindet einen optionalen Partial ein (Extension-Point / Hook).
     * Wenn kein Partial-File gefunden wird, geschieht nichts – kein Fehler.
     *
     * Aufruf aus einem Template heraus (da require innerhalb dieser Klasse läuft,
     * zeigt $this auf den Controller):
     *   <?php $this->renderPartial('domains/extra_columns', ['row' => $domain]) ?>
     *
     * @param array<string, mixed> $data  Variablen die dem Partial übergeben werden
     */
    protected function renderPartial(string $partial, array $data = []): void
    {
        $path = $this->theme->resolvePartial($partial);
        if ($path === null) {
            return;
        }
        $data['theme'] = $this->theme;
        extract($data, EXTR_SKIP);
        require $path;
    }
}
