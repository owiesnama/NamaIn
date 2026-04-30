<?php

namespace App\Console\Commands;

use App\Enums\TenantDataGroup;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class HardDeleteTenantData extends Command
{
    protected $signature = 'tenants:hard-delete-cleared';

    protected $description = 'Permanently delete data for tenants cleared more than 30 days ago';

    public function handle(): void
    {
        $tenants = Tenant::whereNotNull('data_cleared_at')
            ->where('data_cleared_at', '<=', now()->subDays(30))
            ->get();

        if ($tenants->isEmpty()) {
            $this->info('No tenants pending hard delete.');

            return;
        }

        foreach ($tenants as $tenant) {
            $this->hardDeleteTenantData($tenant);
        }
    }

    private function hardDeleteTenantData(Tenant $tenant): void
    {
        $count = 0;

        DB::transaction(function () use ($tenant, &$count) {
            foreach ($tenant->cleared_groups ?? [] as $groupValue) {
                $group = TenantDataGroup::from($groupValue);

                foreach ($group->models() as $modelClass) {
                    $instance = new $modelClass;
                    $table = $instance->getTable();

                    if (in_array(SoftDeletes::class, class_uses_recursive($modelClass))) {
                        $deleted = DB::table($table)
                            ->where('tenant_id', $tenant->id)
                            ->whereNotNull('deleted_at')
                            ->delete();
                    } else {
                        $deleted = DB::table($table)
                            ->where('tenant_id', $tenant->id)
                            ->delete();
                    }

                    $count += $deleted;
                }
            }

            $tenant->update([
                'data_cleared_at' => null,
                'cleared_groups' => null,
            ]);
        });

        $this->info("Hard-deleted {$count} rows for tenant '{$tenant->name}' (ID: {$tenant->id}).");
    }
}
