<?php

declare(strict_types=1);

namespace StaticFormMailer\Http;

class Response
{
    public function __construct(
        public readonly int $statusCode = 200,
        public readonly array $headers = [],
        public readonly string $body = ''
    ) {
    }

    public static function json(array $data, int $status = 200, array $headers = []): self
    {
        $headers['Content-Type'] = 'application/json; charset=utf-8';
        return new self($status, $headers, json_encode($data, JSON_UNESCAPED_SLASHES));
    }

    public static function empty(int $status = 204, array $headers = []): self
    {
        return new self($status, $headers, '');
    }

    public function send(): never
    {
        http_response_code($this->statusCode);
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
        echo $this->body;
        exit;
    }
}
