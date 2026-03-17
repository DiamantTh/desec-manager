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

        extract($data, EXTR_SKIP);
        require $templatePath;
    }
}
