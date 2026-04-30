<?php

namespace App\Actions\Admin;

use App\Enums\TenantDataGroup;
use App\Models\Tenant;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClearTenantDataAction
{
    /**
     * @param  array<TenantDataGroup>  $selectedGroups
     */
    public function handle(Tenant $tenant, array $selectedGroups): void
    {
        if ($tenant->isActive()) {
            throw ValidationException::withMessages([
                'tenant' => __('Tenant must be deactivated before clearing data.'),
            ]);
        }

        $groups = TenantDataGroup::resolveWithDependencies($selectedGroups);

        DB::transaction(function () use ($tenant, $groups) {
            foreach ($groups as $group) {
                $this->clearGroup($tenant, $group);
            }

            $existingGroups = $tenant->cleared_groups ?? [];
            $newGroups = array_map(fn (TenantDataGroup $g) => $g->value, $groups);

            $tenant->update([
                'data_cleared_at' => $tenant->data_cleared_at ?? now(),
                'cleared_groups' => array_values(array_unique(array_merge($existingGroups, $newGroups))),
            ]);
        });
    }

    private function clearGroup(Tenant $tenant, TenantDataGroup $group): void
    {
        foreach ($group->models() as $modelClass) {
            $instance = new $modelClass;

            if (in_array(SoftDeletes::class, class_uses_recursive($modelClass))) {
                $modelClass::withoutGlobalScope(TenantScope::class)
                    ->where('tenant_id', $tenant->id)
                    ->delete();
            } else {
                DB::table($instance->getTable())
                    ->where('tenant_id', $tenant->id)
                    ->delete();
            }
        }

        if ($group === TenantDataGroup::Financial) {
            $this->clearCategorizables($tenant);
        }
    }

    private function clearCategorizables(Tenant $tenant): void
    {
        DB::table('categorizables')
            ->whereIn('category_id', function ($query) use ($tenant) {
                $query->select('id')
                    ->from('categories')
                    ->where('tenant_id', $tenant->id);
            })
            ->delete();
    }
}
