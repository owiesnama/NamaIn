<?php

namespace App\Queries;

use App\Models\Payment;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

class PartyAccountQuery
{
    public function invoices(Model $party, int $perPage): LengthAwarePaginator
    {
        return $party->invoices()
            ->latest()
            ->paginate($perPage, ['*'], 'invoices_page')
            ->withQueryString();
    }

    public function payments(Model $party, int $perPage): LengthAwarePaginator
    {
        return Payment::where(function ($query) use ($party) {
            $query->whereHas('invoice', function ($query) use ($party) {
                $query->where('invocable_id', $party->id)
                    ->where('invocable_type', get_class($party));
            })->orWhere(function ($query) use ($party) {
                $query->where('payable_id', $party->id)
                    ->where('payable_type', get_class($party));
            });
        })
            ->with('invoice')
            ->orderBy('paid_at', 'desc')
            ->paginate($perPage, ['*'], 'payments_page')
            ->withQueryString();
    }

    public function transactions(Model $party, int $perPage): LengthAwarePaginator
    {
        return Transaction::whereHas('invoice', function ($query) use ($party) {
            $query->where('invocable_id', $party->id)
                ->where('invocable_type', get_class($party));
        })
            ->with(['product', 'invoice'])
            ->latest()
            ->paginate($perPage, ['*'], 'transactions_page')
            ->withQueryString();
    }
}
