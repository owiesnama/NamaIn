<?php

use App\Http\Controllers\ChequesController;
use App\Http\Controllers\ChequeStatusController;
use App\Http\Controllers\CustomersController;
use App\Http\Controllers\ProductsController;
use App\Http\Controllers\PurchasesController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\StoragesController;
use App\Http\Controllers\SuppliersController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

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

    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::resource('/customers', CustomersController::class);
    Route::resource('/suppliers', SuppliersController::class);
    Route::resource('/storages', StoragesController::class);
    Route::resource('/products', ProductsController::class);
    Route::post('/products/import', [ProductsController::class, 'import'])->name('products.import');
    Route::resource('/purchases', PurchasesController::class);
    Route::resource('/sales', SalesController::class);
    Route::resource('/cheques', ChequesController::class);
    Route::put('/stock/{storage}/add', [StockController::class, 'add'])->name('stock.add');
    Route::put('/stock/{storage}/deduct', [StockController::class, 'deduct'])->name('stock.deduct');
    Route::put('/cheques/{cheque}/status', [ChequeStatusController::class, 'update'])->name('cheques.updateStatus');
});
