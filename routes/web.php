<?php

use App\Http\Controllers\CustomersController;
use Illuminate\Foundation\Application;
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

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::get('/items', function () {
        return Inertia::render('Items');
    })->name('items');

    Route::get('/customers', [CustomersController::class, 'index'])->name('customers');
    Route::post('/customers', [CustomersController::class, 'store'])->name('customers.store');

    Route::get('/purchases', function () {
        return Inertia::render('Purchases');
    })->name('purchases');

    Route::get('/storages', function () {
        return Inertia::render('Storages');
    })->name('storages');

    Route::get('/sales', function () {
        return Inertia::render('Sales');
    })->name('sales');
});
