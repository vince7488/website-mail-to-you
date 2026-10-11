<?php

declare(strict_types=1);

namespace StaticFormMailer\RateLimit;

final readonly class RateLimitDecision
{
    public function __construct(
        public bool $allowed,
        public int $retryAfter,
        public string $identifierHash
    ) {
    }
}
