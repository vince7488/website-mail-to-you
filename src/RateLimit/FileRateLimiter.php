<?php

declare(strict_types=1);

namespace StaticFormMailer\RateLimit;

use RuntimeException;

final class FileRateLimiter
{
    public function __construct(
        private readonly string $directory,
        private readonly int $maximumAttempts,
        private readonly int $windowSeconds,
        private readonly string $hashKey
    ) {
    }

    public function consume(string $identifier, ?int $now = null): RateLimitDecision
    {
        $timestamp = $now ?? time();
        $identifierHash = hash_hmac('sha256', $identifier, $this->hashKey);
        $statePath = $this->directory . '/' . $identifierHash . '.json';
        $lockPath = $statePath . '.lock';

        $lock = fopen($lockPath, 'c+');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            if (is_resource($lock)) {
                fclose($lock);
            }
            throw new RuntimeException('Rate-limit storage is unavailable.');
        }

        $temporaryPath = null;
        try {
            $state = $this->readState($statePath, $timestamp);
            $windowStartedAt = $state['window_started_at'];
            $attempts = $state['attempts'];

            if ($timestamp - $windowStartedAt >= $this->windowSeconds) {
                $windowStartedAt = $timestamp;
                $attempts = 0;
            }

            if ($attempts >= $this->maximumAttempts) {
                $retryAfter = max(1, $this->windowSeconds - ($timestamp - $windowStartedAt));
                return new RateLimitDecision(false, $retryAfter, $identifierHash);
            }

            $attempts++;
            $temporaryPath = $statePath . '.tmp-' . bin2hex(random_bytes(6));
            $encoded = json_encode([
                'window_started_at' => $windowStartedAt,
                'attempts' => $attempts,
            ], JSON_THROW_ON_ERROR);

            if (file_put_contents($temporaryPath, $encoded) === false || !rename($temporaryPath, $statePath)) {
                throw new RuntimeException('Rate-limit state could not be written.');
            }
            $temporaryPath = null;

            return new RateLimitDecision(true, 0, $identifierHash);
        } finally {
            if ($temporaryPath !== null && is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * @return array{window_started_at: int, attempts: int}
     */
    private function readState(string $path, int $now): array
    {
        if (!is_file($path)) {
            return ['window_started_at' => $now, 'attempts' => 0];
        }

        $contents = file_get_contents($path);
        $state = is_string($contents) ? json_decode($contents, true) : null;
        if (
            !is_array($state)
            || !is_int($state['window_started_at'] ?? null)
            || !is_int($state['attempts'] ?? null)
        ) {
            return ['window_started_at' => $now, 'attempts' => 0];
        }

        return [
            'window_started_at' => $state['window_started_at'],
            'attempts' => max(0, $state['attempts']),
        ];
    }
}
