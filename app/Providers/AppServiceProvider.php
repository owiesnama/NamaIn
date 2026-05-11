<?php

namespace App\Providers;

use App\Listeners\InvalidateAllUserSessions;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\User;
use App\Observers\InvoiceObserver;
use App\Observers\QuoteObserver;
use App\Services\Core\Cache as TenantCacheService;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('tenant-cache', fn () => new TenantCacheService);
    }

    public function boot(): void
    {
        Builder::macro('whereLike', function ($attributes, string $searchTerm) {
            /** @var Builder $this */
            $operator = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';

            $this->where(function (Builder $query) use ($attributes, $searchTerm, $operator) {
                foreach (Arr::wrap($attributes) as $attribute) {
                    $query->when(
                        str_contains($attribute, '.'),
                        function (Builder $query) use ($attribute, $searchTerm, $operator) {
                            [$relationName, $relationAttribute] = explode('.', $attribute);

                            $query->orWhereHas($relationName, function (Builder $query) use ($relationAttribute, $searchTerm, $operator) {
                                $query->where($relationAttribute, $operator, "%{$searchTerm}%");
                            });
                        },
                        function (Builder $query) use ($attribute, $searchTerm, $operator) {
                            $query->orWhere($attribute, $operator, "%{$searchTerm}%");
                        }
                    );
                }
            });

            return $this;
        });

        $this->registerDatabaseMacros();

        Event::listen(
            Registered::class,
            SendEmailVerificationNotification::class,
        );

        Event::listen(
            Logout::class,
            InvalidateAllUserSessions::class,
        );

        Invoice::observe(InvoiceObserver::class);
        Quote::observe(QuoteObserver::class);

        Gate::before(function (User $user, string $ability) {
            if ($user->hasRole('owner')) {
                return true;
            }
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }

    private function registerDatabaseMacros(): void
    {
        DB::macro('dateFormat', function (string $groupBy, string $column = 'transactions.created_at'): string {
            $driver = DB::getDriverName();

            return match ($groupBy) {
                'week' => match ($driver) {
                    'sqlite' => "strftime('%Y-W%W', $column)",
                    'pgsql' => "TO_CHAR($column, 'IYYY-\"W\"IW')",
                    default => "DATE_FORMAT($column, '%Y-W%v')",
                },
                'month' => match ($driver) {
                    'sqlite' => "strftime('%Y-%m', $column)",
                    'pgsql' => "TO_CHAR($column, 'YYYY-MM')",
                    default => "DATE_FORMAT($column, '%Y-%m')",
                },
                default => match ($driver) {
                    'sqlite' => "strftime('%Y-%m-%d', $column)",
                    'pgsql' => "TO_CHAR($column, 'YYYY-MM-DD')",
                    default => "DATE_FORMAT($column, '%Y-%m-%d')",
                },
            };
        });

        DB::macro('dateDiff', function (string $column = 'invoices.created_at'): string {
            return match (DB::getDriverName()) {
                'sqlite' => "CAST(julianday('now') - julianday($column) AS INTEGER)",
                'pgsql' => "EXTRACT(DAY FROM NOW() - $column)::INTEGER",
                default => "DATEDIFF(NOW(), $column)",
            };
        });
    }
}
