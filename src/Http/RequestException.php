<?php

declare(strict_types=1);

namespace StaticFormMailer\Http;

use RuntimeException;

final class RequestException extends RuntimeException
{
    public function __construct(
        public readonly int $statusCode,
        public readonly string $errorCode,
        public readonly string $publicMessage
    ) {
        parent::__construct($publicMessage, $statusCode);
    }
}
