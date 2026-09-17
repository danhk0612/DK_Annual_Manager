<?php

declare(strict_types=1);

namespace DKAnnual\View;

use RuntimeException;

final class View
{
    public function __construct(private readonly string $templatePath)
    {
    }

    /** @param array<string, mixed> $data */
    public function render(string $template, array $data = []): string
    {
        $templateFile = $this->templatePath . '/' . $template . '.php';
        $layoutFile = $this->templatePath . '/layout.php';

        if (!is_file($templateFile) || !is_file($layoutFile)) {
            throw new RuntimeException('View template not found.');
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $templateFile;
        $content = (string) ob_get_clean();

        ob_start();
        require $layoutFile;
        return (string) ob_get_clean();
    }
}
