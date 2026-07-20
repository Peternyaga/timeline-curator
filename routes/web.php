<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DirectiveController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\TimelineController;
use App\Http\Controllers\TopicController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::get('/login', [AuthController::class, 'login'])->name('login');
Route::get('/auth/callback', [AuthController::class, 'callback'])->name('auth.callback');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'tenant.web'])->group(function (): void {
    Route::get('/timeline', TimelineController::class)->name('timeline');
    Route::post('/topics', [TopicController::class, 'store'])->name('topics.store');
    Route::post('/directives', [DirectiveController::class, 'store'])->name('directives.store');
    Route::post('/stories/{story}/feedback', [FeedbackController::class, 'store'])->name('feedback.store');
});
