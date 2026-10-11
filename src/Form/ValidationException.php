<?php

declare(strict_types=1);

namespace StaticFormMailer\Form;

use RuntimeException;

final class ValidationException extends RuntimeException
{
    /**
     * @param array<string, string> $fieldErrors
     */
    public function __construct(public readonly array $fieldErrors)
    {
        parent::__construct('Submission validation failed.');
    }
}
