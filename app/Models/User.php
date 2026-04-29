<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::unguard();
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'must_change_password' => 'boolean',
        ];
    }

    /**
     * The accessors to append to the user's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
    ];

    /**
     * Check weather this user is admin or not.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class)->withPivot('role', 'role_id', 'is_active')->withTimestamps();
    }

    public function currentTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'current_tenant_id');
    }

    public function switchTenant(Tenant $tenant): void
    {
        if (! $this->belongsToTenant($tenant)) {
            throw new \DomainException('User does not belong to this tenant.');
        }

        $this->update(['current_tenant_id' => $tenant->id]);
    }

    public function belongsToTenant(Tenant $tenant): bool
    {
        return $this->tenants()->where('tenants.id', $tenant->id)->exists();
    }

    public function roleInCurrentTenant(): ?Role
    {
        return once(function () {
            $tenantId = $this->current_tenant_id;

            if (app()->bound('currentTenant')) {
                $resolvedTenantId = app('currentTenant')->id;
                if ($this->tenants()->where('tenants.id', $resolvedTenantId)->exists()) {
                    $tenantId = $resolvedTenantId;
                }
            }

            if (! $tenantId) {
                return null;
            }

            $roleId = $this->tenants()
                ->where('tenants.id', $tenantId)
                ->first()
                ?->pivot
                ?->role_id;

            if (! $roleId) {
                return null;
            }

            return Role::withoutGlobalScopes()->with('permissions')->find($roleId);
        });
    }

    public function hasRole(string ...$roles): bool
    {
        $role = $this->roleInCurrentTenant();

        if (! $role) {
            return false;
        }

        return in_array($role->slug, $roles);
    }

    public function hasPermission(string $permission): bool
    {
        return $this->roleInCurrentTenant()?->hasPermission($permission) ?? false;
    }

    public function isActiveInTenant(?Tenant $tenant = null): bool
    {
        $tenant ??= $this->currentTenant;

        if (! $tenant) {
            return false;
        }

        return (bool) $this->tenants()
            ->where('tenants.id', $tenant->id)
            ->first()
            ?->pivot
            ?->is_active;
    }
}
