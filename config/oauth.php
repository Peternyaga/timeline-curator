<?php

return [
    'scopes' => [
        'read:curation-context',
        'write:curation-runs',
        'write:story-batches',
        'read:job-search-context',
        'write:job-batches',
        'read:approved-applications',
        'write:application-progress',
    ],
    'authorization_code_ttl_minutes' => (int) env('OAUTH_CODE_TTL_MINUTES', 5),
    'access_token_ttl_minutes' => (int) env('OAUTH_ACCESS_TOKEN_TTL_MINUTES', 60),
];
