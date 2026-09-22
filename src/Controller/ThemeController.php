<?php

declare(strict_types=1);

namespace DKAnnual\Controller;

use DKAnnual\Http\Request;
use DKAnnual\Http\Response;
use DKAnnual\Repository\AppSettingRepository;

final class ThemeController
{
    public function __construct(private readonly AppSettingRepository $settings)
    {
    }

    public function css(Request $request): Response
    {
        $primary = (string) $this->settings->get('ui.primary_color', '#315efb');
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $primary) !== 1) {
            $primary = '#315efb';
        }

        $dark = $this->mix($primary, '#000000', 0.18);
        $soft = $this->mix($primary, '#ffffff', 0.89);

        $css = sprintf(
            ":root{--primary:%s;--primary-dark:%s;--primary-soft:%s;}\n",
            $primary,
            $dark,
            $soft,
        );

        return new Response($css, 200, [
            'Content-Type' => 'text/css; charset=utf-8',
            'Cache-Control' => 'no-store, max-age=0',
        ]);
    }

    private function mix(string $left, string $right, float $rightWeight): string
    {
        $leftRgb = $this->rgb($left);
        $rightRgb = $this->rgb($right);
        $weight = max(0.0, min(1.0, $rightWeight));

        return sprintf(
            '#%02x%02x%02x',
            (int) round($leftRgb[0] * (1 - $weight) + $rightRgb[0] * $weight),
            (int) round($leftRgb[1] * (1 - $weight) + $rightRgb[1] * $weight),
            (int) round($leftRgb[2] * (1 - $weight) + $rightRgb[2] * $weight),
        );
    }

    /** @return array{0:int,1:int,2:int} */
    private function rgb(string $hex): array
    {
        return [
            hexdec(substr($hex, 1, 2)),
            hexdec(substr($hex, 3, 2)),
            hexdec(substr($hex, 5, 2)),
        ];
    }
}
