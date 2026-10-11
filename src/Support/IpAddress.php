<?php

declare(strict_types=1);

namespace StaticFormMailer\Support;

final class IpAddress
{
    /**
     * @param array<string, mixed> $server
     * @param list<string> $trustedProxies
     */
    public static function resolve(array $server, array $trustedProxies): string
    {
        $remoteAddress = is_string($server['REMOTE_ADDR'] ?? null)
            ? $server['REMOTE_ADDR']
            : '';

        if (!filter_var($remoteAddress, FILTER_VALIDATE_IP)) {
            return '0.0.0.0';
        }

        if (!in_array($remoteAddress, $trustedProxies, true)) {
            return $remoteAddress;
        }

        $forwardedFor = $server['HTTP_X_FORWARDED_FOR'] ?? null;
        if (!is_string($forwardedFor)) {
            return $remoteAddress;
        }

        $candidate = trim(explode(',', $forwardedFor)[0]);

        return filter_var($candidate, FILTER_VALIDATE_IP) ? $candidate : $remoteAddress;
    }
}
