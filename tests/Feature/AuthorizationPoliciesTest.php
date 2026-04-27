<?php

use App\Models\Expense;
use App\Models\Product;
use App\Models\Supplier;

/*
 * Staff role has view-only permissions: products.view, suppliers.view,
 * sales.view, purchases.view, expenses.view, payments.view, etc.
 * Staff does NOT have create, update, delete, or manage permissions.
 */

describe('ProductPolicy', function () {
    it('allows staff to view product index', function () {
        actingAsTenantUser(role: 'staff')
            ->get(route('products.index'))
            ->assertOk();
    });

    it('blocks staff from deleting a product', function () {
        $product = Product::factory()->create();

        actingAsTenantUser(role: 'staff')
            ->delete(route('products.destroy', $product))
            ->assertForbidden();
    });

    it('allows owner to delete a product', function () {
        $product = Product::factory()->create();

        actingAsTenantUser(role: 'owner')
            ->delete(route('products.destroy', $product))
            ->assertRedirect();
    });
});

describe('SupplierPolicy', function () {
    it('allows staff to view supplier index', function () {
        actingAsTenantUser(role: 'staff')
            ->get(route('suppliers.index'))
            ->assertOk();
    });

    it('blocks staff from creating a supplier', function () {
        actingAsTenantUser(role: 'staff')
            ->post(route('suppliers.store'), [
                'name' => 'Test Supplier',
                'address' => '123 Test St',
                'phone_number' => '0123456789',
            ])
            ->assertForbidden();
    });

    it('blocks staff from deleting a supplier', function () {
        $supplier = Supplier::factory()->create();

        actingAsTenantUser(role: 'staff')
            ->delete(route('suppliers.destroy', $supplier))
            ->assertForbidden();
    });
});

describe('InvoicePolicy on Sales/Purchases', function () {
    it('allows staff to view sales index', function () {
        actingAsTenantUser(role: 'staff')
            ->get(route('sales.index'))
            ->assertOk();
    });

    it('blocks staff from creating a sale', function () {
        actingAsTenantUser(role: 'staff')
            ->get(route('sales.create'))
            ->assertForbidden();
    });

    it('allows staff to view purchases index', function () {
        actingAsTenantUser(role: 'staff')
            ->get(route('purchases.index'))
            ->assertOk();
    });

    it('blocks staff from creating a purchase', function () {
        actingAsTenantUser(role: 'staff')
            ->get(route('purchases.create'))
            ->assertForbidden();
    });
});

describe('PaymentPolicy', function () {
    it('allows staff to view payment index', function () {
        actingAsTenantUser(role: 'staff')
            ->get(route('payments.index'))
            ->assertOk();
    });

    it('blocks staff from accessing payment creation form', function () {
        actingAsTenantUser(role: 'staff')
            ->get(route('payments.create'))
            ->assertForbidden();
    });
});

describe('ExpensePolicy', function () {
    it('allows staff to view expense index', function () {
        actingAsTenantUser(role: 'staff')
            ->get(route('expenses.index'))
            ->assertOk();
    });

    it('blocks staff from deleting an expense', function () {
        $expense = Expense::factory()->create();

        actingAsTenantUser(role: 'staff')
            ->delete(route('expenses.destroy', $expense))
            ->assertForbidden();
    });

    it('allows owner to delete an expense', function () {
        $expense = Expense::factory()->create();

        actingAsTenantUser(role: 'owner')
            ->delete(route('expenses.destroy', $expense))
            ->assertRedirect();
    });
});
