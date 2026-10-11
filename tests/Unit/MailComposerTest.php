<?php

declare(strict_types=1);

namespace StaticFormMailer\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use StaticFormMailer\Form\Submission;
use StaticFormMailer\Mail\MailComposer;

final class MailComposerTest extends TestCase
{
    public function testBuildsEscapedHtmlAndCompletePlainText(): void
    {
        $message = (new MailComposer())->compose(
            'contact',
            [
                'email_subject' => 'Configured subject',
                'reply_to_field' => 'email',
                'recipients' => [['address' => 'team@example.com']],
                'cc' => [],
                'bcc' => [],
                'fields' => [
                    'name' => ['label' => 'Name'],
                    'email' => ['label' => 'Email'],
                    'message' => ['label' => 'Message'],
                ],
            ],
            new Submission([
                'name' => 'Jane <script>',
                'email' => 'jane@example.net',
                'message' => "First line\nSecond & line",
            ]),
            'abc12345',
            new DateTimeImmutable('2026-10-10T12:00:00+00:00')
        );

        self::assertSame('Configured subject', $message->subject);
        self::assertSame('jane@example.net', $message->replyToAddress);
        self::assertStringNotContainsString('<script>', $message->htmlBody);
        self::assertStringContainsString('Jane &lt;script&gt;', $message->htmlBody);
        self::assertStringContainsString("First line\nSecond & line", $message->textBody);
        self::assertStringContainsString('Request ID: abc12345', $message->textBody);
    }
}
