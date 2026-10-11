<?php

declare(strict_types=1);

namespace StaticFormMailer\Http;

use DateTimeImmutable;
use DateTimeZone;
use StaticFormMailer\Form\SpamGuard;
use StaticFormMailer\Form\SubmissionValidator;
use StaticFormMailer\Form\ValidationException;
use StaticFormMailer\Mail\MailComposer;
use StaticFormMailer\Mail\Mailer;
use StaticFormMailer\Mail\PhpMailerAdapter;
use StaticFormMailer\RateLimit\FileRateLimiter;
use StaticFormMailer\Support\IpAddress;
use StaticFormMailer\Support\RequestId;
use Throwable;

final class Router
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly array $config,
        private readonly BodyParser $bodyParser,
        private readonly SubmissionValidator $submissionValidator,
        private readonly SpamGuard $spamGuard,
        private readonly MailComposer $mailComposer,
        private readonly Mailer $mailer,
        private readonly RequestId $requestId,
        private readonly ?FileRateLimiter $rateLimiter
    ) {
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromConfig(array $config): self
    {
        $rateLimitConfig = $config['rate_limit'] ?? [];
        $rateLimiter = null;
        if (($rateLimitConfig['enabled'] ?? false) === true) {
            $storagePath = rtrim((string) $config['application']['storage_path'], '/');
            $rateLimiter = new FileRateLimiter(
                $storagePath . '/rate-limits',
                (int) ($rateLimitConfig['maximum_attempts'] ?? 5),
                (int) ($rateLimitConfig['window_seconds'] ?? 900),
                (string) ($rateLimitConfig['ip_hash_key'] ?? '')
            );
        }

        return new self(
            $config,
            new BodyParser(),
            new SubmissionValidator(),
            new SpamGuard(),
            new MailComposer(),
            new PhpMailerAdapter($config['smtp'] ?? [], $config['mail'] ?? []),
            new RequestId(),
            $rateLimiter
        );
    }

    public function dispatch(Request $request): Response
    {
        try {
            return $this->handle($request);
        } catch (Throwable) {
            return Response::json([
                'ok' => false,
                'code' => 'internal_error',
                'message' => 'Internal server error.',
            ], 500);
        }
    }

    private function handle(Request $request): Response
    {
        $path = parse_url($request->uri, PHP_URL_PATH) ?? '/';

        if ($path === '/health') {
            if ($request->method !== 'GET') {
                return new Response(405, ['Allow' => 'GET']);
            }

            return Response::json(['status' => 'ok']);
        }

        if (!preg_match('#^/v1/forms/([^/]+)$#', $path, $matches)) {
            return new Response(404, [], 'Not found');
        }

        $formId = $matches[1];
        $formsConfig = $this->config['forms'] ?? [];
        if (!isset($formsConfig[$formId]) || !is_array($formsConfig[$formId])) {
            return new Response(404, [], 'Unknown form');
        }

        $formConfig = $formsConfig[$formId];
        $cors = new CorsPolicy($formConfig['allowed_origins'] ?? []);
        $corsResult = $cors->check($request);
        if ($corsResult instanceof Response) {
            return $corsResult;
        }

        if ($request->method !== 'POST') {
            return new Response(405, ['Allow' => 'POST, OPTIONS']);
        }

        return $this->handleSubmission($request, $formId, $formConfig, $corsResult);
    }

    /**
     * @param array<string, mixed> $formConfig
     * @param array<string, string> $corsHeaders
     */
    private function handleSubmission(
        Request $request,
        string $formId,
        array $formConfig,
        array $corsHeaders
    ): Response {
        $requestId = $this->requestId->generate();

        if ($this->rateLimiter !== null) {
            $clientAddress = IpAddress::resolve(
                $request->server,
                array_values($this->config['rate_limit']['trusted_proxies'] ?? [])
            );
            $rateLimit = $this->rateLimiter->consume($clientAddress);
            if (!$rateLimit->allowed) {
                return Response::json([
                    'ok' => false,
                    'requestId' => $requestId,
                    'code' => 'rate_limited',
                    'message' => 'Too many attempts. Please try again later.',
                ], 429, $corsHeaders + ['Retry-After' => (string) $rateLimit->retryAfter]);
            }
        }

        try {
            $payload = $this->bodyParser->parse($request);
        } catch (RequestException $exception) {
            return $this->errorResponse(
                $request,
                $formConfig,
                $exception->statusCode,
                $requestId,
                $exception->errorCode,
                $exception->publicMessage,
                $corsHeaders
            );
        }

        if ($this->spamGuard->inspect($payload, $formConfig) !== null) {
            return $this->successResponse($request, $formConfig, $requestId, $corsHeaders);
        }

        try {
            $submission = $this->submissionValidator->validate($payload, $formConfig);
        } catch (ValidationException $exception) {
            return $this->validationResponse(
                $request,
                $formConfig,
                $requestId,
                $exception->fieldErrors,
                $corsHeaders
            );
        }

        $message = $this->mailComposer->compose(
            $formId,
            $formConfig,
            $submission,
            $requestId,
            new DateTimeImmutable('now', new DateTimeZone('UTC'))
        );

        try {
            $this->mailer->send($message);
        } catch (Throwable) {
            return $this->errorResponse(
                $request,
                $formConfig,
                503,
                $requestId,
                'mail_unavailable',
                'The mail service is temporarily unavailable.',
                $corsHeaders
            );
        }

        return $this->successResponse($request, $formConfig, $requestId, $corsHeaders);
    }

    /**
     * @param array<string, mixed> $formConfig
     * @param array<string, string> $headers
     */
    private function successResponse(
        Request $request,
        array $formConfig,
        string $requestId,
        array $headers
    ): Response {
        if (!$this->wantsJson($request)) {
            return new Response(303, $headers + [
                'Location' => (string) $formConfig['success_redirect_url'],
            ]);
        }

        return Response::json([
            'ok' => true,
            'requestId' => $requestId,
            'message' => 'Your message was sent.',
        ], 200, $headers);
    }

    /**
     * @param array<string, mixed> $formConfig
     * @param array<string, string> $headers
     */
    private function errorResponse(
        Request $request,
        array $formConfig,
        int $status,
        string $requestId,
        string $code,
        string $message,
        array $headers
    ): Response {
        if (!$this->wantsJson($request)) {
            return new Response(303, $headers + [
                'Location' => (string) $formConfig['error_redirect_url'],
            ]);
        }

        return Response::json([
            'ok' => false,
            'requestId' => $requestId,
            'code' => $code,
            'message' => $message,
        ], $status, $headers);
    }

    /**
     * @param array<string, mixed> $formConfig
     * @param array<string, string> $fieldErrors
     * @param array<string, string> $headers
     */
    private function validationResponse(
        Request $request,
        array $formConfig,
        string $requestId,
        array $fieldErrors,
        array $headers
    ): Response {
        if (!$this->wantsJson($request)) {
            return new Response(303, $headers + [
                'Location' => (string) $formConfig['error_redirect_url'],
            ]);
        }

        return Response::json([
            'ok' => false,
            'requestId' => $requestId,
            'code' => 'validation_failed',
            'message' => 'Please correct the highlighted fields.',
            'fields' => $fieldErrors,
        ], 422, $headers);
    }

    private function wantsJson(Request $request): bool
    {
        $contentType = strtolower(trim(explode(';', $request->headers['content-type'] ?? '')[0]));
        $accept = strtolower($request->headers['accept'] ?? '');

        return $contentType === 'application/json' || str_contains($accept, 'application/json');
    }
}
