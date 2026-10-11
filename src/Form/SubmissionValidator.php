<?php

declare(strict_types=1);

namespace StaticFormMailer\Form;

final class SubmissionValidator
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $formConfig
     */
    public function validate(array $payload, array $formConfig): Submission
    {
        $fieldConfigs = $formConfig['fields'] ?? [];
        $allowedFields = array_keys($fieldConfigs);
        $allowedFields[] = (string) ($formConfig['honeypot_field'] ?? '');
        $allowedFields[] = (string) ($formConfig['timing']['field'] ?? '');
        $allowedFields = array_filter($allowedFields, static fn (string $name): bool => $name !== '');

        $errors = [];
        foreach (array_keys($payload) as $fieldName) {
            if (!is_string($fieldName) || !in_array($fieldName, $allowedFields, true)) {
                $errors['_form'] = 'The submission contains an unexpected field.';
                break;
            }
        }

        $validatedFields = [];
        foreach ($fieldConfigs as $fieldName => $fieldConfig) {
            $required = ($fieldConfig['required'] ?? false) === true;
            $rawValue = $payload[$fieldName] ?? null;

            if ($rawValue === null) {
                if ($required) {
                    $errors[$fieldName] = 'This field is required.';
                }
                $validatedFields[$fieldName] = '';
                continue;
            }

            if (!is_string($rawValue)) {
                $errors[$fieldName] = 'This field must be a string.';
                $validatedFields[$fieldName] = '';
                continue;
            }

            if (!mb_check_encoding($rawValue, 'UTF-8')) {
                $errors[$fieldName] = 'This field must contain valid UTF-8 text.';
                $validatedFields[$fieldName] = '';
                continue;
            }

            $value = trim(str_replace(["\r\n", "\r"], "\n", $rawValue));
            $validatedFields[$fieldName] = $value;

            if ($required && $value === '') {
                $errors[$fieldName] = 'This field is required.';
                continue;
            }

            if ($value === '') {
                continue;
            }

            $length = mb_strlen($value, 'UTF-8');
            $minimumLength = $fieldConfig['minimum_length'] ?? null;
            $maximumLength = $fieldConfig['maximum_length'] ?? null;

            if (is_int($minimumLength) && $length < $minimumLength) {
                $errors[$fieldName] = "Enter at least {$minimumLength} characters.";
                continue;
            }

            if (is_int($maximumLength) && $length > $maximumLength) {
                $errors[$fieldName] = "Enter no more than {$maximumLength} characters.";
                continue;
            }

            if (($fieldConfig['type'] ?? 'string') === 'email') {
                if (preg_match('/[\r\n]/', $value) || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$fieldName] = 'Enter a valid email address.';
                }
            }
        }

        $replyToField = (string) ($formConfig['reply_to_field'] ?? '');
        if ($replyToField !== '' && isset($validatedFields['name'])) {
            if (preg_match('/[\r\n]/', $validatedFields['name'])) {
                $errors['name'] = 'Enter a name without line breaks.';
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return new Submission($validatedFields);
    }
}
