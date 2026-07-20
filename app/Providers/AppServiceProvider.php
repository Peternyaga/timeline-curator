<?php

namespace App\Providers;

use App\Auth\Auth0TokenVerifier;
use App\Auth\TokenVerifier;
use App\Tenancy\TenantContext;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
        $this->app->bind(TokenVerifier::class, Auth0TokenVerifier::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
