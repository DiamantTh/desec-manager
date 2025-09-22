<?php

namespace App\Controller;

abstract class AbstractPageController extends BaseController
{
    protected function renderTemplate(string $template, array $data = []): void
    {
        $templatePath = __DIR__ . '/../../templates/' . $template . '.php';
        if (!file_exists($templatePath)) {
            http_response_code(500);
            echo "Template nicht gefunden: {$template}";
            return;
        }

        extract($data, EXTR_SKIP);
        require $templatePath;
    }
}
