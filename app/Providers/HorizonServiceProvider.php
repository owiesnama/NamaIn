<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        Horizon::auth(function ($request) {
            return auth()->guard('admin')->check()
                && auth()->guard('admin')->user()->isAdmin();
        });
    }

    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null) {
            return $user?->isAdmin() ?? false;
        });
    }
}
