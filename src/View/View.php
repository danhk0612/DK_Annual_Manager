<?php

declare(strict_types=1);

namespace DKAnnual\View;

use DKAnnual\Config;
use DKAnnual\Repository\AppSettingRepository;
use RuntimeException;

final class View
{
    public function __construct(
        private readonly string $templatePath,
        private readonly AppSettingRepository $settings,
        private readonly Config $config,
    ) {
    }

    /** @param array<string, mixed> $data */
    public function render(string $template, array $data = []): string
    {
        $templateFile = $this->templatePath . '/' . $template . '.php';
        $layoutFile = $this->templatePath . '/layout.php';

        if (!is_file($templateFile) || !is_file($layoutFile)) {
            throw new RuntimeException('View template not found.');
        }

        $appName = trim((string) $this->settings->get(
            'ui.app_name',
            (string) $this->config->get('app.name', 'DK Annual Manager'),
        ));
        if ($appName === '') {
            $appName = 'DK Annual Manager';
        }

        $theme = (string) $this->settings->get('ui.theme', 'system');
        if (!in_array($theme, ['light', 'dark', 'system'], true)) {
            $theme = 'system';
        }

        $primaryColor = (string) $this->settings->get('ui.primary_color', '#315efb');
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $primaryColor) !== 1) {
            $primaryColor = '#315efb';
        }

        $logoPath = trim((string) $this->settings->get('ui.logo_path', ''));
        $currentPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        if (!is_string($currentPath) || $currentPath === '') {
            $currentPath = '/';
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
