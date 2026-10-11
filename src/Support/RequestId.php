<?php

declare(strict_types=1);

namespace StaticFormMailer\Support;

final class RequestId
{
    public function generate(): string
    {
        return bin2hex(random_bytes(16));
    }
}
