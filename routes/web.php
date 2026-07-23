<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DirectiveController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\TimelineController;
use App\Http\Controllers\TopicController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function (): void {
    Route::get('/oauth/authorize', [\App\Http\Controllers\OAuthAuthorizationController::class, 'show'])
        ->name('oauth.authorize');
    Route::post('/oauth/authorize', [\App\Http\Controllers\OAuthAuthorizationController::class, 'decide']);
});

Route::middleware(['auth', 'tenant.web'])->group(function (): void {
    Route::get('/timeline', TimelineController::class)->name('timeline');
    Route::post('/topics', [TopicController::class, 'store'])->name('topics.store');
    Route::post('/directives', [DirectiveController::class, 'store'])->name('directives.store');
    Route::post('/stories/{story}/feedback', [FeedbackController::class, 'store'])->name('feedback.store');
});
