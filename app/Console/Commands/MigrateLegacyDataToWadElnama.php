<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MigrateLegacyDataToWadElnama extends Command
{
    /** @var array<int, string> */
    private const TENANT_TABLES = [
        'products',
        'categories',
        'customers',
        'suppliers',
        'invoices',
        'payments',
        'cheques',
        'expenses',
        'recurring_expenses',
        'transactions',
        'storages',
        'stocks',
        'banks',
        'units',
        'preferences',
    ];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:migrate-legacy-data-to-wad-elnama';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Move pre-tenancy data to the "Wad Elnama" tenant and attach existing users';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tenantName = 'Wad Elnama';
        $tenantSlug = Str::slug($tenantName);
        $now = now();

        $tenant = Tenant::firstOrCreate(
            ['slug' => $tenantSlug],
            ['name' => $tenantName, 'is_active' => true]
        );

        $updatedRows = 0;

        foreach (self::TENANT_TABLES as $tableName) {
            $updated = DB::table($tableName)
                ->whereNull('tenant_id')
                ->update(['tenant_id' => $tenant->id, 'updated_at' => $now]);

            $updatedRows += $updated;
            $this->line("{$tableName}: {$updated} rows moved");
        }

        $allUserIds = User::query()->pluck('id');
        $existingMemberIds = DB::table('tenant_user')
            ->where('tenant_id', $tenant->id)
            ->pluck('user_id');

        $missingUserIds = $allUserIds->diff($existingMemberIds);

        if ($missingUserIds->isNotEmpty()) {
            $rows = $missingUserIds
                ->map(fn (int $userId): array => [
                    'tenant_id' => $tenant->id,
                    'user_id' => $userId,
                    'role' => 'member',
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->all();

            DB::table('tenant_user')->insert($rows);
        }

        $usersUpdated = User::query()
            ->whereNull('current_tenant_id')
            ->update(['current_tenant_id' => $tenant->id]);

        $this->info("Tenant: {$tenant->name} ({$tenant->slug})");
        $this->info("Data rows moved: {$updatedRows}");
        $this->info('Users added to tenant: '.$missingUserIds->count());
        $this->info("Users assigned current tenant: {$usersUpdated}");

        return self::SUCCESS;
    }
}
