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
        return $this->belongsToMany(Tenant::class)->withPivot('role')->withTimestamps();
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

    public function roleInCurrentTenant(): ?string
    {
        if (! $this->current_tenant_id) {
            return null;
        }

        return $this->tenants()
            ->where('tenants.id', $this->current_tenant_id)
            ->first()
            ?->pivot
            ?->role;
    }

    public function hasRole(string ...$roles): bool
    {
        $currentRole = $this->roleInCurrentTenant();

        if (! $currentRole) {
            return false;
        }

        return in_array($currentRole, $roles);
    }
}
