<?php

$applicationHost = parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST);

return [
    'allowed_hosts' => array_values(array_filter(
        [$applicationHost],
        static fn (mixed $host): bool => is_string($host) && $host !== '',
    )),
    'session_path' => env('MCP_SESSION_PATH', storage_path('framework/mcp-sessions')),
    'session_ttl' => (int) env('MCP_SESSION_TTL', 3600),
];
