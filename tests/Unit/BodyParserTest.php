<?php

declare(strict_types=1);

namespace StaticFormMailer\Tests\Unit;

use PHPUnit\Framework\TestCase;
use StaticFormMailer\Http\BodyParser;
use StaticFormMailer\Http\Request;
use StaticFormMailer\Http\RequestException;

final class BodyParserTest extends TestCase
{
    public function testParsesJsonObject(): void
    {
        $request = new Request(
            'POST',
            '/v1/forms/contact',
            ['content-type' => 'application/json; charset=utf-8'],
            '{"name":"Jane"}',
            []
        );

        self::assertSame(['name' => 'Jane'], (new BodyParser())->parse($request));
    }

    public function testRejectsMalformedJson(): void
    {
        $request = new Request(
            'POST',
            '/v1/forms/contact',
            ['content-type' => 'application/json'],
            '{',
            []
        );

        try {
            (new BodyParser())->parse($request);
            self::fail('Expected malformed JSON to be rejected.');
        } catch (RequestException $exception) {
            self::assertSame(400, $exception->statusCode);
            self::assertSame('malformed_request', $exception->errorCode);
        }
    }

    public function testRejectsUnsupportedContentType(): void
    {
        $request = new Request(
            'POST',
            '/v1/forms/contact',
            ['content-type' => 'multipart/form-data'],
            '',
            []
        );

        try {
            (new BodyParser())->parse($request);
            self::fail('Expected unsupported content type to be rejected.');
        } catch (RequestException $exception) {
            self::assertSame(415, $exception->statusCode);
            self::assertSame('unsupported_media_type', $exception->errorCode);
        }
    }
}
