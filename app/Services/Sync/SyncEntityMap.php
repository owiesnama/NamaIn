<?php

namespace App\Services\Sync;

use App\Enums\TreasuryAccountType;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerAdvance;
use App\Models\Device;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\PosSession;
use App\Models\Preference;
use App\Models\Product;
use App\Models\Register;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Storage;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\TransactionReceipt;
use App\Models\TreasuryAccount;
use App\Models\TreasuryMovement;
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
            // The register's own cash drawer (§4: each register writes only its
            // drawer) PLUS the tenant's active non-cash receiving accounts and
            // the shared default cash account (read-only on device) so the POS
            // can route bank transfers exactly like the cloud checkout. The
            // POS default crosses as a flag — the cloud preference stores a
            // cloud DB id that means nothing on the device.
            new SyncEntityDefinition(
                table: 'treasury_accounts',
                query: function (Device $device) {
                    $defaultId = (int) preference('pos_default_bank_account_id', 0);

                    return TreasuryAccount::query()
                        ->where(fn ($q) => $q
                            ->where('register_id', $device->register_id)
                            ->orWhere(fn ($bank) => $bank
                                ->where('is_active', true)
                                ->where('type', '!=', TreasuryAccountType::Cash->value))
                            ->orWhereIn('id', array_filter([TreasuryAccount::defaultCash()?->id])))
                        ->select('treasury_accounts.*')
                        ->selectRaw('(treasury_accounts.id = ?) as pos_default', [$defaultId]);
                },
                columns: ['name' => 'name', 'type' => 'type', 'currency' => 'currency', 'is_active' => 'is_active', 'pos_default' => 'pos_default'],
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
                    'is_global_favorite' => 'is_global_favorite',
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

            // ── Per-user favorites (register grid's المفضلة section) ──────
            new SyncEntityDefinition(
                table: 'favorite_products',
                query: fn (Device $device) => DB::table('favorite_products')
                    ->whereIn('product_id', Product::query()->select('id')->toBase()),
                references: [
                    'user' => ['user_id', User::class],
                    'product' => ['product_id', Product::class],
                ],
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

            // ── Transactional records (pull-only, §4: a fresh device has no
            //    history; it needs its own sales back, not other registers') ─
            new SyncEntityDefinition(
                table: 'invoices',
                query: fn (Device $device) => Invoice::query()->where('register_id', $device->register_id),
                columns: [
                    'serial_number' => 'serial_number',
                    'status' => 'status',
                    'payment_method' => 'payment_method',
                    'payment_status' => 'payment_status',
                    'currency' => 'currency',
                    'is_inverse' => 'is_inverse',
                    'inverse_reason' => 'inverse_reason',
                    'created_at' => 'created_at',
                ],
                integers: ['total' => 'total', 'discount' => 'discount', 'paid_amount' => 'paid_amount'],
                references: [
                    'parent_invoice' => ['parent_invoice_id', Invoice::class],
                    'pos_session' => ['pos_session_id', PosSession::class],
                    'register' => ['register_id', Register::class],
                ],
                morphs: ['invocable' => ['invocable_type', 'invocable_id']],
                inSnapshot: false,
            ),
            new SyncEntityDefinition(
                table: 'transactions',
                query: fn (Device $device) => Transaction::query()->whereIn(
                    'invoice_id',
                    Invoice::query()->where('register_id', $device->register_id)->select('id')->toBase()
                ),
                columns: [
                    'quantity' => 'quantity',
                    'base_quantity' => 'base_quantity',
                    'description' => 'description',
                    'currency' => 'currency',
                    'delivered' => 'delivered',
                    'delivered_at' => 'delivered_at',
                    'created_at' => 'created_at',
                ],
                integers: ['price' => 'price', 'unit_cost' => 'unit_cost'],
                references: [
                    'invoice' => ['invoice_id', Invoice::class],
                    'storage' => ['storage_id', Storage::class],
                    'product' => ['product_id', Product::class],
                    'unit' => ['unit_id', Unit::class],
                    'delivered_by' => ['delivered_by', User::class],
                    'fulfilled_from_storage' => ['fulfilled_from_storage_id', Storage::class],
                ],
                inSnapshot: false,
            ),
            new SyncEntityDefinition(
                table: 'transaction_receipts',
                query: fn (Device $device) => TransactionReceipt::query()
                    ->where('storage_id', $device->register->storage_id),
                columns: ['quantity' => 'quantity', 'received_at' => 'received_at', 'notes' => 'notes'],
                references: [
                    'transaction' => ['transaction_id', Transaction::class],
                    'storage' => ['storage_id', Storage::class],
                    'received_by' => ['received_by', User::class],
                ],
                inSnapshot: false,
            ),
            new SyncEntityDefinition(
                table: 'payments',
                query: fn (Device $device) => Payment::query()->whereIn(
                    'invoice_id',
                    Invoice::query()->where('register_id', $device->register_id)->select('id')->toBase()
                ),
                columns: [
                    'payment_method' => 'payment_method',
                    'direction' => 'direction',
                    'reference' => 'reference',
                    'notes' => 'notes',
                    'paid_at' => 'paid_at',
                    'currency' => 'currency',
                ],
                integers: ['amount' => 'amount'],
                references: [
                    'invoice' => ['invoice_id', Invoice::class],
                    'created_by' => ['created_by', User::class],
                    'treasury_account' => ['treasury_account_id', TreasuryAccount::class],
                ],
                morphs: ['payable' => ['payable_type', 'payable_id']],
                inSnapshot: false,
            ),
            // sessions are keyed by sale point today (storages.active_session_id);
            // register-keyed sessions arrive with the multi-register runtime
            new SyncEntityDefinition(
                table: 'pos_sessions',
                query: fn (Device $device) => PosSession::query()
                    ->where('storage_id', $device->register->storage_id),
                columns: ['closed_at' => 'closed_at', 'created_at' => 'created_at'],
                integers: ['opening_float' => 'opening_float', 'closing_float' => 'closing_float'],
                references: [
                    'storage' => ['storage_id', Storage::class],
                    'opened_by' => ['opened_by', User::class],
                    'closed_by' => ['closed_by', User::class],
                ],
                inSnapshot: false,
            ),
            new SyncEntityDefinition(
                table: 'expenses',
                query: fn (Device $device) => Expense::query()->whereIn(
                    'treasury_account_id',
                    TreasuryAccount::query()->where('register_id', $device->register_id)->select('id')->toBase()
                ),
                columns: [
                    'title' => 'title',
                    'currency' => 'currency',
                    'notes' => 'notes',
                    'expensed_at' => 'expensed_at',
                    'status' => 'status',
                    'approved_at' => 'approved_at',
                    'created_at' => 'created_at',
                ],
                integers: ['amount' => 'amount'],
                references: [
                    'created_by' => ['created_by', User::class],
                    'approved_by' => ['approved_by', User::class],
                    'treasury_account' => ['treasury_account_id', TreasuryAccount::class],
                ],
                inSnapshot: false,
            ),
            new SyncEntityDefinition(
                table: 'treasury_movements',
                query: fn (Device $device) => TreasuryMovement::query()->whereIn(
                    'treasury_account_id',
                    TreasuryAccount::query()->where('register_id', $device->register_id)->select('id')->toBase()
                ),
                columns: ['reason' => 'reason', 'notes' => 'notes', 'occurred_at' => 'occurred_at'],
                integers: ['amount' => 'amount', 'balance_after' => 'balance_after'],
                references: [
                    'treasury_account' => ['treasury_account_id', TreasuryAccount::class],
                    'created_by' => ['created_by', User::class],
                ],
                morphs: ['movable' => ['movable_type', 'movable_id']],
                inSnapshot: false,
            ),
            new SyncEntityDefinition(
                table: 'stock_movements',
                query: fn (Device $device) => StockMovement::query()
                    ->where('storage_id', $device->register->storage_id),
                columns: [
                    'reason' => 'reason',
                    'quantity' => 'quantity',
                    'quantity_before' => 'quantity_before',
                    'quantity_after' => 'quantity_after',
                    'created_at' => 'created_at',
                ],
                references: [
                    'storage' => ['storage_id', Storage::class],
                    'product' => ['product_id', Product::class],
                    'user' => ['user_id', User::class],
                ],
                morphs: ['movable' => ['movable_type', 'movable_id']],
                inSnapshot: false,
            ),
        ];
    }
}
