<?php

declare(strict_types=1);

namespace StaticFormMailer\Form;

final readonly class Submission
{
    /**
     * @param array<string, string> $fields
     */
    public function __construct(public array $fields)
    {
    }

    public function value(string $fieldName): string
    {
        return $this->fields[$fieldName] ?? '';
    }
}
