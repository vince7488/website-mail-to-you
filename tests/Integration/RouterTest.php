<?php

declare(strict_types=1);

namespace StaticFormMailer\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use StaticFormMailer\Form\SpamGuard;
use StaticFormMailer\Form\SubmissionValidator;
use StaticFormMailer\Http\BodyParser;
use StaticFormMailer\Http\Request;
use StaticFormMailer\Http\Router;
use StaticFormMailer\Mail\MailComposer;
use StaticFormMailer\Mail\Mailer;
use StaticFormMailer\Mail\MailMessage;
use StaticFormMailer\Support\RequestId;

final class RouterTest extends TestCase
{
    public function testSuccessfulSubmissionMatchesFrontendContractAndSendsMail(): void
    {
        $mailer = new RecordingMailer();
        $response = $this->router($mailer)->dispatch($this->request($this->validPayload()));
        $body = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->statusCode);
        self::assertTrue($body['ok']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $body['requestId']);
        self::assertSame('Your message was sent.', $body['message']);
        self::assertSame('http://localhost:3000', $response->headers['Access-Control-Allow-Origin']);
        self::assertCount(1, $mailer->messages);
    }

    public function testHoneypotReturnsIndistinguishableSuccessWithoutSendingMail(): void
    {
        $mailer = new RecordingMailer();
        $payload = $this->validPayload();
        $payload['confirm_email'] = 'bot@example.net';
        $response = $this->router($mailer)->dispatch($this->request($payload));
        $body = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->statusCode);
        self::assertTrue($body['ok']);
        self::assertArrayHasKey('requestId', $body);
        self::assertCount(0, $mailer->messages);
    }

    public function testValidationFailureDoesNotSendMail(): void
    {
        $mailer = new RecordingMailer();
        $payload = $this->validPayload();
        $payload['email'] = 'invalid';
        $response = $this->router($mailer)->dispatch($this->request($payload));
        $body = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(422, $response->statusCode);
        self::assertSame('validation_failed', $body['code']);
        self::assertArrayHasKey('email', $body['fields']);
        self::assertCount(0, $mailer->messages);
    }

    public function testSmtpFailureReturnsGenericResponse(): void
    {
        $mailer = new RecordingMailer(true);
        $response = $this->router($mailer)->dispatch($this->request($this->validPayload()));
        $body = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(503, $response->statusCode);
        self::assertSame('mail_unavailable', $body['code']);
        self::assertStringNotContainsString('secret SMTP detail', $response->body);
    }

    private function router(RecordingMailer $mailer): Router
    {
        return new Router(
            $this->config(),
            new BodyParser(),
            new SubmissionValidator(),
            new SpamGuard(),
            new MailComposer(),
            $mailer,
            new RequestId(),
            null
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function request(array $payload): Request
    {
        return new Request(
            'POST',
            '/v1/forms/contact',
            [
                'origin' => 'http://localhost:3000',
                'content-type' => 'application/json',
                'accept' => 'application/json',
            ],
            json_encode($payload, JSON_THROW_ON_ERROR),
            ['REMOTE_ADDR' => '127.0.0.1']
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'name' => 'Jane Example',
            'email' => 'jane@example.net',
            'topic' => 'Project inquiry',
            'message' => 'This is a sufficiently long message.',
            'confirm_email' => '',
            '_started_at' => time() - 10,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function config(): array
    {
        return [
            'forms' => [
                'contact' => [
                    'recipients' => [['address' => 'team@example.com']],
                    'cc' => [],
                    'bcc' => [],
                    'email_subject' => 'New website inquiry',
                    'reply_to_field' => 'email',
                    'allowed_origins' => ['http://localhost:3000'],
                    'success_redirect_url' => 'https://example.com/success',
                    'error_redirect_url' => 'https://example.com/error',
                    'honeypot_field' => 'confirm_email',
                    'timing' => [
                        'field' => '_started_at',
                        'required' => false,
                        'minimum_seconds' => 3,
                        'maximum_seconds' => 7200,
                    ],
                    'fields' => [
                        'name' => [
                            'label' => 'Name',
                            'type' => 'string',
                            'required' => true,
                            'minimum_length' => 2,
                            'maximum_length' => 120,
                        ],
                        'email' => [
                            'label' => 'Email',
                            'type' => 'email',
                            'required' => true,
                            'maximum_length' => 254,
                        ],
                        'topic' => [
                            'label' => 'Topic',
                            'type' => 'string',
                            'required' => false,
                            'maximum_length' => 100,
                        ],
                        'message' => [
                            'label' => 'Message',
                            'type' => 'string',
                            'required' => true,
                            'minimum_length' => 10,
                            'maximum_length' => 5000,
                        ],
                    ],
                ],
            ],
            'rate_limit' => [
                'trusted_proxies' => [],
            ],
        ];
    }
}

final class RecordingMailer implements Mailer
{
    /** @var list<MailMessage> */
    public array $messages = [];

    public function __construct(private readonly bool $fail = false)
    {
    }

    public function send(MailMessage $message): void
    {
        if ($this->fail) {
            throw new RuntimeException('secret SMTP detail');
        }

        $this->messages[] = $message;
    }
}
