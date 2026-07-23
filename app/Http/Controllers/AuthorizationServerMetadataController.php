<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class AuthorizationServerMetadataController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'issuer' => rtrim(url('/'), '/'),
            'authorization_endpoint' => url('/oauth/authorize'),
            'token_endpoint' => url('/oauth/token'),
            'registration_endpoint' => url('/oauth/register'),
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['none'],
            'scopes_supported' => config('oauth.scopes'),
        ]);
    }
}
