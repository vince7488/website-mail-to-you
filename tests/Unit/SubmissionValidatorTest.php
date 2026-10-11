<?php

declare(strict_types=1);

namespace StaticFormMailer\Tests\Unit;

use PHPUnit\Framework\TestCase;
use StaticFormMailer\Form\SubmissionValidator;
use StaticFormMailer\Form\ValidationException;

final class SubmissionValidatorTest extends TestCase
{
    public function testValidatesAndNormalizesSubmission(): void
    {
        $submission = (new SubmissionValidator())->validate([
            'name' => '  Jane Example  ',
            'email' => 'jane@example.net',
            'message' => "Hello\r\nthere!",
            'confirm_email' => '',
        ], $this->formConfig());

        self::assertSame('Jane Example', $submission->value('name'));
        self::assertSame("Hello\nthere!", $submission->value('message'));
    }

    public function testRejectsNestedAndUnknownFields(): void
    {
        try {
            (new SubmissionValidator())->validate([
                'name' => ['Jane'],
                'email' => 'not-an-email',
                'message' => 'Long enough message',
                'unexpected' => 'value',
            ], $this->formConfig());
            self::fail('Expected invalid fields to be rejected.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('_form', $exception->fieldErrors);
            self::assertArrayHasKey('name', $exception->fieldErrors);
            self::assertArrayHasKey('email', $exception->fieldErrors);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formConfig(): array
    {
        return [
            'reply_to_field' => 'email',
            'honeypot_field' => 'confirm_email',
            'timing' => ['field' => '_started_at'],
            'fields' => [
                'name' => [
                    'type' => 'string',
                    'required' => true,
                    'minimum_length' => 2,
                    'maximum_length' => 120,
                ],
                'email' => [
                    'type' => 'email',
                    'required' => true,
                    'maximum_length' => 254,
                ],
                'message' => [
                    'type' => 'string',
                    'required' => true,
                    'minimum_length' => 10,
                    'maximum_length' => 5000,
                ],
            ],
        ];
    }
}
