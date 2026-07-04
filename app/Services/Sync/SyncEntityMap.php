<?php

namespace App\Services\Sync;

use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerAdvance;
use App\Models\Device;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\PosSession;
use App\Models\Preference;
use App\Models\Product;
use App\Models\Register;
use App\Models\Role;
use App\Models\Storage;
use App\Models\Supplier;
use App\Models\TreasuryAccount;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The declarative per-entity sync config: Design 02 §2.2 (snapshot entity set)
 * and §4 (pull scope matrix) as one table-keyed registry. Tenant-wide entities
 * rely on the bound TenantScope; scoped entities add the device's register /
 * storage / drawer filter; pivot tables carry explicit tenant joins because
 * they have no model scope.
 */
class SyncEntityMap
{
    /**
     * Snapshot entities in client apply order (referenced rows first).
     *
     * @return list<SyncEntityDefinition>
     */
    public function snapshotEntities(): array
    {
        return array_values(array_filter($this->entities(), fn (SyncEntityDefinition $entity) => $entity->inSnapshot));
    }

    /**
     * The definition delivering pull changes for a table, or null when the
     * table is outside the device sync scope (skipped, cursor still advances).
     */
    public function pulledEntity(string $table): ?SyncEntityDefinition
    {
        $entity = collect($this->entities())->firstWhere('table', $table);

        return $entity?->pulled ? $entity : null;
    }

    /**
     * @return list<SyncEntityDefinition>
     */
    private function entities(): array
    {
        return [
            // ── Identity & access (tenant-wide; local login + can:) ────────
            new SyncEntityDefinition(
                table: 'permissions',
                query: fn (Device $device) => Permission::query(),
                columns: ['slug' => 'slug', 'group' => 'group', 'description' => 'description'],
            ),
            new SyncEntityDefinition(
                table: 'roles',
                query: fn (Device $device) => Role::query(),
                columns: ['name' => 'name', 'slug' => 'slug', 'is_system' => 'is_system'],
            ),
            new SyncEntityDefinition(
                table: 'permission_role',
                query: fn (Device $device) => DB::table('permission_role')
                    ->whereIn('role_id', Role::query()->select('id')->toBase()),
                references: [
                    'role' => ['role_id', Role::class],
                    'permission' => ['permission_id', Permission::class],
                ],
            ),
            new SyncEntityDefinition(
                table: 'users',
                query: fn (Device $device) => User::query()->whereIn(
                    'id',
                    DB::table('tenant_user')->where('tenant_id', $device->tenant_id)->select('user_id')
                ),
                // the bcrypt hash is exported deliberately: it is the device's
                // offline credential cache (Design 02 §10.5)
                columns: [
                    'name' => 'name',
                    'email' => 'email',
                    'password' => 'password',
                    'must_change_password' => 'must_change_password',
                ],
            ),
            new SyncEntityDefinition(
                table: 'tenant_user',
                query: fn (Device $device) => DB::table('tenant_user')->where('tenant_id', $device->tenant_id),
                columns: ['is_active' => 'is_active'],
                references: [
                    'user' => ['user_id', User::class],
                    'role' => ['role_id', Role::class],
                ],
            ),

            // ── Store topology ─────────────────────────────────────────────
            new SyncEntityDefinition(
                table: 'storages',
                query: fn (Device $device) => Storage::query(),
                columns: ['name' => 'name', 'address' => 'address', 'type' => 'type'],
                references: ['active_session' => ['active_session_id', PosSession::class]],
            ),
            new SyncEntityDefinition(
                table: 'registers',
                query: fn (Device $device) => Register::query(),
                columns: ['code' => 'code', 'label' => 'label', 'is_cloud' => 'is_cloud', 'is_active' => 'is_active'],
                references: ['storage' => ['storage_id', Storage::class]],
            ),
            // the device register's serial counters, so a replacement device
            // resumes the sequence without collision (Design 04 §4.3);
            // snapshot-only — counters are device-owned after provisioning
            new SyncEntityDefinition(
                table: 'register_serials',
                query: fn (Device $device) => DB::table('register_serials')
                    ->where('tenant_id', $device->tenant_id)
                    ->where('register_id', $device->register_id),
                columns: ['series' => 'series'],
                integers: ['year' => 'year', 'last_seq' => 'last_seq'],
                references: ['register' => ['register_id', Register::class]],
                pulled: false,
            ),
            // the register's cash drawer only (§4: each register writes only
            // its own drawer)
            new SyncEntityDefinition(
                table: 'treasury_accounts',
                query: fn (Device $device) => TreasuryAccount::query()->where('register_id', $device->register_id),
                columns: ['name' => 'name', 'type' => 'type', 'currency' => 'currency', 'is_active' => 'is_active'],
                integers: ['opening_balance' => 'opening_balance'],
                references: [
                    'storage' => ['sale_point_id', Storage::class],
                    'register' => ['register_id', Register::class],
                ],
            ),
            new SyncEntityDefinition(
                table: 'preferences',
                query: fn (Device $device) => Preference::query(),
                columns: ['key' => 'key', 'value' => 'value'],
            ),

            // ── Catalog (tenant-wide reference data) ───────────────────────
            new SyncEntityDefinition(
                table: 'categories',
                query: fn (Device $device) => Category::query(),
                columns: ['name' => 'name', 'type' => 'type'],
                integers: ['budget_limit' => 'budget_limit'],
            ),
            new SyncEntityDefinition(
                table: 'products',
                query: fn (Device $device) => Product::query(),
                columns: [
                    'name' => 'name',
                    'currency' => 'currency',
                    'alert_quantity' => 'alert_quantity',
                    'expire_date' => 'expire_date',
                ],
                integers: ['cost' => 'cost', 'price' => 'price', 'average_cost' => 'average_cost'],
            ),
            new SyncEntityDefinition(
                table: 'units',
                query: fn (Device $device) => Unit::query(),
                columns: ['name' => 'name', 'conversion_factor' => 'conversion_factor'],
                references: ['product' => ['product_id', Product::class]],
            ),
            // category links; pivot writes fire no model events in Phase 0,
            // so this is snapshot-only until pivot capture lands
            new SyncEntityDefinition(
                table: 'categorizables',
                query: fn (Device $device) => DB::table('categorizables')
                    ->whereIn('category_id', Category::query()->select('id')->toBase()),
                references: ['category' => ['category_id', Category::class]],
                morphs: ['categorizable' => ['categorizable_type', 'categorizable_id']],
                pulled: false,
            ),

            // ── Stock levels (tenant-wide read; replenishment hints, §4) ───
            new SyncEntityDefinition(
                table: 'stocks',
                query: fn (Device $device) => DB::table('stocks')
                    ->where('tenant_id', $device->tenant_id)
                    ->whereNull('deleted_at'),
                columns: ['quantity' => 'quantity'],
                references: [
                    'storage' => ['storage_id', Storage::class],
                    'product' => ['product_id', Product::class],
                ],
            ),

            // ── Parties (tenant-wide; any customer may buy at any register) ─
            new SyncEntityDefinition(
                table: 'suppliers',
                query: fn (Device $device) => Supplier::query(),
                columns: ['name' => 'name', 'address' => 'address', 'phone_number' => 'phone_number'],
                integers: ['opening_debit' => 'opening_debit', 'opening_credit' => 'opening_credit'],
            ),
            new SyncEntityDefinition(
                table: 'customers',
                query: fn (Device $device) => Customer::query()->addSelect([
                    'sync_invoiced' => Invoice::query()
                        ->whereColumn('invocable_id', 'customers.id')
                        ->where('invocable_type', Customer::class)
                        ->selectRaw('COALESCE(SUM(total - discount), 0)'),
                    'sync_paid_in' => Payment::query()
                        ->whereColumn('payable_id', 'customers.id')
                        ->where('payable_type', Customer::class)
                        ->where('direction', 'in')
                        ->selectRaw('COALESCE(SUM(amount), 0)'),
                    'sync_paid_out' => Payment::query()
                        ->whereColumn('payable_id', 'customers.id')
                        ->where('payable_type', Customer::class)
                        ->where('direction', 'out')
                        ->selectRaw('COALESCE(SUM(amount), 0)'),
                ]),
                columns: [
                    'name' => 'name',
                    'address' => 'address',
                    'phone_number' => 'phone_number',
                    'is_system' => 'is_system',
                ],
                integers: [
                    'credit_limit' => 'credit_limit',
                    'opening_debit' => 'opening_debit',
                    'opening_credit' => 'opening_credit',
                ],
                // minor-unit mirror of HasAccountBalance (offline credit checks)
                computed: [
                    'balance' => fn (object $row): int => (int) $row->sync_invoiced
                        - ((int) $row->sync_paid_in - (int) $row->sync_paid_out)
                        + (int) $row->opening_debit
                        - (int) $row->opening_credit,
                ],
            ),
            new SyncEntityDefinition(
                table: 'customer_advances',
                query: fn (Device $device) => CustomerAdvance::query(),
                columns: ['status' => 'status', 'notes' => 'notes', 'given_at' => 'given_at'],
                integers: ['amount' => 'amount', 'settled_amount' => 'settled_amount'],
                references: [
                    'customer' => ['customer_id', Customer::class],
                    'treasury_account' => ['treasury_account_id', TreasuryAccount::class],
                    'created_by' => ['created_by', User::class],
                ],
            ),
        ];
    }
}
