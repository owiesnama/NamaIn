<?php

use App\Http\Controllers\Api\CustomersController;
use App\Http\Controllers\Api\ProductsController;
use App\Http\Controllers\Api\SuppliersController;
use App\Http\Middleware\BindTenant;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware(['auth:sanctum', BindTenant::class, 'throttle:60,1'])->group(function () {
    Route::get('/customers', CustomersController::class)->name('api.customers.index');
    Route::get('/products', ProductsController::class)->name('api.products.index');
    Route::get('/suppliers', SuppliersController::class)->name('api.suppliers.index');
});
