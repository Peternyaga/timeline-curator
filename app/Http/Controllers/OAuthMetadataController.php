<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class OAuthMetadataController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'resource' => url('/mcp'),
            'authorization_servers' => [rtrim(url('/'), '/')],
            'bearer_methods_supported' => ['header'],
            'scopes_supported' => config('oauth.scopes'),
        ]);
    }
}
