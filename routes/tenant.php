<?php

declare(strict_types=1);

use App\Http\Controllers\ChequesController;
use App\Http\Controllers\TenantSelectionController;
use App\Http\Controllers\ChequeStatusController;
use App\Http\Controllers\CustomersController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseApprovalController;
use App\Http\Controllers\ExpenseReceiptController;
use App\Http\Controllers\ExpensesController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\InverseInvoicesController;
use App\Http\Controllers\InvoicesController;
use App\Http\Controllers\PaymentsController;
use App\Http\Controllers\PreferenceController;
use App\Http\Controllers\ProductsController;
use App\Http\Controllers\PurchasesController;
use App\Http\Controllers\RecurringExpensesController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\StoragesController;
use App\Http\Controllers\SuppliersController;
use App\Http\Controllers\TemporaryUploadController;
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
        Route::redirect('/', '/dashboard');

        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('/global-search', GlobalSearchController::class)->name('global-search');

        Route::get('/preferences', [PreferenceController::class, 'index'])->name('preferences.index');
        Route::post('/preferences', [PreferenceController::class, 'update'])->name('preferences.update');
        Route::put('/preferences', [PreferenceController::class, 'update']);

        Route::get('/customers/export', [CustomersController::class, 'export'])->name('customers.export');
        Route::post('/customers/import', [CustomersController::class, 'import'])->name('customers.import');
        Route::get('/customers/import/sample', [CustomersController::class, 'importSample'])->name('customers.import.sample');
        Route::resource('/customers', CustomersController::class);
        Route::get('/customers/{customer}/account', [CustomersController::class, 'account'])->name('customers.account');
        Route::get('/customers/{customer}/statement', [CustomersController::class, 'statement'])->name('customers.statement');
        Route::get('/customers/{customer}/statement/print', [CustomersController::class, 'printStatement'])->name('customers.print-statement');

        Route::get('/suppliers/export', [SuppliersController::class, 'export'])->name('suppliers.export');
        Route::post('/suppliers/import', [SuppliersController::class, 'import'])->name('suppliers.import');
        Route::get('/suppliers/import/sample', [SuppliersController::class, 'importSample'])->name('suppliers.import.sample');
        Route::resource('/suppliers', SuppliersController::class);
        Route::get('/suppliers/{supplier}/account', [SuppliersController::class, 'account'])->name('suppliers.account');
        Route::get('/suppliers/{supplier}/statement', [SuppliersController::class, 'statement'])->name('suppliers.statement');
        Route::get('/suppliers/{supplier}/statement/print', [SuppliersController::class, 'printStatement'])->name('suppliers.print-statement');

        Route::resource('/storages', StoragesController::class);

        Route::get('/products/export', [ProductsController::class, 'export'])->name('products.export');
        Route::post('/products/import', [ProductsController::class, 'import'])->name('products.import');
        Route::get('/products/import/sample', [ProductsController::class, 'importSample'])->name('products.import.sample');
        Route::resource('/products', ProductsController::class);
        Route::resource('/purchases', PurchasesController::class);
        Route::resource('/sales', SalesController::class);
        Route::resource('/payments', PaymentsController::class);
        Route::resource('/cheques', ChequesController::class);
        Route::get('/payee-invoices', [ChequesController::class, 'payeeInvoices'])->name('cheques.payee-invoices');
        Route::resource('/recurring-expenses', RecurringExpensesController::class);
        Route::put('/recurring-expenses/{recurring_expense}/toggle', [RecurringExpensesController::class, 'toggle'])->name('recurring-expenses.toggle');
        Route::get('/expenses/export', [ExpensesController::class, 'export'])->name('expenses.export');
        Route::resource('/expenses', ExpensesController::class);
        Route::put('/expenses/{expense}/approval', [ExpenseApprovalController::class, 'update'])->name('expenses.approval');
        Route::get('/expenses/{expense}/receipt', [ExpenseReceiptController::class, 'show'])->name('expenses.receipt');
        Route::get('/invoice/print/{invoice}', [InvoicesController::class, 'print'])->name('invoices.print');
        Route::get('/invoice/show/{invoice}', [InvoicesController::class, 'show'])->name('invoices.show');
        Route::put('/stock/{storage}/add', [StockController::class, 'add'])->name('stock.add')->can('manageStock', 'storage');
        Route::put('/stock/{storage}/deduct', [StockController::class, 'deduct'])->name('stock.deduct')->can('manageStock', 'storage');
        Route::put('/cheques/{cheque}/status', [ChequeStatusController::class, 'update'])->name('cheques.updateStatus');

        Route::get('/invoices/search-for-return', [InverseInvoicesController::class, 'searchInvoices'])->name('invoices.search-for-return');
        Route::get('/sales/{invoice}/return', [InverseInvoicesController::class, 'createSaleReturn'])->name('sales.return.create');
        Route::post('/sales/{invoice}/return', [InverseInvoicesController::class, 'storeSaleReturn'])->name('sales.return.store');
        Route::get('/purchases/{invoice}/return', [InverseInvoicesController::class, 'createPurchaseReturn'])->name('purchases.return.create');
        Route::post('/purchases/{invoice}/return', [InverseInvoicesController::class, 'storePurchaseReturn'])->name('purchases.return.store');

        Route::post('/uploads/tmp', [TemporaryUploadController::class, 'store'])->name('uploads.tmp.store');
        Route::delete('/uploads/tmp', [TemporaryUploadController::class, 'destroy'])->name('uploads.tmp.destroy');

        Route::post('/switch-tenant/{target}', [TenantSelectionController::class, 'switchFrom'])->name('tenants.switch');
    });
});
