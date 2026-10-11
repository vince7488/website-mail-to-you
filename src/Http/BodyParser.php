<?php

declare(strict_types=1);

namespace StaticFormMailer\Http;

use JsonException;

final class BodyParser
{
    /**
     * @return array<string, mixed>
     */
    public function parse(Request $request): array
    {
        $contentType = strtolower(trim(explode(';', $request->headers['content-type'] ?? '')[0]));

        return match ($contentType) {
            'application/json' => $this->parseJson($request->body),
            'application/x-www-form-urlencoded' => $this->parseFormEncoded($request->body),
            default => throw new RequestException(
                415,
                'unsupported_media_type',
                'Use application/json or application/x-www-form-urlencoded.'
            ),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function parseJson(string $body): array
    {
        try {
            $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RequestException(400, 'malformed_request', 'The request body is not valid JSON.');
        }

        if (!is_array($payload) || array_is_list($payload)) {
            throw new RequestException(400, 'malformed_request', 'The JSON body must be an object.');
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseFormEncoded(string $body): array
    {
        parse_str($body, $payload);

        return $payload;
    }
}
