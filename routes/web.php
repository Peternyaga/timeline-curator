<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DirectiveController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\OAuthAuthorizationController;
use App\Http\Controllers\PolicyController;
use App\Http\Controllers\TimelineController;
use App\Http\Controllers\TimelineUpdatesController;
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
    Route::get('/oauth/authorize', [OAuthAuthorizationController::class, 'show'])
        ->name('oauth.authorize');
    Route::post('/oauth/authorize', [OAuthAuthorizationController::class, 'decide']);
});

Route::middleware(['auth', 'tenant.web'])->group(function (): void {
    Route::get('/timeline', TimelineController::class)->name('timeline');
    Route::get('/timeline/updates', TimelineUpdatesController::class)->name('timeline.updates');
    Route::get('/policy', PolicyController::class)->name('policy');

    Route::post('/topics', [TopicController::class, 'store'])->name('topics.store');
    Route::patch('/topics/{topic}', [TopicController::class, 'update'])->name('topics.update');
    Route::patch('/topics/{topic}/archive', [TopicController::class, 'archive'])->name('topics.archive');
    Route::patch('/topics/{topic}/restore', [TopicController::class, 'restore'])->name('topics.restore');

    Route::post('/directives', [DirectiveController::class, 'store'])->name('directives.store');
    Route::patch('/directives/{directive}', [DirectiveController::class, 'update'])->name('directives.update');
    Route::patch('/directives/{directive}/archive', [DirectiveController::class, 'archive'])->name('directives.archive');
    Route::patch('/directives/{directive}/restore', [DirectiveController::class, 'restore'])->name('directives.restore');

    Route::post('/stories/{story}/feedback', [FeedbackController::class, 'store'])->name('feedback.store');
});
