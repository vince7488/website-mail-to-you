<?php

declare(strict_types=1);

namespace StaticFormMailer\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use StaticFormMailer\Config\ConfigValidator;

final class ConfigValidatorTest extends TestCase
{
    public function testExampleConfigurationIsAccepted(): void
    {
        $config = require dirname(__DIR__, 2) . '/config/config.example.php';

        (new ConfigValidator())->validate($config);

        self::addToAssertionCount(1);
    }

    public function testHoneypotCannotCollideWithPublicField(): void
    {
        $config = require dirname(__DIR__, 2) . '/config/config.example.php';
        $config['forms']['contact']['honeypot_field'] = 'email';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('invalid honeypot field');

        (new ConfigValidator())->validate($config);
    }

    public function testReplyToMustReferenceConfiguredEmailField(): void
    {
        $config = require dirname(__DIR__, 2) . '/config/config.example.php';
        $config['forms']['contact']['reply_to_field'] = 'name';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('configured email field');

        (new ConfigValidator())->validate($config);
    }
}
