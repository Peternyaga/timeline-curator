<?php

namespace App\Providers;

use App\Support\ProductUpdateService;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('layouts.app', function ($view): void {
            $updates = auth()->check()
                ? app(ProductUpdateService::class)->unreadFor(auth()->user())
                : collect();

            $view->with([
                'unreadProductUpdates' => $updates,
                'latestProductUpdate' => $updates->first(),
            ]);
        });
    }
}
