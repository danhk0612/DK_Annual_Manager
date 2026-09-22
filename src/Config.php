<?php

declare(strict_types=1);

namespace DKAnnual;

use RuntimeException;

final class Config
{
    /** @var array<string, mixed> */
    private array $values;

    public function __construct(string $configPath)
    {
        if (!is_file($configPath)) {
            throw new RuntimeException(sprintf(
                'Configuration file not found: %s. Copy config/config.example.php to config/config.php first.',
                $configPath
            ));
        }

        $values = require $configPath;
        if (!is_array($values)) {
            throw new RuntimeException('Configuration file must return an array.');
        }

        $this->values = $values;
    }

    public function set(string $path, mixed $value): void
    {
        $segments = explode('.', $path);
        $cursor =& $this->values;

        foreach ($segments as $index => $segment) {
            if ($index === array_key_last($segments)) {
                $cursor[$segment] = $value;
                return;
            }

            if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) {
                $cursor[$segment] = [];
            }

            $cursor =& $cursor[$segment];
        }
    }

    public function get(string $path, mixed $default = null): mixed
    {
        $value = $this->values;

        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }
}
