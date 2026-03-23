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
     * Renders a template. Resolves it via ThemeManager (3 levels).
     * Available in template scope:
     *   – alle Variablen aus $data
     *   – $theme  → ThemeManager instance (for template metadata)
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
     * Includes an optional partial (extension point / hook).
     * If no partial file is found, nothing happens – no error.
     *
     * Called from within a template (since require runs inside this class,
     * so $this refers to the controller):
     *   <?php $this->renderPartial('domains/extra_columns', ['row' => $domain]) ?>
     *
     * @param array<string, mixed> $data  Variables passed to the partial
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
