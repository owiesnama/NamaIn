<?php

declare(strict_types=1);

use App\Http\Controllers\Catalog\ProductsController;
use App\Http\Controllers\Contacts\CustomersController;
use App\Http\Controllers\Contacts\SuppliersController;
use App\Http\Controllers\Core\DashboardController;
use App\Http\Controllers\Core\GlobalSearchController;
use App\Http\Controllers\Core\PreferenceController;
use App\Http\Controllers\Core\TenantSelectionController;
use App\Http\Controllers\Expenses\ExpenseApprovalController;
use App\Http\Controllers\Expenses\ExpenseReceiptController;
use App\Http\Controllers\Expenses\ExpensesController;
use App\Http\Controllers\Expenses\RecurringExpensesController;
use App\Http\Controllers\Inventory\StockController;
use App\Http\Controllers\Inventory\StockTransfersController;
use App\Http\Controllers\Inventory\StoragesController;
use App\Http\Controllers\Invoicing\InverseInvoicesController;
use App\Http\Controllers\Invoicing\InvoicesController;
use App\Http\Controllers\Payments\ChequesController;
use App\Http\Controllers\Payments\ChequeStatusController;
use App\Http\Controllers\Payments\PaymentsController;
use App\Http\Controllers\Purchases\PurchasesController;
use App\Http\Controllers\Sales\PosController;
use App\Http\Controllers\Sales\PosInvoicesController;
use App\Http\Controllers\Sales\SalesController;
use App\Http\Controllers\Utils\TemporaryUploadController;
use App\Http\Middleware\EnsureTenantIsActive;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tenant Routes (Subdomain: {tenant}.domain.test)
|--------------------------------------------------------------------------
*/

Route::middleware([ResolveTenant::class])->group(function () {

    Route::middleware([
        'auth:sanctum',
        config('jetstream.auth_session'),
        'verified',
        EnsureTenantIsActive::class,
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
        Route::get('/customers/export', [CustomersController::class, 'export'])->name('customers.export');
        Route::post('/customers/import', [CustomersController::class, 'import'])->name('customers.import');
        Route::get('/customers/import/sample', [CustomersController::class, 'importSample'])->name('customers.import.sample');
        Route::resource('/customers', CustomersController::class);
        Route::get('/customers/{customer}/account', [CustomersController::class, 'account'])->name('customers.account');
        Route::get('/customers/{customer}/statement', [CustomersController::class, 'statement'])->name('customers.statement');
        Route::get('/customers/{customer}/statement/print', [CustomersController::class, 'printStatement'])->name('customers.print-statement');

        /*
        |--------------------------------------------------------------------------
        | Suppliers
        |--------------------------------------------------------------------------
        | Supplier management including CRUD, account ledger, account statement,
        | and bulk import/export.
        */
        Route::get('/suppliers/export', [SuppliersController::class, 'export'])->name('suppliers.export');
        Route::post('/suppliers/import', [SuppliersController::class, 'import'])->name('suppliers.import');
        Route::get('/suppliers/import/sample', [SuppliersController::class, 'importSample'])->name('suppliers.import.sample');
        Route::resource('/suppliers', SuppliersController::class);
        Route::get('/suppliers/{supplier}/account', [SuppliersController::class, 'account'])->name('suppliers.account');
        Route::get('/suppliers/{supplier}/statement', [SuppliersController::class, 'statement'])->name('suppliers.statement');
        Route::get('/suppliers/{supplier}/statement/print', [SuppliersController::class, 'printStatement'])->name('suppliers.print-statement');

        /*
        |--------------------------------------------------------------------------
        | Products
        |--------------------------------------------------------------------------
        | Product catalog management including CRUD and bulk import/export.
        */
        Route::get('/products/export', [ProductsController::class, 'export'])->name('products.export');
        Route::post('/products/import', [ProductsController::class, 'import'])->name('products.import');
        Route::get('/products/import/sample', [ProductsController::class, 'importSample'])->name('products.import.sample');
        Route::resource('/products', ProductsController::class);

        /*
        |--------------------------------------------------------------------------
        | Inventory & Stock
        |--------------------------------------------------------------------------
        | Storage locations, manual stock adjustments, add/deduct stock operations,
        | and inter-storage stock transfers.
        */
        Route::resource('/storages', StoragesController::class);
        Route::post('/storages/{storage}/adjust/{product}', [StoragesController::class, 'adjust'])->name('storages.adjust');
        Route::put('/stock/{storage}/add', [StockController::class, 'add'])->name('stock.add')->can('manageStock', 'storage');
        Route::put('/stock/{storage}/deduct', [StockController::class, 'deduct'])->name('stock.deduct')->can('manageStock', 'storage');

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
        Route::post('/purchases/receive/{transaction}', [PurchasesController::class, 'receive'])->name('purchases.receive');
        Route::get('/purchases/{invoice}/return', [InverseInvoicesController::class, 'createPurchaseReturn'])->name('purchases.return.create');
        Route::post('/purchases/{invoice}/return', [InverseInvoicesController::class, 'storePurchaseReturn'])->name('purchases.return.store');

        /*
        |--------------------------------------------------------------------------
        | Sales & Point of Sale
        |--------------------------------------------------------------------------
        | Sales invoices, POS session management (open/checkout/close),
        | and sale return (credit notes).
        */
        Route::resource('/sales', SalesController::class);
        Route::get('/pos', [SalesController::class, 'pos'])->name('pos.index');
        Route::get('/pos/invoices', [PosInvoicesController::class, 'index'])->name('pos.invoices');
        Route::post('/pos/open', [PosController::class, 'open'])->name('pos.open');
        Route::post('/pos/checkout', [PosController::class, 'checkout'])->name('pos.checkout');
        Route::post('/pos/close', [PosController::class, 'close'])->name('pos.close');
        Route::get('/sales/{invoice}/return', [InverseInvoicesController::class, 'createSaleReturn'])->name('sales.return.create');
        Route::post('/sales/{invoice}/return', [InverseInvoicesController::class, 'storeSaleReturn'])->name('sales.return.store');

        /*
        |--------------------------------------------------------------------------
        | Invoices & Transactions
        |--------------------------------------------------------------------------
        | Invoice printing, viewing, transaction delivery, and searching
        | invoices eligible for return.
        */
        Route::get('/invoice/print/{invoice}', [InvoicesController::class, 'print'])->name('invoices.print');
        Route::get('/invoice/show/{invoice}', [InvoicesController::class, 'show'])->name('invoices.show');
        Route::post('/transactions/{transaction}/deliver', [InvoicesController::class, 'deliverTransaction'])->name('transactions.deliver');
        Route::get('/invoices/search-for-return', [InverseInvoicesController::class, 'searchInvoices'])->name('invoices.search-for-return');

        /*
        |--------------------------------------------------------------------------
        | Payments & Cheques
        |--------------------------------------------------------------------------
        | Payment records, cheque management, payee invoice lookup,
        | and cheque status updates.
        */
        Route::resource('/payments', PaymentsController::class);
        Route::resource('/cheques', ChequesController::class);
        Route::get('/payee-invoices', [ChequesController::class, 'payeeInvoices'])->name('cheques.payee-invoices');
        Route::put('/cheques/{cheque}/status', [ChequeStatusController::class, 'update'])->name('cheques.updateStatus');

        /*
        |--------------------------------------------------------------------------
        | Expenses
        |--------------------------------------------------------------------------
        | One-time and recurring expense management, expense approval workflow,
        | receipt viewing, and bulk export.
        */
        Route::resource('/recurring-expenses', RecurringExpensesController::class);
        Route::put('/recurring-expenses/{recurring_expense}/toggle', [RecurringExpensesController::class, 'toggle'])->name('recurring-expenses.toggle');
        Route::get('/expenses/export', [ExpensesController::class, 'export'])->name('expenses.export');
        Route::resource('/expenses', ExpensesController::class);
        Route::put('/expenses/{expense}/approval', [ExpenseApprovalController::class, 'update'])->name('expenses.approval');
        Route::get('/expenses/{expense}/receipt', [ExpenseReceiptController::class, 'show'])->name('expenses.receipt');

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
