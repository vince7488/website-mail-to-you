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

        $localPath = preg_replace('/\.php$/', '.local.php', $path);
        if ($localPath !== null && file_exists($localPath)) {
            $localConfig = require $localPath;
            if (is_array($localConfig)) {
                $config = array_replace_recursive($config, $localConfig);
            }
        }

        return $config;
    }
}
