<?php

namespace App\Providers;

use App\Features\EntitlementManager;
use Illuminate\Support\ServiceProvider;

class FeatureServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Singleton so the per-tenant resolution cache lives for one request.
        $this->app->singleton(EntitlementManager::class);
    }
}
