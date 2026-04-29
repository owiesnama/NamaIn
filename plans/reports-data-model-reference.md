# Reports Module — Data Model Reference

## Invoice Model (`app/Models/Invoice.php`)

**Columns:** id, invocable_id, invocable_type, total (decimal:2), status (InvoiceStatus enum), payment_method (PaymentMethod enum), payment_status (PaymentStatus enum), paid_amount (decimal:2), discount (decimal:2), serial_number, pos_session_id, is_inverse, parent_invoice_id, currency, delivered (bool), created_at, deleted_at

**Type Differentiation:** `invocable_type = Customer::class` for sales, `Supplier::class` for purchases

**Relationships:** hasMany(Transaction), hasMany(Payment), morphTo(invocable), belongsTo(PosSession), belongsTo(parentInvoice)

**Helper Methods:**
- `getRemainingBalanceAttribute()` = total - discount - paid_amount
- `updatePaymentStatus()`
- `recordPayment()`
- `createInverseInvoice()`

**Scopes:** scopeForCustomer(), scopeForSupplier(), scopeOutstanding(), scopeDelivered(), scopeFromPos(), scopeNotFromPos()

---

## Transaction Model (`app/Models/Transaction.php`) — Line Items

**Columns:** id, invoice_id, product_id, storage_id, unit_id, quantity (int), base_quantity (int), price (double), unit_cost, currency, description, delivered (bool), delivered_at, fulfilled_from_storage_id, created_at

**Cost Storage:** unit_cost is stored on transaction (set on purchase add). Actual cost = base_quantity * unit_cost

**Helper Methods:**
- `total()` = quantity * price
- `getTotalCostAttribute()` = base_quantity * unit_cost
- `getTypeAttribute()` = "Sales" or "Purchases"

**Scopes:** scopeForCustomer(), scopeForSupplier(), scopeDelivered(), scopeTotalValue(), scopeFilterByType(), scopeForStorage(), scopeInDateRange()

---

## Payment Model (`app/Models/Payment.php`)

**Columns:** id, invoice_id, payable_id, payable_type, amount (decimal:15,2), payment_method (PaymentMethod enum), direction (PaymentDirection enum: 'in'/'out'), reference, notes, paid_at (datetime), treasury_account_id, created_by, metadata (json), receipt_path, currency

**Helper Methods:** isIncoming() (direction = 'in'), isOutgoing() (direction = 'out')

**Balance Logic:** Customer payments direction 'in' settle their debt; supplier payments direction 'out' settle their debt

---

## POS Session Model (`app/Models/PosSession.php`)

**Columns:** id, storage_id, opened_by, closed_by, opening_float (integer, in cents), closing_float (integer), closed_at (datetime)

**Returns (integers — in cents):**
- `cashSalesTotal()` = sum of invoices with payment_method='cash' total
- `expectedClosingFloat()` = opening_float + cashSalesTotal()
- `variance()` = closing_float - expectedClosingFloat()

**Relationships:** belongsTo(Storage), hasMany(Invoice)

---

## Cheque Model (`app/Models/Cheque.php`)

**Columns:** id, amount, reference_number, due (datetime), status (ChequeStatus enum: Drafted, Issued, Bounced, Cleared, Cancelled), type (ChequeType enum: Receivable/Payable), bank, bank_id, chequeable_id, chequeable_type, invoice_id, notes

**Scopes:** scopeReceivable(), scopePayable(), scopeOverdue() (due < now AND not Cleared/Cancelled)

---

## Expense Model (`app/Models/Expense.php`)

**Columns:** id, title, amount (decimal:2), currency, expensed_at (datetime), treasury_account_id, status (ExpenseStatus enum: Pending/Approved/Rejected), approved_by, approved_at, created_by, notes, recurring_expense_id

**Relationships:** belongsTo(TreasuryAccount), morphToMany(Category), belongsTo(RecurringExpense), belongsTo(createdBy)

---

## Recurring Expense Model (`app/Models/RecurringExpense.php`)

**Columns:** amount (decimal:2), frequency (daily/weekly/monthly/yearly), starts_at (date), ends_at (date), is_active (bool), last_generated_at (datetime)

**Methods:** isDue(), nextDueDate() — generates Expense records via scheduled command

---

## Stock & Stock Movement Models

**Stock (Pivot):** product_id, storage_id, quantity, appends: average_cost, totalCost
- `getAverageCostAttribute()` = sum of (delivered purchase transactions x unit_cost) / delivered quantity
- `getTotalCostAttribute()` = quantity * average_cost

**StockMovement:** id, product_id, storage_id, quantity, reason, movable_id, movable_type, created_by

---

## Treasury Models

**TreasuryAccount:** id, name, type (TreasuryAccountType enum: Cash/Bank), opening_balance (integer, cents), sale_point_id, bank_id, is_active
- `currentBalance()` = opening_balance + sum(movements.amount)
- Scopes: scopeActive(), scopeShared(), scopeForSalePoint(), scopeOfType()

**TreasuryMovement:** id, treasury_account_id, amount (integer, cents), balance_after, reason (TreasuryMovementReason enum), movable_id, movable_type, occurred_at

**TreasuryTransfer:** from_account_id, to_account_id, amount (integer), transferred_at

---

## Customer & Supplier Models

Both use traits: HasAccountBalance, HasPaymentHistory

**Columns:** id, name, opening_balance (decimal:2), credit_limit, phone_number, address, currency

**Methods:**
- `calculateAccountBalance(?$asOfDate)` = sum(invoices) - sum(payments) - opening_balance
- `getPaymentHistory()` = Collection of all Payments (via invoice or direct payable)
- `getUnpaidInvoices()`

**Relationships:** morphMany(Invoice), morphMany(Payment), morphMany(Cheque), morphToMany(Category)

---

## Amount Storage Format

- **Decimals (cents):** Invoice total, discount, paid_amount; Payment amount; Expense amount; Customer/Supplier opening_balance, credit_limit — all cast to decimal:2
- **Integers (cents):** Treasury movements, transfers, account balances — cast to integer
- **Doubles (legacy):** Transaction price (double); Invoice total in base migration (but newer migrations use decimal)

---

## Enums (`app/Enums/`)

InvoiceStatus: Delivered, PartiallyDelivered, Initial, Returned, Pending
PaymentStatus: Unpaid, PartiallyPaid, Paid, Overdue
PaymentMethod, PaymentDirection (In/Out), ChequeStatus, ChequeType, ExpenseStatus, TreasuryAccountType, TreasuryMovementReason

---

## Key Query Patterns for Reports

- **Sales/Purchase separation:** Filter by `invoice.invocable_type`
- **POS vs B2B:** `invoice.pos_session_id IS NOT NULL` (POS) vs `IS NULL` (B2B)
- **Stock cost valuation:** Use `Stock::average_cost` attribute (historical FIFO-like calculation)
- **Account aging:** Group outstanding invoices by `datediff(now(), created_at)` buckets
- **Treasury reconciliation:** Group TreasuryMovement by treasury_account_id, sum amount, check balance_after
