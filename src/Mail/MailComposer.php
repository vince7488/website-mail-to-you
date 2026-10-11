<?php

declare(strict_types=1);

namespace StaticFormMailer\Mail;

use DateTimeImmutable;
use StaticFormMailer\Form\Submission;

final class MailComposer
{
    /**
     * @param array<string, mixed> $formConfig
     */
    public function compose(
        string $formId,
        array $formConfig,
        Submission $submission,
        string $requestId,
        DateTimeImmutable $receivedAt
    ): MailMessage {
        $plainLines = ['A new form submission was received.', ''];
        $htmlRows = [];

        foreach ($formConfig['fields'] ?? [] as $fieldName => $fieldConfig) {
            $value = $submission->value($fieldName);
            if ($value === '') {
                continue;
            }

            $label = (string) ($fieldConfig['label'] ?? $fieldName);
            $plainLines[] = "{$label}:";
            $plainLines[] = $value;
            $plainLines[] = '';

            $escapedLabel = htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $escapedValue = nl2br(
                htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                false
            );
            $htmlRows[] = "<dt><strong>{$escapedLabel}</strong></dt><dd>{$escapedValue}</dd>";
        }

        $received = $receivedAt->format(DATE_ATOM);
        $plainLines[] = "Request ID: {$requestId}";
        $plainLines[] = "Received: {$received}";
        $plainLines[] = "Form: {$formId}";

        $escapedRequestId = htmlspecialchars($requestId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $escapedReceived = htmlspecialchars($received, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $escapedFormId = htmlspecialchars($formId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $htmlBody = '<p>A new form submission was received.</p>'
            . '<dl>' . implode('', $htmlRows) . '</dl>'
            . '<hr><p>'
            . "Request ID: {$escapedRequestId}<br>"
            . "Received: {$escapedReceived}<br>"
            . "Form: {$escapedFormId}"
            . '</p>';

        $replyToField = (string) ($formConfig['reply_to_field'] ?? '');

        return new MailMessage(
            (string) ($formConfig['email_subject'] ?? 'New form submission'),
            array_values($formConfig['recipients'] ?? []),
            array_values($formConfig['cc'] ?? []),
            array_values($formConfig['bcc'] ?? []),
            $submission->value($replyToField),
            $submission->value('name'),
            $htmlBody,
            implode("\n", $plainLines)
        );
    }
}
