<?php

return [
    'scopes' => [
        'read:curation-context',
        'write:curation-runs',
        'write:story-batches',
    ],
    'authorization_code_ttl_minutes' => (int) env('OAUTH_CODE_TTL_MINUTES', 5),
    'access_token_ttl_minutes' => (int) env('OAUTH_ACCESS_TOKEN_TTL_MINUTES', 60),
    'refresh_token_until_revoked' => filter_var(
        env('OAUTH_REFRESH_TOKEN_UNTIL_REVOKED', true),
        FILTER_VALIDATE_BOOL,
    ),
    // Used only when refresh_token_until_revoked is false.
    'refresh_token_ttl_days' => (int) env('OAUTH_REFRESH_TOKEN_TTL_DAYS', 0),
];
