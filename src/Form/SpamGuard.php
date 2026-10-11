<?php

declare(strict_types=1);

namespace StaticFormMailer\Form;

final class SpamGuard
{
    /**
     * Returns the triggered rule name, or null when the submission may proceed.
     *
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $formConfig
     */
    public function inspect(array $payload, array $formConfig, ?int $now = null): ?string
    {
        $honeypotField = (string) ($formConfig['honeypot_field'] ?? '');
        if ($honeypotField !== '' && array_key_exists($honeypotField, $payload)) {
            $honeypotValue = $payload[$honeypotField];
            if (!is_string($honeypotValue) || trim($honeypotValue) !== '') {
                return 'honeypot';
            }
        }

        $timing = $formConfig['timing'] ?? [];
        $timingField = (string) ($timing['field'] ?? '');
        if ($timingField === '') {
            return null;
        }

        if (!array_key_exists($timingField, $payload)) {
            return ($timing['required'] ?? false) === true ? 'timing' : null;
        }

        $startedAt = filter_var($payload[$timingField], FILTER_VALIDATE_INT);
        if ($startedAt === false) {
            return 'timing';
        }

        $elapsed = ($now ?? time()) - $startedAt;
        $minimum = (int) ($timing['minimum_seconds'] ?? 0);
        $maximum = (int) ($timing['maximum_seconds'] ?? PHP_INT_MAX);

        return $elapsed < $minimum || $elapsed > $maximum ? 'timing' : null;
    }
}
