<?php

declare(strict_types=1);

namespace StaticFormMailer\Mail;

final readonly class MailMessage
{
    /**
     * @param list<array{address: string, name?: string}> $recipients
     * @param list<array{address: string, name?: string}> $cc
     * @param list<array{address: string, name?: string}> $bcc
     */
    public function __construct(
        public string $subject,
        public array $recipients,
        public array $cc,
        public array $bcc,
        public string $replyToAddress,
        public string $replyToName,
        public string $htmlBody,
        public string $textBody
    ) {
    }
}
