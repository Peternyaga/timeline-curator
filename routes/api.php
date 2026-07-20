<?php

use App\Http\Controllers\McpController;
use App\Http\Controllers\OAuthMetadataController;
use Illuminate\Support\Facades\Route;

Route::get('/.well-known/oauth-protected-resource', OAuthMetadataController::class);
Route::match(['GET', 'POST', 'DELETE', 'OPTIONS'], '/mcp', McpController::class)->middleware('tenant.bearer');
