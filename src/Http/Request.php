<?php

declare(strict_types=1);

namespace StaticFormMailer\Http;

use RuntimeException;

class Request
{
    public function __construct(
        public readonly string $method,
        public readonly string $uri,
        public readonly array $headers,
        public readonly string $body,
        public readonly array $server
    ) {
    }

    public static function fromGlobals(int $maxBytes): self
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', substr($key, 5));
                $headers[strtolower($name)] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'])) {
                $name = str_replace('_', '-', $key);
                $headers[strtolower($name)] = $value;
            }
        }

        $contentLength = (int) ($headers['content-length'] ?? 0);
        if ($contentLength > $maxBytes) {
            throw new RuntimeException("Request body too large", 413);
        }

        $body = '';
        $stream = fopen('php://input', 'r');
        if ($stream) {
            while (!feof($stream)) {
                $body .= fread($stream, 8192);
                if (strlen($body) > $maxBytes) {
                    fclose($stream);
                    throw new RuntimeException("Request body too large", 413);
                }
            }
            fclose($stream);
        }

        return new self(
            $_SERVER['REQUEST_METHOD'] ?? 'GET',
            $_SERVER['REQUEST_URI'] ?? '/',
            $headers,
            $body,
            $_SERVER
        );
    }
}
