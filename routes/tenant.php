<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\Auth\MustChangePasswordController;
use App\Http\Controllers\Catalog\ProductExportController;
use App\Http\Controllers\Catalog\ProductImportController;
use App\Http\Controllers\Catalog\ProductsController;
use App\Http\Controllers\Contacts\CustomerAccountController;
use App\Http\Controllers\Contacts\CustomerExportController;
use App\Http\Controllers\Contacts\CustomerImportController;
use App\Http\Controllers\Contacts\CustomersController;
use App\Http\Controllers\Contacts\CustomerStatementController;
use App\Http\Controllers\Contacts\SupplierAccountController;
use App\Http\Controllers\Contacts\SupplierExportController;
use App\Http\Controllers\Contacts\SupplierImportController;
use App\Http\Controllers\Contacts\SuppliersController;
use App\Http\Controllers\Contacts\SupplierStatementController;
use App\Http\Controllers\Core\DashboardController;
use App\Http\Controllers\Core\GlobalSearchController;
use App\Http\Controllers\Core\PreferenceController;
use App\Http\Controllers\Core\TenantSelectionController;
use App\Http\Controllers\CustomerAdvancesController;
use App\Http\Controllers\Expenses\ExpenseApprovalController;
use App\Http\Controllers\Expenses\ExpenseExportController;
use App\Http\Controllers\Expenses\ExpenseReceiptController;
use App\Http\Controllers\Expenses\ExpensesController;
use App\Http\Controllers\Expenses\RecurringExpensesController;
use App\Http\Controllers\Expenses\RecurringExpenseStatusController;
use App\Http\Controllers\Exports;
use App\Http\Controllers\Inventory\StockAdditionController;
use App\Http\Controllers\Inventory\StockAdjustmentController;
use App\Http\Controllers\Inventory\StockDeductionController;
use App\Http\Controllers\Inventory\StockTransfersController;
use App\Http\Controllers\Inventory\StoragesController;
use App\Http\Controllers\Invoicing\InvoicePrintController;
use App\Http\Controllers\Invoicing\InvoicesController;
use App\Http\Controllers\Invoicing\InvoiceSearchController;
use App\Http\Controllers\Invoicing\PurchaseReturnController;
use App\Http\Controllers\Invoicing\SaleReturnController;
use App\Http\Controllers\Invoicing\TransactionDeliveryController;
use App\Http\Controllers\Payments\ChequePayeeInvoiceController;
use App\Http\Controllers\Payments\ChequesController;
use App\Http\Controllers\Payments\ChequeStatusController;
use App\Http\Controllers\Payments\PaymentsController;
use App\Http\Controllers\Purchases\PurchaseReceiptController;
use App\Http\Controllers\Purchases\PurchasesController;
use App\Http\Controllers\Reports;
use App\Http\Controllers\Sales\PosCheckoutController;
use App\Http\Controllers\Sales\PosInvoicesController;
use App\Http\Controllers\Sales\PosPreflightController;
use App\Http\Controllers\Sales\PosSessionController;
use App\Http\Controllers\Sales\SalesController;
use App\Http\Controllers\Treasury\TreasuryAccountsController;
use App\Http\Controllers\Treasury\TreasuryAdjustmentsController;
use App\Http\Controllers\Treasury\TreasuryTransfersController;
use App\Http\Controllers\Users\RoleController;
use App\Http\Controllers\Users\UserInvitationController;
use App\Http\Controllers\Users\UserManagementController;
use App\Http\Controllers\Users\UserRoleController;
use App\Http\Controllers\Users\UserStatusController;
use App\Http\Controllers\Utils\TemporaryUploadController;
use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Http\Middleware\EnsureTenantIsActive;
use App\Http\Middleware\EnsureUserIsActiveInTenant;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tenant Routes (Subdomain: {tenant}.domain.test)
|--------------------------------------------------------------------------
*/

Route::middleware([ResolveTenant::class])->group(function () {

    Route::post('/stop-impersonating', [ImpersonationController::class, 'stop'])
        ->name('impersonate.stop')
        ->middleware(['auth:sanctum', config('jetstream.auth_session')]);

    Route::middleware([
        'auth:sanctum',
        config('jetstream.auth_session'),
        'verified',
        EnsureTenantIsActive::class,
        EnsureUserIsActiveInTenant::class,
    ])->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Must Change Password
        |--------------------------------------------------------------------------
        | Force password change for users created directly by an admin.
        | These routes bypass the EnsurePasswordIsChanged middleware.
        */
        Route::get('/must-change-password', [MustChangePasswordController::class, 'show'])->name('password.change');
        Route::post('/must-change-password', [MustChangePasswordController::class, 'update'])->name('password.change.update');

    }); // end password-change-exempt group

    Route::middleware([
        'auth:sanctum',
        config('jetstream.auth_session'),
        'verified',
        EnsureTenantIsActive::class,
        EnsureUserIsActiveInTenant::class,
        EnsurePasswordIsChanged::class,
    ])->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Core
        |--------------------------------------------------------------------------
        | Dashboard, global search, and application-wide preferences.
        */
        Route::redirect('/', '/dashboard');
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/global-search', GlobalSearchController::class)->name('global-search');
        Route::get('/preferences', [PreferenceController::class, 'index'])->name('preferences.index');
        Route::post('/preferences', [PreferenceController::class, 'update'])->name('preferences.update');
        Route::put('/preferences', [PreferenceController::class, 'update']);

        /*
        |--------------------------------------------------------------------------
        | Customers
        |--------------------------------------------------------------------------
        | Customer management including CRUD, account ledger, account statement,
        | and bulk import/export.
        */
        Route::get('/customers/export', [CustomerExportController::class, 'store'])->name('customers.export');
        Route::post('/customers/import', [CustomerImportController::class, 'store'])->name('customers.import');
        Route::get('/customers/import/sample', [CustomerImportController::class, 'show'])->name('customers.import.sample');
        Route::resource('/customers', CustomersController::class);
        Route::get('/customers/{customer}/account', [CustomerAccountController::class, 'show'])->name('customers.account');
        Route::get('/customers/{customer}/statement', [CustomerStatementController::class, 'show'])->name('customers.statement');
        Route::get('/customers/{customer}/statement/print', [CustomerStatementController::class, 'store'])->name('customers.print-statement');
        Route::post('/customers/{customer}/advances', [CustomerAdvancesController::class, 'store'])->name('customer-advances.store');
        Route::post('/customer-advances/{customerAdvance}/settle', [CustomerAdvancesController::class, 'destroy'])->name('customer-advances.settle');

        /*
        |--------------------------------------------------------------------------
        | Suppliers
        |--------------------------------------------------------------------------
        | Supplier management including CRUD, account ledger, account statement,
        | and bulk import/export.
        */
        Route::get('/suppliers/export', [SupplierExportController::class, 'store'])->name('suppliers.export');
        Route::post('/suppliers/import', [SupplierImportController::class, 'store'])->name('suppliers.import');
        Route::get('/suppliers/import/sample', [SupplierImportController::class, 'show'])->name('suppliers.import.sample');
        Route::resource('/suppliers', SuppliersController::class);
        Route::get('/suppliers/{supplier}/account', [SupplierAccountController::class, 'show'])->name('suppliers.account');
        Route::get('/suppliers/{supplier}/statement', [SupplierStatementController::class, 'show'])->name('suppliers.statement');
        Route::get('/suppliers/{supplier}/statement/print', [SupplierStatementController::class, 'store'])->name('suppliers.print-statement');

        /*
        |--------------------------------------------------------------------------
        | Products
        |--------------------------------------------------------------------------
        | Product catalog management including CRUD and bulk import/export.
        */
        Route::get('/products/export', [ProductExportController::class, 'store'])->name('products.export');
        Route::post('/products/import', [ProductImportController::class, 'store'])->name('products.import');
        Route::get('/products/import/sample', [ProductImportController::class, 'show'])->name('products.import.sample');
        Route::resource('/products', ProductsController::class);

        /*
        |--------------------------------------------------------------------------
        | Inventory & Stock
        |--------------------------------------------------------------------------
        | Storage locations, manual stock adjustments, add/deduct stock operations,
        | and inter-storage stock transfers.
        */
        Route::resource('/storages', StoragesController::class);
        Route::post('/storages/{storage}/adjust/{product}', [StockAdjustmentController::class, 'store'])->name('storages.adjust');
        Route::put('/stock/{storage}/add', [StockAdditionController::class, 'store'])->name('stock.add');
        Route::put('/stock/{storage}/deduct', [StockDeductionController::class, 'store'])->name('stock.deduct');

        Route::get('/stock-transfers', [StockTransfersController::class, 'index'])->name('stock-transfers.index');
        Route::get('/stock-transfers/create', [StockTransfersController::class, 'create'])->name('stock-transfers.create');
        Route::post('/stock-transfers', [StockTransfersController::class, 'store'])->name('stock-transfers.store');
        Route::get('/stock-transfers/{transfer}', [StockTransfersController::class, 'show'])->name('stock-transfers.show');

        /*
        |--------------------------------------------------------------------------
        | Purchases
        |--------------------------------------------------------------------------
        | Purchase invoices, goods receiving, and purchase return (credit notes).
        */
        Route::resource('/purchases', PurchasesController::class);
        Route::post('/purchases/receive/{transaction}', [PurchaseReceiptController::class, 'store'])->name('purchases.receive');
        Route::get('/purchases/{invoice}/return', [PurchaseReturnController::class, 'create'])->name('purchases.return.create');
        Route::post('/purchases/{invoice}/return', [PurchaseReturnController::class, 'store'])->name('purchases.return.store');

        /*
        |--------------------------------------------------------------------------
        | Sales & Point of Sale
        |--------------------------------------------------------------------------
        | Sales invoices, POS session management (open/checkout/close),
        | and sale return (credit notes).
        */
        Route::resource('/sales', SalesController::class);
        Route::get('/pos', [PosSessionController::class, 'show'])->name('pos.index');
        Route::get('/pos/invoices', [PosInvoicesController::class, 'index'])->name('pos.invoices');
        Route::post('/pos/open', [PosSessionController::class, 'store'])->name('pos.open');
        Route::post('/pos/preflight', [PosPreflightController::class, 'store'])->name('pos.preflight');
        Route::post('/pos/checkout', [PosCheckoutController::class, 'store'])->name('pos.checkout');
        Route::post('/pos/close', [PosSessionController::class, 'destroy'])->name('pos.close');
        Route::get('/sales/{invoice}/return', [SaleReturnController::class, 'create'])->name('sales.return.create');
        Route::post('/sales/{invoice}/return', [SaleReturnController::class, 'store'])->name('sales.return.store');

        /*
        |--------------------------------------------------------------------------
        | Invoices & Transactions
        |--------------------------------------------------------------------------
        | Invoice printing, viewing, transaction delivery, and searching
        | invoices eligible for return.
        */
        Route::get('/invoice/print/{invoice}', [InvoicePrintController::class, 'show'])->name('invoices.print');
        Route::get('/invoice/show/{invoice}', [InvoicesController::class, 'show'])->name('invoices.show');
        Route::post('/transactions/{transaction}/deliver', [TransactionDeliveryController::class, 'store'])->name('transactions.deliver');
        Route::get('/invoices/search-for-return', [InvoiceSearchController::class, 'index'])->name('invoices.search-for-return');

        /*
        |--------------------------------------------------------------------------
        | Payments & Cheques
        |--------------------------------------------------------------------------
        | Payment records, cheque management, payee invoice lookup,
        | and cheque status updates.
        */
        Route::resource('/payments', PaymentsController::class);
        Route::resource('/cheques', ChequesController::class);
        Route::get('/payee-invoices', [ChequePayeeInvoiceController::class, 'index'])->name('cheques.payee-invoices');
        Route::put('/cheques/{cheque}/status', [ChequeStatusController::class, 'update'])->name('cheques.updateStatus');

        /*
        |--------------------------------------------------------------------------
        | Expenses
        |--------------------------------------------------------------------------
        | One-time and recurring expense management, expense approval workflow,
        | receipt viewing, and bulk export.
        */
        Route::resource('/recurring-expenses', RecurringExpensesController::class);
        Route::put('/recurring-expenses/{recurring_expense}/toggle', [RecurringExpenseStatusController::class, 'update'])->name('recurring-expenses.toggle');
        Route::get('/expenses/export', [ExpenseExportController::class, 'store'])->name('expenses.export');
        Route::resource('/expenses', ExpensesController::class);
        Route::put('/expenses/{expense}/approval', [ExpenseApprovalController::class, 'update'])->name('expenses.approval');
        Route::get('/expenses/{expense}/receipt', [ExpenseReceiptController::class, 'show'])->name('expenses.receipt');

        /*
        |--------------------------------------------------------------------------
        | Treasury
        |--------------------------------------------------------------------------
        | Treasury accounts, movement ledger, inter-account transfers,
        | and manual balance adjustments.
        */
        Route::get('/treasury', [TreasuryAccountsController::class, 'index'])->name('treasury.index');
        Route::get('/treasury/create', [TreasuryAccountsController::class, 'create'])->name('treasury.create');
        Route::post('/treasury', [TreasuryAccountsController::class, 'store'])->name('treasury.store');
        Route::get('/treasury/transfer', [TreasuryTransfersController::class, 'create'])->name('treasury.transfer.create');
        Route::post('/treasury/transfer', [TreasuryTransfersController::class, 'store'])->name('treasury.transfer.store');
        Route::get('/treasury/transfer/{transfer}', [TreasuryTransfersController::class, 'show'])->name('treasury.transfer.show');
        Route::get('/treasury/{treasury}', [TreasuryAccountsController::class, 'show'])->name('treasury.show');
        Route::get('/treasury/{treasury}/edit', [TreasuryAccountsController::class, 'edit'])->name('treasury.edit');
        Route::put('/treasury/{treasury}', [TreasuryAccountsController::class, 'update'])->name('treasury.update');
        Route::post('/treasury/{treasury}/adjust', [TreasuryAdjustmentsController::class, 'store'])->name('treasury.adjust');

        /*
        |--------------------------------------------------------------------------
        | User Management
        |--------------------------------------------------------------------------
        | Invite, enable/disable, assign roles, and remove team members.
        */
        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
        Route::post('/users/invite', [UserInvitationController::class, 'store'])->name('users.invite');
        Route::delete('/users/invitations/{invitation}', [UserInvitationController::class, 'destroy'])->name('users.invitations.cancel');
        Route::put('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');
        Route::put('/users/{user}/role', [UserRoleController::class, 'update'])->name('users.assign-role');
        Route::put('/users/{user}/toggle-status', [UserStatusController::class, 'update'])->name('users.toggle-status');
        Route::delete('/users/{user}', [UserManagementController::class, 'destroy'])->name('users.destroy');

        /*
        |--------------------------------------------------------------------------
        | Role Management
        |--------------------------------------------------------------------------
        | Create, update, and delete tenant-scoped roles with custom permissions.
        */
        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
        Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
        Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');

        /*
        |--------------------------------------------------------------------------
        | Exports
        |--------------------------------------------------------------------------
        | Queued export requests, history, and downloads.
        */
        Route::get('/exports', [Exports\ExportController::class, 'index'])->name('exports.index');
        Route::post('/exports', [Exports\ExportController::class, 'store'])->name('exports.store');
        Route::get('/exports/{exportLog}/download', [Exports\ExportController::class, 'download'])->name('exports.download');

        /*
        |--------------------------------------------------------------------------
        | Reports
        |--------------------------------------------------------------------------
        | Filterable report pages with export and print support.
        */
        Route::prefix('reports')->group(function () {
            Route::get('/', [Reports\ReportsIndexController::class, 'index'])->name('reports.index');
            Route::get('/sales', [Reports\SalesReportController::class, 'index'])->name('reports.sales');
            Route::get('/purchases', [Reports\PurchaseReportController::class, 'index'])->name('reports.purchases');
            Route::get('/pos-sessions', [Reports\PosSessionReportController::class, 'index'])->name('reports.pos-sessions');
            Route::get('/inventory-valuation', [Reports\InventoryValuationController::class, 'index'])->name('reports.inventory-valuation');
            Route::get('/customer-aging', [Reports\CustomerAgingController::class, 'index'])->name('reports.customer-aging');
            Route::get('/supplier-aging', [Reports\SupplierAgingController::class, 'index'])->name('reports.supplier-aging');
            Route::get('/treasury-reconciliation', [Reports\TreasuryReconciliationController::class, 'index'])->name('reports.treasury');
            Route::get('/expense-summary', [Reports\ExpenseSummaryController::class, 'index'])->name('reports.expenses');
            Route::get('/profit-and-loss', [Reports\ProfitAndLossController::class, 'index'])->name('reports.pnl');
        });

        /*
        |--------------------------------------------------------------------------
        | Utilities
        |--------------------------------------------------------------------------
        | Temporary file uploads and tenant switching.
        */
        Route::post('/uploads/tmp', [TemporaryUploadController::class, 'store'])->name('uploads.tmp.store');
        Route::delete('/uploads/tmp', [TemporaryUploadController::class, 'destroy'])->name('uploads.tmp.destroy');
        Route::post('/switch-tenant/{target}', [TenantSelectionController::class, 'switchFrom'])->name('tenant.switch');
    });
});
