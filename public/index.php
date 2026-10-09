<?php

declare(strict_types=1);

use StaticFormMailer\Application;
use StaticFormMailer\Http\Request;
use StaticFormMailer\Http\Router;
use StaticFormMailer\Http\Response;

require __DIR__ . '/../vendor/autoload.php';

$configPath = __DIR__ . '/../config/config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    echo "Configuration file missing.";
    exit;
}

try {
    $app = Application::boot($configPath);
    $config = $app->getConfig();

    $maxBytes = $config['application']['request_max_bytes'] ?? 32768;
    $request = Request::fromGlobals($maxBytes);

    $router = new Router($config);
    $response = $router->dispatch($request);

    $response->send();
} catch (\Throwable $e) {
    if ($e->getCode() === 413) {
        $response = new Response(413, [], 'Request Entity Too Large');
        $response->send();
    }
    http_response_code(500);
    echo "Internal Server Error";
    exit;
}
