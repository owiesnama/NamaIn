<?php

use App\Http\Controllers\Api\CustomersController;
use App\Http\Middleware\BindTenantFromAuth;
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

Route::middleware(['auth:sanctum', BindTenantFromAuth::class])
    ->get('/customers', CustomersController::class)
    ->name('api.customers.index');
