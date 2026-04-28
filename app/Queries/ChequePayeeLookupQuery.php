<?php

namespace App\Queries;

use App\Models\Customer;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ChequePayeeLookupQuery
{
    public function all(): Collection
    {
        $customers = Customer::select('id', 'name')
            ->orderBy('name')
            ->get()
            ->map(fn ($customer) => [
                'id' => $customer->id,
                'name' => $customer->name,
                'type' => Customer::class,
                'type_string' => 'Customer',
            ]);

        $suppliers = Supplier::select('id', 'name')
            ->orderBy('name')
            ->get()
            ->map(fn ($supplier) => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'type' => Supplier::class,
                'type_string' => 'Supplier',
            ]);

        return $customers->concat($suppliers);
    }

    public function outstandingInvoicesFor(int $payeeId, string $payeeType): Collection
    {
        $payee = $this->payee($payeeId, $payeeType);

        if (! $payee) {
            return collect();
        }

        return $payee->invoices()
            ->outstanding()
            ->oldest()
            ->get(['id', 'serial_number', 'total', 'discount', 'paid_amount', 'payment_status', 'created_at'])
            ->map(fn ($invoice) => [
                'id' => $invoice->id,
                'serial_number' => $invoice->serial_number,
                'remaining_balance' => $invoice->remaining_balance,
                'total' => $invoice->total - $invoice->discount,
            ]);
    }

    private function payee(int $payeeId, string $payeeType): ?Model
    {
        return match ($payeeType) {
            Customer::class, 'Customer' => Customer::find($payeeId),
            Supplier::class, 'Supplier' => Supplier::find($payeeId),
            default => null,
        };
    }
}
