<?php

declare(strict_types=1);

namespace StaticFormMailer\Tests\Unit;

use PHPUnit\Framework\TestCase;
use StaticFormMailer\RateLimit\FileRateLimiter;

final class FileRateLimiterTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/static-form-mailer-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $path) {
            unlink($path);
        }
        rmdir($this->directory);
    }

    public function testLimitsHashedIdentifierWithinWindow(): void
    {
        $limiter = new FileRateLimiter($this->directory, 2, 60, str_repeat('k', 32));

        self::assertTrue($limiter->consume('192.0.2.1', 1000)->allowed);
        self::assertTrue($limiter->consume('192.0.2.1', 1001)->allowed);
        $blocked = $limiter->consume('192.0.2.1', 1002);

        self::assertFalse($blocked->allowed);
        self::assertSame(58, $blocked->retryAfter);
        self::assertNotSame('192.0.2.1', $blocked->identifierHash);

        $storedState = implode('', array_map(
            static fn (string $path): string => (string) file_get_contents($path),
            glob($this->directory . '/*.json') ?: []
        ));
        self::assertStringNotContainsString('192.0.2.1', $storedState);
    }

    public function testStartsNewWindowAfterExpiry(): void
    {
        $limiter = new FileRateLimiter($this->directory, 1, 60, str_repeat('k', 32));

        self::assertTrue($limiter->consume('192.0.2.1', 1000)->allowed);
        self::assertFalse($limiter->consume('192.0.2.1', 1001)->allowed);
        self::assertTrue($limiter->consume('192.0.2.1', 1060)->allowed);
    }
}
