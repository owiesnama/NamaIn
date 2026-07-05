<?php

namespace App\Console\Commands;

use App\Enums\DeviceHealth;
use App\Enums\ReconciliationType;
use App\Models\Device;
use App\Models\Preference;
use App\Models\ReconciliationItem;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\ReconciliationDigestNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

/**
 * The daily reconciliation digest (Design 04 §3.2, R8). Per tenant: bind it, set
 * the locale from its Preference, and — if there are open items or device-health
 * warnings — email a single summary to every `reconciliation.view` holder. Never
 * per-event (oversell-storm safe). Mirrors `cheques:notify-for-due`.
 */
class ReconciliationDigestCommand extends Command
{
    protected $signature = 'reconciliation:digest';

    protected $description = 'Email a daily reconciliation summary to reconciliation.view holders per tenant';

    public function handle(): int
    {
        Tenant::query()->get()->each(fn (Tenant $tenant) => $this->digestFor($tenant));

        return self::SUCCESS;
    }

    private function digestFor(Tenant $tenant): void
    {
        app()->instance('currentTenant', $tenant);
        URL::defaults(['tenant' => $tenant->slug]);

        $locale = Preference::asPairs()->get('locale') ?: config('app.locale', 'en');
        app()->setLocale($locale);

        $openItems = ReconciliationItem::open()->latest('detected_at')->get();
        $warnings = $this->deviceWarnings($tenant);

        if ($openItems->isEmpty() && $warnings->isEmpty()) {
            return;
        }

        $summary = $this->buildSummary($tenant, $openItems, $warnings);

        $this->recipients($tenant)->each(
            fn ($user) => $user->notify((new ReconciliationDigestNotification($summary))->locale($locale)),
        );
    }

    /**
     * @param  Collection<int, ReconciliationItem>  $openItems
     * @param  Collection<int, array{name: string, health: string}>  $warnings
     * @return array<string, mixed>
     */
    private function buildSummary(Tenant $tenant, Collection $openItems, Collection $warnings): array
    {
        return [
            'tenant' => $tenant->name,
            'total_open' => $openItems->count(),
            'by_type' => $openItems->groupBy(fn (ReconciliationItem $item) => $item->type->value)
                ->map(fn (Collection $group, string $type): array => [
                    'label' => ReconciliationType::from($type)->label(),
                    'count' => $group->count(),
                ])->values()->all(),
            'recent' => $openItems->take(5)->map(fn (ReconciliationItem $item): array => [
                'type' => $item->type->label(),
                'occurred_at' => $item->occurred_at?->toIso8601String(),
            ])->values()->all(),
            'device_warnings' => $warnings->all(),
        ];
    }

    /**
     * @return Collection<int, array{name: string, health: string}>
     */
    private function deviceWarnings(Tenant $tenant): Collection
    {
        return Device::query()
            ->where('tenant_id', $tenant->id)
            ->get()
            ->map(fn (Device $device): array => ['device' => $device, 'health' => $device->health()])
            ->filter(fn (array $row): bool => in_array($row['health'], [DeviceHealth::Skewed, DeviceHealth::Offline], true))
            ->map(fn (array $row): array => [
                'name' => $row['device']->name,
                'health' => $row['health']->label(),
            ])
            ->values();
    }

    /**
     * @return Collection<int, User>
     */
    private function recipients(Tenant $tenant): Collection
    {
        $roleIds = Role::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereHas('permissions', fn ($query) => $query->where('slug', 'reconciliation.view'))
            ->pluck('id');

        return $tenant->users()
            ->wherePivotIn('role_id', $roleIds->all())
            ->wherePivot('is_active', true)
            ->get();
    }
}
