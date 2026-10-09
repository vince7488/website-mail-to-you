<?php

declare(strict_types=1);

namespace StaticFormMailer\Config;

use RuntimeException;

class ConfigLoader
{
    /**
     * @param string $path Path to the config file
     * @return array<string, mixed>
     */
    public function load(string $path): array
    {
        if (!file_exists($path)) {
            throw new RuntimeException("Configuration file not found at: {$path}");
        }

        $config = require $path;

        if (!is_array($config)) {
            throw new RuntimeException("Configuration file must return an array.");
        }

        return $config;
    }
}
