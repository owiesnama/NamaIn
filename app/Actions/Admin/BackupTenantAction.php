<?php

namespace App\Actions\Admin;

use App\Models\Backup;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class BackupTenantAction
{
    /** @var string[] */
    private const EXCLUDED_TABLES = [
        'admin_audit_logs',
        'backup_settings',
        'backups',
        'failed_jobs',
        'jobs',
        'migrations',
        'password_resets',
        'permission_role',
        'permissions',
        'personal_access_tokens',
        'sessions',
        'shetabit_visits',
        'tenant_user',
        'tenants',
        'visits_log',
    ];

    /** @var string[] */
    private const USER_EXCLUDED_COLUMNS = [
        'current_tenant_id',
        'role',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        'must_change_password',
    ];

    public function handle(Backup $backup, Tenant $tenant): void
    {
        $backup->markRunning();

        $directory = storage_path('app/backups');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $tables = $this->tenantScopedTables();

        match ($backup->format) {
            'sql' => $this->exportAsSql($tenant, $tables, $backup->path),
            'json' => $this->exportAsJson($tenant, $tables, $backup->path),
        };

        $backup->markCompleted();
    }

    /** @return array<int, string> */
    private function tenantScopedTables(): array
    {
        $rows = DB::select("
            SELECT table_name
            FROM information_schema.columns
            WHERE column_name = 'tenant_id'
              AND table_schema = 'public'
            ORDER BY table_name
        ");

        return array_values(array_filter(
            array_map(fn ($row) => $row->table_name, $rows),
            fn ($table) => ! in_array($table, self::EXCLUDED_TABLES),
        ));
    }

    /**
     * @param  array<int, string>  $tables
     * @return array<int, string>
     */
    private function columnsWithout(string $table, array $exclude): array
    {
        $all = DB::select(
            "SELECT column_name FROM information_schema.columns
             WHERE table_name = ? AND table_schema = 'public'
             ORDER BY ordinal_position",
            [$table],
        );

        return array_values(array_filter(
            array_map(fn ($row) => $row->column_name, $all),
            fn ($col) => ! in_array($col, $exclude),
        ));
    }

    /** @param array<int, string> $tables */
    private function exportAsSql(Tenant $tenant, array $tables, string $path): void
    {
        $handle = fopen($path, 'w');

        fwrite($handle, "-- Backup: {$tenant->name}\n");
        fwrite($handle, '-- Generated: '.now()->toDateTimeString()."\n\n");
        fwrite($handle, "BEGIN;\n\n");

        // Tenant users
        $this->writeUsersSql($handle, $tenant->id);

        foreach ($tables as $table) {
            $this->writeTableSql($handle, $table, $tenant->id);
        }

        // categorizables through tenant categories
        $this->writeCategorizablesSql($handle, $tenant->id);

        // permission_role through tenant roles
        $this->writePermissionRolesSql($handle, $tenant->id);

        fwrite($handle, "COMMIT;\n");
        fclose($handle);
    }

    /**
     * @param  resource  $handle
     */
    private function writeTableSql($handle, string $table, int $tenantId): void
    {
        $columns = $this->columnsWithout($table, ['tenant_id']);
        $rows = DB::table($table)->where('tenant_id', $tenantId)->get();

        if ($rows->isEmpty()) {
            return;
        }

        $columnList = implode(', ', array_map(fn ($c) => "\"{$c}\"", $columns));
        fwrite($handle, "-- {$table}\n");

        foreach ($rows as $row) {
            $rowArray = (array) $row;
            $values = array_map(
                fn ($col) => $this->escapeValue($rowArray[$col] ?? null),
                $columns,
            );
            fwrite($handle, "INSERT INTO \"{$table}\" ({$columnList}) VALUES (".implode(', ', $values).");\n");
        }

        fwrite($handle, "\n");
    }

    /**
     * @param  resource  $handle
     */
    private function writeUsersSql($handle, int $tenantId): void
    {
        $columns = $this->columnsWithout('users', array_merge(['current_tenant_id'], self::USER_EXCLUDED_COLUMNS));

        $rows = DB::table('users')
            ->join('tenant_user', 'users.id', '=', 'tenant_user.user_id')
            ->where('tenant_user.tenant_id', $tenantId)
            ->select('users.*')
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $columnList = implode(', ', array_map(fn ($c) => "\"{$c}\"", $columns));
        fwrite($handle, "-- users\n");

        foreach ($rows as $row) {
            $rowArray = (array) $row;
            $values = array_map(
                fn ($col) => $this->escapeValue($rowArray[$col] ?? null),
                $columns,
            );
            fwrite($handle, "INSERT INTO \"users\" ({$columnList}) VALUES (".implode(', ', $values).");\n");
        }

        fwrite($handle, "\n");
    }

    /**
     * @param  resource  $handle
     */
    private function writeCategorizablesSql($handle, int $tenantId): void
    {
        $rows = DB::table('categorizables')
            ->join('categories', 'categorizables.category_id', '=', 'categories.id')
            ->where('categories.tenant_id', $tenantId)
            ->select('categorizables.*')
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $columns = array_keys((array) $rows->first());
        $columnList = implode(', ', array_map(fn ($c) => "\"{$c}\"", $columns));
        fwrite($handle, "-- categorizables\n");

        foreach ($rows as $row) {
            $values = array_map(fn ($v) => $this->escapeValue($v), array_values((array) $row));
            fwrite($handle, "INSERT INTO \"categorizables\" ({$columnList}) VALUES (".implode(', ', $values).");\n");
        }

        fwrite($handle, "\n");
    }

    /**
     * @param  resource  $handle
     */
    private function writePermissionRolesSql($handle, int $tenantId): void
    {
        $rows = DB::table('permission_role')
            ->join('roles', 'permission_role.role_id', '=', 'roles.id')
            ->where('roles.tenant_id', $tenantId)
            ->select('permission_role.*')
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        fwrite($handle, "-- permission_role\n");

        $columns = array_keys((array) $rows->first());
        $columnList = implode(', ', array_map(fn ($c) => "\"{$c}\"", $columns));

        foreach ($rows as $row) {
            $values = array_map(fn ($v) => $this->escapeValue($v), array_values((array) $row));
            fwrite($handle, "INSERT INTO \"permission_role\" ({$columnList}) VALUES (".implode(', ', $values).");\n");
        }

        fwrite($handle, "\n");
    }

    private function escapeValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return DB::connection()->getPdo()->quote((string) $value);
    }

    /** @param array<int, string> $tables */
    private function exportAsJson(Tenant $tenant, array $tables, string $path): void
    {
        $data = [
            '_meta' => [
                'name' => $tenant->name,
                'generated_at' => now()->toDateTimeString(),
            ],
        ];

        // Users belonging to this tenant
        $userColumns = $this->columnsWithout('users', array_merge(['current_tenant_id'], self::USER_EXCLUDED_COLUMNS));
        $users = DB::table('users')
            ->join('tenant_user', 'users.id', '=', 'tenant_user.user_id')
            ->where('tenant_user.tenant_id', $tenant->id)
            ->select(array_map(fn ($c) => "users.{$c}", $userColumns))
            ->get();

        if ($users->isNotEmpty()) {
            $data['users'] = $users->toArray();
        }

        foreach ($tables as $table) {
            $columns = $this->columnsWithout($table, ['tenant_id']);
            $rows = DB::table($table)
                ->where('tenant_id', $tenant->id)
                ->get($columns);

            if ($rows->isNotEmpty()) {
                $data[$table] = $rows->toArray();
            }
        }

        // categorizables through tenant categories
        $categorizables = DB::table('categorizables')
            ->join('categories', 'categorizables.category_id', '=', 'categories.id')
            ->where('categories.tenant_id', $tenant->id)
            ->select('categorizables.*')
            ->get();

        if ($categorizables->isNotEmpty()) {
            $data['categorizables'] = $categorizables->toArray();
        }

        // permission_role through tenant roles
        $permissionRoles = DB::table('permission_role')
            ->join('roles', 'permission_role.role_id', '=', 'roles.id')
            ->where('roles.tenant_id', $tenant->id)
            ->select('permission_role.*')
            ->get();

        if ($permissionRoles->isNotEmpty()) {
            $data['permission_role'] = $permissionRoles->toArray();
        }

        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
