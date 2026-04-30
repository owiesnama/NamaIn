# Financial & Accounting System Audit Report
## Islamic Finance Compliance & Accounting Principles Review

**System:** NamaIn (Multi-tenant Inventory & Accounting Platform)
**Date:** 2026-04-30
**Scope:** Sales, Purchases, Payments, Cheques, Expenses, Inventory, Treasury, Reporting

---

## PART 1: SYSTEM ARCHITECTURE SUMMARY

Your system is a **transaction-based commercial management system** -- not a full double-entry accounting/GL system. It manages:

| Module | Status |
|--------|--------|
| Sales Invoicing | Implemented |
| Purchase Invoicing | Implemented |
| Payments (Cash, Cheque, Bank, Mixed) | Implemented |
| Cheque Management (Receivable & Payable) | Implemented |
| Expenses & Recurring Expenses | Implemented |
| Treasury/Cash Management | Implemented |
| Inventory (Weighted Average Cost) | Implemented |
| Customer/Supplier Accounts | Implemented |
| Customer Advances | Implemented |
| POS Sessions | Implemented |
| P&L Reporting | Implemented (basic) |
| Double-Entry Bookkeeping / General Ledger | **NOT Implemented** |
| Tax/VAT/Zakat | **NOT Implemented** |
| Interest/Late Fees/Penalties | **NOT Implemented** (Sharia-positive) |

---

## PART 2: SHARIA COMPLIANCE ASSESSMENT

### What is Already Compliant

**1. Zero Interest (Riba-Free) -- COMPLIANT**
The system has **no interest calculation, late fees, or penalty charges** anywhere in the codebase. This is fundamentally aligned with the Sharia prohibition of Riba. No financing charges are applied to overdue balances.

**2. Real Asset-Backed Transactions -- COMPLIANT**
All sales and purchases are tied to physical products with real inventory. Transactions represent actual goods changing hands (`Transaction` model links to `Product`, `Storage`, quantities). This aligns with the Sharia principle that trade must involve real assets (Bay' al-Sila).

**3. No Speculative Trading (Gharar-Free) -- COMPLIANT**
The system records concrete quantities, prices, and delivery statuses. No futures, derivatives, or speculative instruments exist. All transactions have clear terms (price, quantity, product, delivery status).

**4. Cheque System -- COMPLIANT**
Cheques represent real obligations tied to actual invoices and payables -- no discounting of cheques (which would constitute Riba).

**5. Customer Advances -- COMPLIANT**
Customer advances (`CustomerAdvance` model) track prepayments as real cash movements, not as interest-bearing loans. Settlement reduces the advance balance directly.

### Sharia Compliance Gaps & Risks

**GAP 1: No Zakat Calculation Module -- MAJOR**
- There is no mechanism to calculate Zakat al-Mal (wealth tax at 2.5%) on inventory, cash, or receivables.
- In Islamic accounting, Zakat is a mandatory obligation calculated on:
  - Inventory value (at cost or market value, whichever is lower)
  - Cash in treasury accounts
  - Net receivables (amounts owed by customers)
  - Minus: payables owed to suppliers
- **Recommendation:** Add a Zakat calculation report using the formula:
  ```
  Zakatable Base = Inventory Value + Cash Balances + Net Receivables - Payables
  Zakat Due = Zakatable Base * 2.5% (if above Nisab threshold)
  ```

**GAP 2: No Prohibition Enforcement on Haram Goods -- MEDIUM**
- The product/category system has no mechanism to flag or prevent trading in Sharia-prohibited goods (alcohol, pork products, gambling-related items, etc.).
- **Recommendation:** Add a `sharia_compliant` boolean or category-level flag to enforce product compliance.

**GAP 3: No Profit-Sharing (Mudarabah/Musharakah) Tracking -- LOW**
- If the business operates with Islamic financing partners, there's no mechanism to track profit-sharing ratios or distribute profits according to Mudarabah/Musharakah agreements.
- Only relevant if the business has such partnerships.

---

## PART 3: ACCOUNTING PRINCIPLES ASSESSMENT

### 3.1 Account Balance Formula -- HAS A CRITICAL ISSUE

**Current formula** (`HasAccountBalance.php:32`):
```
Balance = InvoicedTotal - TotalPaid - OpeningBalance
```

**The Problem:** The `opening_balance` is **subtracted**, meaning a positive opening balance **reduces** the calculated balance. This creates an inconsistency:

- For a **Customer** with `opening_balance = 1000` (they owed you 1000 before the system started): The formula subtracts 1000, making their balance appear *lower*, when it should be *higher*.
- For a **Supplier** with `opening_balance = 500` (you owed them 500): Same subtraction, which happens to work correctly for suppliers but for the wrong reason.

**The formula treats opening_balance as "already paid"**, which is semantically confusing. If a customer owed you 5000 before the system and you enter `opening_balance = 5000`, the system deducts 5000 from their running balance -- effectively treating it as a credit, not a debit.

**Recommendation:** Clarify the semantics. The `opening_balance` should either:
- Be **added** for customers (they owe you more) and **subtracted** for suppliers (you owe them more), OR
- Be explicitly labeled as `opening_credit` vs `opening_debit` to remove ambiguity

### 3.2 Dual Counting Risk in getCurrentTotalPaid -- CRITICAL

**Current code** (`HasAccountBalance.php:75-84`):
```php
$directSettling = $this->payments()->where('direction', $settling)->sum('amount');
$invoicePayments = $this->invoices()->sum('paid_amount');
return ($directSettling + $invoicePayments) - $directReversing;
```

**The Problem:** When a payment is recorded via `Invoice::recordPayment()`, it:
1. Creates a `Payment` record linked to both the invoice AND the payable (customer/supplier)
2. Updates `invoice.paid_amount`

In `getCurrentTotalPaid()`:
- `$directSettling` sums payments on the **payable** (customer) -- which includes invoice-linked payments
- `$invoicePayments` sums `paid_amount` from invoices -- which reflects the same payments

**This could double-count invoice payments** if a payment is both linked to an invoice AND linked directly to the customer as payable. The `getTotalPaidAsOf()` method handles this differently (separating direct vs invoice payments), suggesting the two methods may produce different results for the same data.

**Recommendation:** Audit and unify the two calculation paths. Use the same approach in both `getCurrentTotalPaid()` and `getTotalPaidAsOf()` to ensure consistency.

### 3.3 Gross Profit Formula on Dashboard -- INCORRECT

**Current formula** (`DashboardStatsQuery.php:31-35`):
```php
grossProfit = totalSales - totalPurchase - expensesThisMonth + totalInventoryValue
```

**Problems:**
1. **Mixing time periods:** `totalSales` and `totalPurchase` are last 30 days, but `totalInventoryValue` is current total. This conflates a period figure with a point-in-time figure.
2. **Adding inventory value to gross profit is wrong.** Gross Profit = Revenue - COGS. Inventory value is a balance sheet item, not an income statement item.
3. **The P&L Report does it correctly** (`ProfitAndLossQuery.php`): `Gross Profit = Revenue - COGS`, where COGS = `SUM(base_quantity * unit_cost)` for delivered sales. The dashboard contradicts this.

**Recommendation:** Fix the dashboard formula:
```php
// Correct:
grossProfit = totalSalesRevenue - totalSalesCOGS
netProfit = grossProfit - expenses
```

### 3.4 COGS Calculation -- PARTIALLY CORRECT

**P&L Report** (`ProfitAndLossQuery.php:48-56`):
```php
COGS = SUM(transactions.base_quantity * COALESCE(transactions.unit_cost, 0))
```

**Issues:**
- `COALESCE(unit_cost, 0)` -- if `unit_cost` is NULL (no cost recorded on the transaction), COGS for that line becomes **zero**. This silently understates COGS and overstates profit.
- The `unit_cost` is captured at **purchase time** on purchase transactions, but for sales transactions, `unit_cost` must be populated from somewhere (likely copied from the product's average cost at sale time). If this isn't happening, COGS will be zero for many items.

**Recommendation:**
- Ensure `unit_cost` is **always populated** on sales transactions at the time of sale (snapshot of weighted average cost)
- Add a validation or warning when `unit_cost` is NULL on delivered sales transactions
- Consider a periodic reconciliation report that flags sales transactions with zero/null cost

### 3.5 Expense Status Inconsistency -- BUG

**Dashboard** (`DashboardStatsQuery.php:132-138`):
```php
$q->where('status', ExpenseStatus::Approved)
    ->orWhereNull('status');
```

**P&L Report** (`ProfitAndLossQuery.php:58`):
```php
Expense::where('status', ExpenseStatus::Approved)
```

The dashboard includes expenses with **NULL status** (unapproved), but the P&L report only includes **Approved** expenses. This means the dashboard and P&L report will show different expense totals for the same period.

**Recommendation:** Decide on one policy -- either all expenses require approval or they don't -- and apply it consistently.

### 3.6 No Double-Entry Bookkeeping -- FUNDAMENTAL GAP

The system lacks a proper **General Ledger (GL)** with:
- Chart of Accounts (Assets, Liabilities, Equity, Revenue, Expenses)
- Journal entries with balanced debits and credits
- Trial Balance
- Balance Sheet generation

**Impact:**
- Cannot produce a proper Balance Sheet
- Cannot verify the fundamental accounting equation: `Assets = Liabilities + Equity`
- Treasury movements are a partial substitute but don't cover all accounts
- Receivables/Payables are tracked but not in a GL framework

**Recommendation:** This is the single largest accounting gap. For a business of any scale, implementing a GL layer is essential. The treasury movement system is a good foundation -- it already tracks movements with reasons and amounts. Extending this to a full chart of accounts would enable:
- Automated journal entries from invoices, payments, and expenses
- Trial balance verification
- Full Balance Sheet and Income Statement generation
- Audit readiness

---

## PART 4: MODULE-BY-MODULE FINDINGS

### 4.1 Sales Module

| Aspect | Status | Notes |
|--------|--------|-------|
| Invoice creation | Good | Polymorphic, multi-currency, idempotent |
| Line items (transactions) | Good | Base quantity conversion, unit pricing |
| Delivery tracking | Good | Partial delivery support, stock deduction |
| Returns/Credit notes | Good | Inverse invoices with parent linkage |
| Discount handling | Adequate | Invoice-level only, no line-item discounts |
| Payment status tracking | Good | Auto-updates based on payments |

**Findings:**
- **No line-item discounts**: Discounts are only at invoice level. For Sharia compliance, discounts should be transparent per item to avoid any ambiguity (Gharar).
- **No tax fields**: If the jurisdiction requires VAT or sales tax, there's no mechanism.
- **Revenue recognition**: Revenue is counted when transactions are delivered, which aligns with the **accrual principle** and the Sharia concept of **Qabd** (taking possession).

### 4.2 Purchases Module

| Aspect | Status | Notes |
|--------|--------|-------|
| Purchase invoicing | Good | Uses same Invoice model |
| Goods receipt | Good | Partial receipt via TransactionReceipt |
| Cost tracking | Good | unit_cost captured per transaction |
| Supplier payments | Good | Direction-aware payment system |

**Findings:**
- **Purchase returns** work via inverse invoices -- correct.
- **unit_cost reliability**: The system depends on `unit_cost` being set on purchase transactions. If not consistently populated, inventory valuation and COGS suffer.

### 4.3 Payments Module

| Aspect | Status | Notes |
|--------|--------|-------|
| Multi-method support | Good | Cash, Cheque, Bank Transfer, Mixed, Advance |
| Direction tracking | Good | In/Out for receivables/payables |
| Treasury integration | Good | Movements recorded |
| Receipt uploads | Good | Audit trail |

**Findings:**
- **Payment reason for outgoing supplier payments uses `ExpensePaid`** (`RecordPaymentAction.php:64`) -- this is semantically wrong. Paying a supplier is not an expense; it's settling a liability. Should be a distinct reason like `SupplierPayment`.
- **No payment approval workflow**: Any user can record payments. For Islamic finance governance, payments should have authorization controls.

### 4.4 Cheques Module

| Aspect | Status | Notes |
|--------|--------|-------|
| Receivable/Payable types | Good | Separate flows |
| Status lifecycle | Good | Drafted->Issued->Cleared/Returned |
| Partial clearing | Good | PartiallyCleared status |
| Clearing account | Good | ChequeClearing treasury type |
| Bounce handling | Good | Treasury reversal on bounce |

**Findings:**
- **Cheque clearing is well-implemented** with proper double-entry-style movements (debit clearing, credit bank).
- **No post-dated cheque maturity alerts**: The `upcomingCheques` query only looks 7 days ahead. For better cash flow management, extend this or add configurable alerts.
- **No cheque discounting** (selling cheques at a discount for early cash) -- this is **Sharia-compliant** since cheque discounting constitutes Riba.

### 4.5 Expenses Module

| Aspect | Status | Notes |
|--------|--------|-------|
| Category tracking | Good | Polymorphic categories with budgets |
| Approval workflow | Partial | Status field exists but enforcement is weak |
| Recurring expenses | Good | Auto-generation logic |
| Treasury impact | Good | Movements recorded |

**Findings:**
- **Budget tracking exists** but no enforcement (expenses can exceed category budgets without warnings/blocks).
- **Approval is optional** -- expenses with NULL status are counted on the dashboard.

### 4.6 Inventory Module

| Aspect | Status | Notes |
|--------|--------|-------|
| Stock tracking | Good | Per-storage quantities with locking |
| Valuation method | Good | Weighted Average Cost |
| Audit trail | Excellent | StockMovement logs everything |
| Transfer between locations | Good | Atomic transfers |
| Adjustments | Good | Tracked with before/after |
| Low stock alerts | Good | Configurable thresholds |

**Findings:**
- **Weighted Average Cost is Sharia-compliant** -- it's the most commonly accepted method in Islamic accounting (along with FIFO). Avoid LIFO as some scholars consider it problematic due to the disconnect between physical and cost flow.
- **No inventory write-downs**: If goods expire or are damaged, there's no formal write-down process that adjusts cost and records the loss. The adjustment system changes quantity but doesn't create an expense entry.
- **Expired goods tracking**: `expire_date` exists on Product but there's no automated check or report for expired inventory.

---

## PART 5: DETAILED RECOMMENDATIONS

### Must Fix (Critical)

**1. Fix the Dashboard Gross Profit Formula**
```php
// WRONG (current):
grossProfit = totalSales - totalPurchase - expenses + inventoryValue

// CORRECT:
grossProfit = totalSalesRevenue - totalSalesCOGS
netProfit = grossProfit - expenses
```
This is misleading stakeholders about profitability.

**2. Audit and Fix Account Balance Dual-Counting**
Unify `getCurrentTotalPaid()` and `getTotalPaidAsOf()` to use the same logic. Test with scenarios where payments exist both on invoices and directly on customers.

**3. Ensure unit_cost is Always Populated on Sales Transactions**
When a sale is recorded, snapshot the product's weighted average cost into `unit_cost`. Add a database constraint or validation to prevent NULL `unit_cost` on delivered sales transactions.

**4. Fix Expense Status Consistency**
Choose one approach: either require approval for all expenses or count all expenses. Apply the same filter in dashboard, P&L, and expense summary reports.

### Should Improve (High Priority)

**5. Implement a Zakat Calculation Report**
Build a report that calculates:
```
Zakatable Assets:
  + Total Inventory Value (at cost or market, lower)
  + Total Cash in Treasury Accounts
  + Total Receivables (outstanding customer balances)
  - Total Payables (outstanding supplier balances)
  = Zakatable Base

Zakat Due = Zakatable Base * 2.5% (if above Nisab)
```
All the underlying data already exists in your system.

**6. Add a Chart of Accounts / General Ledger Layer**
Your treasury movement system is already close to a journal entry system. Extend it:
- Define account types (Asset, Liability, Equity, Revenue, Expense)
- Map each treasury movement reason to GL accounts
- Generate a Trial Balance from the movement data
- Produce a Balance Sheet

**7. Rename Payment Reason for Supplier Payments**
Change from `ExpensePaid` to a dedicated `SupplierPaymentSettled` reason. Paying suppliers is not an expense -- it's settling a liability.

**8. Inventory Write-Down Process**
When adjusting inventory down (damage, expiry), automatically create an expense entry for the loss:
```
Loss Amount = Reduced Quantity * Average Cost
```
This ensures the P&L reflects the actual economic impact.

### Nice to Have (Medium Priority)

**9. Line-Item Discounts**
Add discount support at the transaction (line item) level, not just invoice level. This provides better transparency per Sharia requirements and more accurate reporting.

**10. Expired Inventory Report**
Add a scheduled check for products past their `expire_date` and flag them for write-down or disposal.

**11. Opening Balance Semantics**
Rename or document `opening_balance` clearly:
- For Customers: "Amount they owed us before the system" (should ADD to their balance)
- For Suppliers: "Amount we owed them before the system" (should ADD to their balance)
- Or split into `opening_debit` and `opening_credit`

**12. Payment Authorization**
Add an approval mechanism for payments above a certain threshold, aligning with Islamic governance principles (Hisbah).

**13. Multi-Currency with Exchange Rates**
The `currency` field exists throughout but there's no exchange rate table or conversion logic. If operating in multiple currencies, this will cause reporting inaccuracies.

---

## PART 6: SUMMARY SCORECARD

| Area | Score | Notes |
|------|-------|-------|
| **Riba (Interest) Free** | 10/10 | No interest anywhere |
| **Gharar (Uncertainty) Free** | 9/10 | Clear terms; line-item discounts would improve |
| **Real Asset Backing** | 10/10 | All trades backed by inventory |
| **Haram Goods Prevention** | 0/10 | No mechanism to flag prohibited goods |
| **Zakat Support** | 0/10 | Not implemented |
| **Treasury Management** | 8/10 | Strong; needs GL integration |
| **Inventory Accuracy** | 8/10 | Good audit trail; needs write-down process |
| **P&L Accuracy** | 5/10 | Dashboard formula wrong; COGS may have NULLs |
| **Balance Sheet Capability** | 2/10 | No GL, no formal balance sheet |
| **Account Balance Accuracy** | 6/10 | Potential dual-counting; opening_balance semantics |
| **Audit Readiness** | 7/10 | Good trails; lacks GL and formal reconciliation |

**Overall Assessment:** The system is fundamentally Sharia-compliant in its transaction design (no Riba, no speculation, real asset-backed). The primary gaps are in **accounting completeness** (no GL/double-entry) and **Islamic-specific features** (Zakat, Haram product filtering). The critical bugs in the gross profit formula and potential balance dual-counting should be fixed immediately as they affect financial accuracy.
