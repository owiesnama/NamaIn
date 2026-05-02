<?php

namespace App\Providers;

use Laravel\Telescope\EntryType;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    public function register(): void
    {
        Telescope::night();

        $this->hideSensitiveRequestDetails();

        $isLocal = $this->app->environment('local');

        Telescope::filter(function (IncomingEntry $entry) use ($isLocal) {
            return $isLocal ||
                   $entry->isReportableException() ||
                   $entry->isFailedRequest() ||
                   $entry->isFailedJob() ||
                   $entry->isScheduledTask() ||
                   $entry->hasMonitoredTag() ||
                   $entry->type === EntryType::JOB ||
                   $entry->type === EntryType::LOG;
        });

        Telescope::tag(function (IncomingEntry $entry) {
            if (app()->bound('currentTenant')) {
                return ['tenant:'.app('currentTenant')->id];
            }

            return [];
        });
    }

    public function boot(): void
    {
        parent::boot();

        Telescope::auth(function ($request) {
            return auth()->guard('admin')->check()
                && auth()->guard('admin')->user()->isAdmin();
        });
    }

    protected function hideSensitiveRequestDetails(): void
    {
        if ($this->app->environment('local')) {
            return;
        }

        Telescope::hideRequestParameters(['_token']);

        Telescope::hideRequestHeaders([
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
        ]);
    }

    protected function gate(): void
    {
        //
    }
}
