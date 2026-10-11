<?php

declare(strict_types=1);

namespace StaticFormMailer\Http;

class CorsPolicy
{
    public function __construct(private readonly array $allowedOrigins)
    {
    }

    public function check(Request $request): Response|array
    {
        $origin = $request->headers['origin'] ?? null;
        
        if ($origin === null || $origin === 'null') {
            return new Response(403, [], 'Origin not allowed');
        }

        if (!in_array($origin, $this->allowedOrigins, true)) {
            return new Response(403, [], 'Origin not allowed');
        }

        $corsHeaders = [
            'Access-Control-Allow-Origin' => $origin,
            'Access-Control-Allow-Methods' => 'POST, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type',
            'Access-Control-Max-Age' => '600',
            'Vary' => 'Origin',
        ];

        if ($request->method === 'OPTIONS') {
            return Response::empty(204, $corsHeaders);
        }

        return $corsHeaders;
    }
}
