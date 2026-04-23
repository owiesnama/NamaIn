# Plan: POS Walk-in Customer + Invoices Page + Session Reconciliation

## Context

Three interconnected problems identified during review:

1. **Walk-in customer leaks everywhere** — POS creates a `Walk-in Customer` record with no system flag, so it appears in the customer list (editable), in the sales index (mixed with real invoices), and in customer dropdowns across the app.
2. **POS invoices mixed with sales invoices** — The sales index shows both B2B invoiced sales and POS counter sales with no distinction. A separate page is needed.
3. **Session reconciliation is invisible** — `opening_float`, `closing_float`, `cashSalesTotal()`, `expectedClosingFloat()`, and `variance()` all exist on the model but are never surfaced to the cashier or manager. The close modal is a single input field with zero context.

---

## Business Value

- Cashiers are accountable from minute one — opening float is acknowledged per shift
- End-of-session reconciliation shows expected vs actual cash — discrepancies are caught immediately, not weeks later during accounting
- Session-level audit trail: who opened, who closed, what the variance was
- POS revenue is separated from B2B sales — management can see each channel independently
- Walk-in customer is hidden from operational views — no confusion for staff

---

## Layer 1 — Database (1 file)

### 1. Migration — `add_is_system_to_customers`

Add `is_system` boolean (default `false`) to the `customers` table.

In `up()`, also update existing walk-in customer records:
```php
DB::table('customers')
    ->where('name', 'Walk-in Customer')
    ->update(['is_system' => true]);
```

---

## Layer 2 — Models (2 files)

### 2. `app/Models/Customer.php`

Add `is_system` to `casts()`:
```php
'is_system' => 'boolean',
```

### 3. `app/Models/Invoice.php`

Add two scopes:
```php
public function scopeFromPos(Builder $builder): Builder
{
    return $builder->whereNotNull('pos_session_id');
}

public function scopeNotFromPos(Builder $builder): Builder
{
    return $builder->whereNull('pos_session_id');
}
```

---

## Layer 3 — Actions (1 file)

### 4. `app/Actions/Pos/ProcessPosCheckoutAction.php`

Update `firstOrCreate` for walk-in customer to include `is_system: true` in the defaults:
```php
$customerId = Customer::firstOrCreate(
    ['name' => 'Walk-in Customer', 'tenant_id' => $session->tenant_id],
    ['address' => 'N/A', 'phone_number' => 'N/A', 'is_system' => true]
)->id;
```

---

## Layer 4 — Policy (1 file)

### 5. `app/Policies/CustomerPolicy.php`

Block `update()` and `delete()` for system customers:
```php
public function update(User $user, Customer $customer): bool
{
    return ! $customer->is_system;
}

public function delete(User $user, Customer $customer): bool
{
    return ! $customer->is_system;
}
```

---

## Layer 5 — Filters (1 file)

### 6. `app/Filters/PosInvoiceFilter.php` (new)

Extends base `Filters` class. Handles:
- `search` — by serial number or customer name
- `session_id` — filter by a specific POS session
- `from_date` / `to_date` — date range
- `customer_type` — `walk_in` (`is_system = true`) vs `named` (`is_system = false`)

---

## Layer 6 — Controllers (5 files)

### 7. `app/Http/Controllers/Contacts/CustomersController.php`

Two changes:
- `index()`: add `->where('is_system', false)` to exclude walk-in customer from the list
- `update()`: add `$this->authorize('update', $customer)` — policy handles the is_system guard

### 8. `app/Http/Controllers/Api/CustomersController.php`

Add `->where('is_system', false)` to the search query so the walk-in customer never appears in dropdowns across the app (invoices, payments, cheques).

### 9. `app/Http/Controllers/Sales/SalesController.php`

Two changes:
- `index()`: add `->notFromPos()` scope to exclude POS invoices from the regular sales list
- `pos()`: add `session_stats` prop for the reconciliation close modal:

```php
'session_stats' => [
    'opening_float'          => $session->opening_float / 100,
    'cash_sales_total'       => $session->cashSalesTotal() / 100,
    'expected_closing_float' => $session->expectedClosingFloat() / 100,
],
```

### 10. `app/Http/Controllers/Sales/PosInvoicesController.php` (new)

Single `index()` method returning `Pos/Invoices` Inertia page with:

```php
public function index(PosInvoiceFilter $filter)
{
    return inertia('Pos/Invoices', [
        // Individual POS transactions
        'invoices' => Invoice::fromPos()
            ->filter($filter)
            ->with(['invocable', 'posSession.storage', 'posSession.openedBy'])
            ->latest()
            ->paginate(15)
            ->withQueryString(),

        // Session-level reconciliation
        'sessions' => PosSession::with(['openedBy', 'closedBy', 'storage'])
            ->latest()
            ->paginate(15)
            ->through(fn ($session) => [
                ...$session->toArray(),
                'opening_float'          => $session->opening_float / 100,
                'closing_float'          => $session->closing_float ? $session->closing_float / 100 : null,
                'cash_sales_total'       => $session->cashSalesTotal() / 100,
                'expected_closing_float' => $session->expectedClosingFloat() / 100,
                'variance'               => $session->closing_float ? $session->variance() / 100 : null,
                'invoice_count'          => $session->invoices()->count(),
            ]),

        // Filter options
        'pos_sessions'  => PosSession::with('storage')->latest()->get(['id', 'storage_id', 'created_at']),
        
        // Summary stats
        'stats' => [
            'total_revenue'        => Invoice::fromPos()->sum('total') / 100,
            'total_sales_count'    => Invoice::fromPos()->count(),
            'walk_in_count'        => Invoice::fromPos()->whereHasMorph('invocable', [Customer::class], fn ($q) => $q->where('is_system', true))->count(),
            'named_customer_count' => Invoice::fromPos()->whereHasMorph('invocable', [Customer::class], fn ($q) => $q->where('is_system', false))->count(),
        ],
    ]);
}
```

### 11. `app/Http/Controllers/Sales/PosController.php`

`checkout()`: on the Inertia redirect, flash the invoice ID:
```php
return redirect()->route('pos.index')->with([
    'success'         => __('Checkout successful.'),
    'last_invoice_id' => $invoice->id,
]);
```

---

## Layer 7 — Routes (1 file)

### 12. `routes/tenant.php`

Add alongside existing POS routes:
```php
Route::get('/pos/invoices', [PosInvoicesController::class, 'index'])->name('pos.invoices');
```

---

## Layer 8 — Frontend (3 files)

### 13. `resources/js/Pages/Pos/Invoices.vue` (new)

Two-tab page:

**Tab 1 — Transactions**
- Stats row: Total Revenue | Total Sales | Walk-in | Named Customer
- Inline filter bar: date range, session selector, customer type, search
- Table: Date | Invoice # | Customer (Walk-in badge for `is_system`) | Session | Total | Actions (View, Print Receipt)
- Pagination

**Tab 2 — Sessions**
- Table: Opened | Cashier | Storage | Sales Count | Cash Sales | Opening Float | Expected Closing | Actual Closing | Variance | Status
- Variance column color-coded: green (zero/positive), red (negative)
- Open sessions show "Active" badge and dashes for closing columns

### 14. `resources/js/Pages/Pos/Session.vue`

Three targeted changes:

**A — Replace `alert(__('Transaction Successful'))` with a Sale Complete modal**

Triggered in `onSuccess` when `page.props.flash.last_invoice_id` is present:
- Shows total amount
- Shows invoice number
- "Print Receipt" button → `window.open(route('invoices.print', lastInvoiceId), '_blank')`
- "New Sale" button → closes modal, resets cart

**B — Replace `alert(__('Some items are completely unavailable.'))` with a proper modal**

`unavailableProducts` ref is already populated — just needs a modal to display it. Mirrors the existing transfer modal pattern. Shows which products are unavailable and how many were needed.

**C — Replace `alert(errorMsg)` with an inline error banner**

Small dismissible error banner inside the cart sidebar, styled with red status colors from the design system.

**D — Enrich the close modal with reconciliation**

Pass `session_stats` prop and display:

```
Opening Float              500.00 SDG
Cash Sales This Session  1,240.00 SDG
─────────────────────────────────────
Expected Closing         1,740.00 SDG

Actual Cash in Hand    [          ] SDG

Variance                  +20.00 SDG  ✓   ← live as user types, color-coded
```

### 15. `resources/js/Layouts/AppLayout.vue`

Add "POS History" nav link directly below the existing POS link:
```html
<NavLink
    :href="route('pos.invoices')"
    :active="route().current('pos.invoices')"
>
    <!-- receipt/history icon -->
    <span class="mx-2 text-sm font-medium" v-text="__('POS History')"></span>
</NavLink>
```

---

## File Summary

| # | File | Type |
|---|---|---|
| 1 | `database/migrations/..._add_is_system_to_customers.php` | New |
| 2 | `app/Models/Customer.php` | Modified |
| 3 | `app/Models/Invoice.php` | Modified |
| 4 | `app/Actions/Pos/ProcessPosCheckoutAction.php` | Modified |
| 5 | `app/Policies/CustomerPolicy.php` | Modified |
| 6 | `app/Filters/PosInvoiceFilter.php` | New |
| 7 | `app/Http/Controllers/Contacts/CustomersController.php` | Modified |
| 8 | `app/Http/Controllers/Api/CustomersController.php` | Modified |
| 9 | `app/Http/Controllers/Sales/SalesController.php` | Modified |
| 10 | `app/Http/Controllers/Sales/PosInvoicesController.php` | New |
| 11 | `app/Http/Controllers/Sales/PosController.php` | Modified |
| 12 | `routes/tenant.php` | Modified |
| 13 | `resources/js/Pages/Pos/Invoices.vue` | New |
| 14 | `resources/js/Pages/Pos/Session.vue` | Modified |
| 15 | `resources/js/Layouts/AppLayout.vue` | Modified |

**15 files total — 4 new, 11 modified**

---

## Implementation Order

Build in this sequence to avoid broken states:

1. Migration (#1) — must run before any model/controller changes
2. Model updates (#2, #3) — before controllers that depend on them
3. Action update (#4) + Policy (#5) — independent, can be done together
4. Filter (#6) + Controllers (#7–#11) — backend complete
5. Routes (#12) — wire up new controller
6. Frontend (#13–#15) — build against working backend
