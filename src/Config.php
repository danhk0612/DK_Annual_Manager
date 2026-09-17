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
