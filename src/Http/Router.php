<?php

declare(strict_types=1);

namespace StaticFormMailer\Http;

use RuntimeException;

class Router
{
    public function __construct(private array $config)
    {
    }

    public function dispatch(Request $request): Response
    {
        try {
            return $this->handle($request);
        } catch (RuntimeException $e) {
            $code = $e->getCode();
            if ($code < 400 || $code > 599) {
                $code = 400;
            }
            return Response::json([
                'ok' => false,
                'message' => $e->getMessage()
            ], $code);
        } catch (\Throwable $e) {
            return Response::json([
                'ok' => false,
                'message' => 'Internal server error.'
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

        if (preg_match('#^/v1/forms/([^/]+)$#', $path, $matches)) {
            $formId = $matches[1];

            $formsConfig = $this->config['forms'] ?? [];
            if (!isset($formsConfig[$formId])) {
                return new Response(404, [], 'Unknown form');
            }

            $formConfig = $formsConfig[$formId];

            $cors = new CorsPolicy($formConfig['allowed_origins'] ?? []);
            $corsResult = $cors->check($request);

            if ($corsResult instanceof Response) {
                return $corsResult;
            }

            $corsHeaders = $corsResult;

            if ($request->method !== 'POST') {
                return new Response(405, ['Allow' => 'POST, OPTIONS']);
            }

            // Process form
            return Response::json(['ok' => true, 'message' => "Mock message sent for {$formId}."], 200, $corsHeaders);
        }

        return new Response(404, [], 'Not found');
    }
}
