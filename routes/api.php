<?php

use App\Http\Controllers\DeploymentController;
use App\Http\Controllers\McpController;
use App\Http\Controllers\AuthorizationServerMetadataController;
use App\Http\Controllers\OAuthClientRegistrationController;
use App\Http\Controllers\OAuthMetadataController;
use App\Http\Controllers\OAuthTokenController;
use Illuminate\Support\Facades\Route;

Route::get('/.well-known/oauth-protected-resource', OAuthMetadataController::class);
Route::get('/.well-known/oauth-protected-resource/mcp', OAuthMetadataController::class);
Route::get('/.well-known/oauth-authorization-server', AuthorizationServerMetadataController::class);
Route::post('/oauth/register', OAuthClientRegistrationController::class)->middleware('throttle:20,1');
Route::post('/oauth/token', OAuthTokenController::class)->middleware('throttle:60,1');
Route::get('/deployment/install', [DeploymentController::class, 'show'])->middleware('throttle:3,1');
Route::post('/deployment/install', [DeploymentController::class, 'install'])->middleware('throttle:3,1');
Route::match(['GET', 'POST', 'DELETE', 'OPTIONS'], '/mcp', McpController::class)->middleware('tenant.bearer');
