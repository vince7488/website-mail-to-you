<?php

declare(strict_types=1);

namespace StaticFormMailer\Tests\Unit;

use PHPUnit\Framework\TestCase;
use StaticFormMailer\Form\SpamGuard;

final class SpamGuardTest extends TestCase
{
    private const NOW = 1_800_000_000;

    public function testAllowsEmptyHoneypotAndReasonableTiming(): void
    {
        $result = (new SpamGuard())->inspect([
            'confirm_email' => '',
            '_started_at' => self::NOW - 10,
        ], $this->formConfig(), self::NOW);

        self::assertNull($result);
    }

    public function testFlagsFilledHoneypot(): void
    {
        $result = (new SpamGuard())->inspect([
            'confirm_email' => 'bot@example.net',
        ], $this->formConfig(), self::NOW);

        self::assertSame('honeypot', $result);
    }

    public function testFlagsImplausiblyFastSubmission(): void
    {
        $result = (new SpamGuard())->inspect([
            'confirm_email' => '',
            '_started_at' => self::NOW - 1,
        ], $this->formConfig(), self::NOW);

        self::assertSame('timing', $result);
    }

    /**
     * @return array<string, mixed>
     */
    private function formConfig(): array
    {
        return [
            'honeypot_field' => 'confirm_email',
            'timing' => [
                'field' => '_started_at',
                'required' => false,
                'minimum_seconds' => 3,
                'maximum_seconds' => 7200,
            ],
        ];
    }
}
