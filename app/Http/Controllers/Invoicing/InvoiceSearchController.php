<?php

namespace App\Http\Controllers\Invoicing;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Supplier;

class InvoiceSearchController extends Controller
{
    public function index()
    {
        $type = request('type', 'sale');
        $query = Invoice::query()
            ->with(['invocable'])
            ->where('is_inverse', false)
            ->where('status', '!=', InvoiceStatus::Returned)
            ->when(request('search'), function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('serial_number', 'like', "%{$search}%")
                        ->orWhereHasMorph('invocable', [Customer::class, Supplier::class], function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->limit(20);

        if ($type === 'sale') {
            $query->where('invocable_type', Customer::class);
        } else {
            $query->where('invocable_type', Supplier::class);
        }

        return response()->json(
            $query->get()->map(fn ($invoice) => [
                'id' => $invoice->id,
                'serial_number' => $invoice->serial_number,
                'invocable_name' => $invoice->invocable?->name,
                'total' => $invoice->total,
                'date' => $invoice->created_at->format('Y-m-d'),
                'return_url' => route($type === 'sale' ? 'sales.return.create' : 'purchases.return.create', $invoice->id),
            ])
        );
    }
}
