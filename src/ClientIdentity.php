<?php

declare(strict_types=1);

namespace Ping;

final class ClientIdentity
{
    public static function hash(array $server): string
    {
        $candidate = $server['HTTP_CF_CONNECTING_IP'] ?? $server['REMOTE_ADDR'] ?? 'unknown';
        $ip = filter_var($candidate, FILTER_VALIDATE_IP) !== false ? $candidate : 'unknown';
        return hash('sha256', $ip);
    }
}
