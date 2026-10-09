<?php

declare(strict_types=1);

namespace StaticFormMailer\Config;

use RuntimeException;

class ConfigValidator
{
    public function validate(array $config): void
    {
        $isProduction = ($config['application']['environment'] ?? 'production') === 'production';

        if ($isProduction && ($config['application']['debug'] ?? false) === true) {
            throw new RuntimeException('Debugging must not be enabled in production.');
        }

        $storagePath = $config['application']['storage_path'] ?? '';
        if (!is_dir($storagePath) || !is_writable($storagePath)) {
            throw new RuntimeException("Storage directory is missing or unwritable: {$storagePath}");
        }

        $this->validateSmtp($config['smtp'] ?? [], $isProduction);
        $this->validateMail($config['mail'] ?? []);
        $this->validateForms($config['forms'] ?? [], $isProduction);
        $this->validateRateLimit($config['rate_limit'] ?? []);
    }

    private function validateSmtp(array $smtp, bool $isProduction): void
    {
        if (empty($smtp['host'])) {
            throw new RuntimeException('SMTP hostname cannot be empty.');
        }

        $port = $smtp['port'] ?? 0;
        if (!is_int($port) || $port < 1 || $port > 65535) {
            throw new RuntimeException('SMTP port must be between 1 and 65535.');
        }

        $encryption = $smtp['encryption'] ?? '';
        if (!in_array($encryption, ['smtps', 'starttls', 'none'], true)) {
            throw new RuntimeException('Unsupported SMTP encryption mode.');
        }

        $tls = $smtp['tls'] ?? [];
        if ($isProduction) {
            if (($tls['verify_peer'] ?? true) === false) {
                throw new RuntimeException('Production certificate verification must not be disabled.');
            }
            if (($tls['allow_self_signed'] ?? false) === true) {
                throw new RuntimeException('Self-signed certificates are not allowed in production.');
            }
        }
    }

    private function validateMail(array $mail): void
    {
        $fromAddress = $mail['from']['address'] ?? '';
        if (!filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('The From address is invalid.');
        }
    }

    private function validateForms(array $forms, bool $isProduction): void
    {
        foreach ($forms as $formId => $formConfig) {
            $recipients = $formConfig['recipients'] ?? [];
            if (empty($recipients)) {
                throw new RuntimeException("Form '{$formId}' must have at least one recipient.");
            }

            foreach ($recipients as $recipient) {
                if (!filter_var($recipient['address'] ?? '', FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException("Form '{$formId}' has an invalid recipient address.");
                }
            }

            $origins = $formConfig['allowed_origins'] ?? [];
            foreach ($origins as $origin) {
                $parsed = parse_url($origin);
                if (!$parsed || !isset($parsed['scheme'], $parsed['host'])) {
                    throw new RuntimeException("Invalid origin format: {$origin}");
                }
                if (isset($parsed['path']) && $parsed['path'] !== '' && $parsed['path'] !== '/') {
                    throw new RuntimeException("Origin must not contain a path: {$origin}");
                }
                if (isset($parsed['query']) || isset($parsed['fragment'])) {
                    throw new RuntimeException("Origin must not contain a query or fragment: {$origin}");
                }
            }

            if ($isProduction) {
                foreach (['success_redirect_url', 'error_redirect_url'] as $redirectKey) {
                    $url = $formConfig[$redirectKey] ?? '';
                    if ($url !== '' && str_starts_with($url, 'http://')) {
                        throw new RuntimeException("Redirect URL must be HTTPS in production: {$url}");
                    }
                }
            }

            $fields = $formConfig['fields'] ?? [];
            foreach ($fields as $fieldName => $fieldConfig) {
                if (!preg_match('/^[a-zA-Z0-9_]+$/', $fieldName)) {
                    throw new RuntimeException("Field name '{$fieldName}' is unsafe (use alphanumeric and underscores).");
                }
            }
        }
    }

    private function validateRateLimit(array $rateLimit): void
    {
        if ($rateLimit['enabled'] ?? false) {
            $key = $rateLimit['ip_hash_key'] ?? '';
            if (strlen($key) < 32) {
                throw new RuntimeException('Rate-limit IP hash key is missing or too short (minimum 32 bytes).');
            }
        }
    }
}
