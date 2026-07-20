<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class OAuthMetadataController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $domain = rtrim((string) config('services.auth0.domain'), '/');
        $issuer = str_starts_with($domain, 'http') ? $domain : "https://$domain";

        return response()->json([
            'resource' => url('/mcp'),
            'authorization_servers' => [$issuer.'/'],
            'bearer_methods_supported' => ['header'],
            'scopes_supported' => [
                'read:curation-context',
                'write:curation-runs',
                'write:story-batches',
            ],
        ]);
    }
}
