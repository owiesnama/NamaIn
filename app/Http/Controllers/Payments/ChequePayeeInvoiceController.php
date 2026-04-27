<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Supplier;

class ChequePayeeInvoiceController extends Controller
{
    public function index()
    {
        return response()->json(
            $this->getPayeeInvoices(request('payee_id'), request('payee_type'))
        );
    }

    protected function getPayeeInvoices($id, $type)
    {
        $model = ($type === 'Customer' || $type === Customer::class) ? Customer::find($id) : Supplier::find($id);

        if (! $model) {
            return [];
        }

        return $model->invoices()
            ->outstanding()
            ->get()
            ->map(fn ($i) => [
                'id' => $i->id,
                'serial_number' => $i->serial_number,
                'remaining_balance' => $i->remaining_balance,
                'total' => $i->total - $i->discount,
            ]);
    }
}
