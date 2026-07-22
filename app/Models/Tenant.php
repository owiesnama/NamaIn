<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use App\Features\Facades\Entitlements;
use App\Features\Feature;
use App\Traits\HasPublicId;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, HasPublicId;

    /** @var string[] */
    public const RESERVED_SLUGS = [
        'admin', 'www', 'api', 'app', 'mail',
        'support', 'login', 'register', 'help',
        'queue', 'monitor', 'status', 'docs',
        'billing', 'webhook', 'webhooks', 'cdn',
        'static', 'assets', 'media', 'staging',
        'dev', 'test', 'demo', 'sandbox',
        'dashboard', 'auth', 'sso', 'oauth', 'cloud',
    ];

    /**
     * Slugs that may not be used as a tenant subdomain.
     *
     * The reservation is lifted on local so any subdomain works while developing.
     *
     * @return string[]
     */
    public static function reservedSlugs(): array
    {
        return app()->isLocal() ? [] : self::RESERVED_SLUGS;
    }

    protected static function booted(): void
    {
        static::unguard();

        // Every tenant owns a change-log counter and a reserved cloud register
        // (R0) that numbers all cloud-web sales. The counter is provisioned first
        // because creating R0 already emits a change entry. Guarded so historical
        // migrations that create tenants before these tables exist do not fail;
        // those tenants are provisioned by the dedicated seed migrations instead.
        static::created(function (Tenant $tenant): void {
            if (Schema::hasTable('tenant_sync_state')) {
                DB::table('tenant_sync_state')->insertOrIgnore([
                    'tenant_id' => $tenant->id,
                    'next_seq' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if (Schema::hasTable('registers')) {
                Register::cloudRegisterFor($tenant);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role', 'role_id', 'is_active')->withTimestamps();
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function storages(): HasMany
    {
        return $this->hasMany(Storage::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function featureOverrides(): HasMany
    {
        return $this->hasMany(TenantFeatureOverride::class);
    }

    /**
     * The tenant's current live subscription: active, or trialing within its
     * trial window, and not past its end date. Newest wins if several exist.
     */
    public function currentSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->whereIn('status', array_map(fn ($s) => $s->value, SubscriptionStatus::live()))
            ->where(function ($query) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->where(function ($query) {
                $query->where('status', SubscriptionStatus::Active->value)
                    ->orWhere(function ($trial) {
                        $trial->where('status', SubscriptionStatus::Trialing->value)
                            ->where(function ($window) {
                                $window->whereNull('trial_ends_at')->orWhere('trial_ends_at', '>', now());
                            });
                    });
            })
            ->latest('starts_at')
            ->first();
    }

    /**
     * The plan whose entitlements apply: the current subscription's plan, or
     * the default (free) plan when there is no live subscription.
     */
    public function activePlan(): ?Plan
    {
        return $this->currentSubscription()?->plan ?? Plan::where('is_default', true)->first();
    }

    /**
     * @return Collection<int, TenantFeatureOverride>
     */
    public function liveFeatureOverrides(): Collection
    {
        return $this->featureOverrides()
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->get();
    }

    public function owner(): ?User
    {
        return $this->users()->wherePivot('role', 'owner')->first();
    }

    /**
     * The "merchant" recipients for booking notifications: the owner and every
     * admin of the tenant. Resolved in the unbound reminder scan.
     *
     * @return Collection<int, User>
     */
    public function merchantUsers(): Collection
    {
        return $this->users()->wherePivotIn('role', ['owner', 'admin'])->get();
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function deactivate(): bool
    {
        return $this->update(['is_active' => false]);
    }

    public function activate(): bool
    {
        return $this->update(['is_active' => true]);
    }

    /**
     * Whether offline sync is live for this tenant — the single
     * {@see Feature::OfflineSync} entitlement (plan value or override) that
     * also gates register serials and change-log capture.
     */
    public function isOfflineEnabled(): bool
    {
        return Entitlements::for($this)->enabled(Feature::OfflineSync);
    }

    /**
     * Admin switches write a per-tenant override: it wins over the plan in both
     * directions, so enable works off-plan and disable is a real kill switch.
     */
    public function enableOffline(): bool
    {
        return $this->setOfflineOverride(true);
    }

    public function disableOffline(): bool
    {
        return $this->setOfflineOverride(false);
    }

    private function setOfflineOverride(bool $enabled): bool
    {
        $this->featureOverrides()->updateOrCreate(
            ['feature_key' => Feature::OfflineSync->value],
            ['value' => $enabled, 'expires_at' => null],
        );

        Entitlements::flush($this);

        return true;
    }

    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $bindingField = $field;

        if (! $bindingField) {
            $bindingField = is_numeric($value) ? $this->getRouteKeyName() : 'slug';
        }

        return $this->newQuery()
            ->where($bindingField, $value)
            ->first();
    }
}
