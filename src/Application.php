<?php

declare(strict_types=1);

namespace StaticFormMailer;

use StaticFormMailer\Config\ConfigLoader;
use StaticFormMailer\Config\ConfigValidator;

class Application
{
    public function __construct(
        private array $config
    ) {
    }

    public static function boot(string $configPath): self
    {
        $loader = new ConfigLoader();
        $config = $loader->load($configPath);

        $validator = new ConfigValidator();
        $validator->validate($config);

        return new self($config);
    }
    
    public function getConfig(): array
    {
        return $this->config;
    }
}
