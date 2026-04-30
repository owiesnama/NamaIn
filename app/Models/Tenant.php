<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::unguard();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'data_cleared_at' => 'datetime',
            'cleared_groups' => 'array',
        ];
    }

    public function isDataCleared(): bool
    {
        return $this->data_cleared_at !== null;
    }

    public function scheduledHardDeleteAt(): ?Carbon
    {
        return $this->data_cleared_at?->addDays(30);
    }

    public function hasClearedGroup(string $group): bool
    {
        return in_array($group, $this->cleared_groups ?? []);
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

    public function owner(): ?User
    {
        return $this->users()->wherePivot('role', 'owner')->first();
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
