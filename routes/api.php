<?php

use App\Http\Controllers\DeploymentController;
use App\Http\Controllers\McpController;
use App\Http\Controllers\OAuthMetadataController;
use Illuminate\Support\Facades\Route;

Route::get('/.well-known/oauth-protected-resource', OAuthMetadataController::class);
Route::get('/.well-known/oauth-protected-resource/mcp', OAuthMetadataController::class);
Route::get('/deployment/install', [DeploymentController::class, 'show'])->middleware('throttle:3,1');
Route::post('/deployment/install', [DeploymentController::class, 'install'])->middleware('throttle:3,1');
Route::match(['GET', 'POST', 'DELETE', 'OPTIONS'], '/mcp', McpController::class)->middleware('tenant.bearer');
