<?php

namespace App\Actions\Admin;

use App\Enums\TenantDataGroup;
use App\Models\Tenant;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RestoreTenantDataAction
{
    public function handle(Tenant $tenant): void
    {
        if (! $tenant->isDataCleared()) {
            throw ValidationException::withMessages([
                'tenant' => __('Tenant data has not been cleared.'),
            ]);
        }

        DB::transaction(function () use ($tenant) {
            foreach ($tenant->cleared_groups ?? [] as $groupValue) {
                $group = TenantDataGroup::from($groupValue);

                foreach ($group->models() as $modelClass) {
                    if (in_array(SoftDeletes::class, class_uses_recursive($modelClass))) {
                        $modelClass::withoutGlobalScope(TenantScope::class)
                            ->onlyTrashed()
                            ->where('tenant_id', $tenant->id)
                            ->restore();
                    }
                }
            }

            $tenant->update([
                'data_cleared_at' => null,
                'cleared_groups' => null,
            ]);
        });
    }
}
