<?php

use App\Http\Controllers\ChequesController;
use App\Http\Controllers\ChequeStatusController;
use App\Http\Controllers\CustomersController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\InvoicesController;
use App\Http\Controllers\PaymentsController;
use App\Http\Controllers\PreferenceController;
use App\Http\Controllers\ProductsController;
use App\Http\Controllers\PurchasesController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\StoragesController;
use App\Http\Controllers\SuppliersController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::redirect('/', '/dashboard');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/global-search', GlobalSearchController::class)->name('global-search');

    Route::get('/preferences', [PreferenceController::class, 'index'])->name('preferences.index');
    Route::post('/preferences', [PreferenceController::class, 'update'])->name('preferences.update');
    Route::put('/preferences', [PreferenceController::class, 'update']);
    Route::resource('/customers', CustomersController::class);
    Route::post('/customers/import', [CustomersController::class, 'import'])->name('customers.import');
    Route::get('/customers/import/sample', [CustomersController::class, 'importSample'])->name('customers.import-sample');
    Route::get('/customers/{customer}/account', [CustomersController::class, 'account'])->name('customers.account');
    Route::get('/customers/{customer}/statement', [CustomersController::class, 'statement'])->name('customers.statement');
    Route::get('/customers/{customer}/statement/print', [CustomersController::class, 'printStatement'])->name('customers.print-statement');

    Route::resource('/suppliers', SuppliersController::class);
    Route::post('/suppliers/import', [SuppliersController::class, 'import'])->name('suppliers.import');
    Route::get('/suppliers/import/sample', [SuppliersController::class, 'importSample'])->name('suppliers.import-sample');

    Route::resource('/storages', StoragesController::class);

    Route::resource('/products', ProductsController::class);
    Route::post('/products/import', [ProductsController::class, 'import'])->name('products.import');
    Route::get('/products/import/sample', [ProductsController::class, 'importSample'])->name('products.import-sample');
    Route::resource('/purchases', PurchasesController::class);
    Route::resource('/sales', SalesController::class);
    Route::resource('/payments', PaymentsController::class);
    Route::resource('/cheques', ChequesController::class);
    Route::get('/invoice/print/{invoice}', [InvoicesController::class, 'print'])->name('invoices.print');
    Route::get('/invoice/show/{invoice}', [InvoicesController::class, 'show'])->name('invoices.show');
    Route::put('/stock/{storage}/add', [StockController::class, 'add'])->name('stock.add');
    Route::put('/stock/{storage}/deduct', [StockController::class, 'deduct'])->name('stock.deduct');
    Route::put('/cheques/{cheque}/status', [ChequeStatusController::class, 'update'])->name('cheques.updateStatus');
});
