<?php

namespace App\Providers;

use App\Models\JobApplication;
use App\Support\ProductUpdateService;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Schema;
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
            $jobActions = auth()->check() && Schema::hasTable('job_applications')
                ? JobApplication::withoutGlobalScopes()
                    ->where('tenant_id', auth()->user()->tenant_id)
                    ->whereIn('status', ['needs_information', 'needs_manual_action'])
                    ->count()
                : 0;

            $view->with([
                'unreadProductUpdates' => $updates,
                'latestProductUpdate' => $updates->first(),
                'jobActionCount' => $jobActions,
            ]);
        });
    }
}
