<?php

namespace App\Queries;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Model;

class StatementQuery
{
    public function forParty(Model $party, string $startDate, string $endDate): array
    {
        $invoices = $party->invoices()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with('payments')
            ->get();

        $payments = Payment::where(function ($query) use ($party) {
            $query->whereHas('invoice', function ($query) use ($party) {
                $query->where('invocable_id', $party->id)
                    ->where('invocable_type', get_class($party));
            })->orWhere(function ($query) use ($party) {
                $query->where('payable_id', $party->id)
                    ->where('payable_type', get_class($party));
            });
        })->whereBetween('paid_at', [$startDate, $endDate])->latest()->get();

        $openingBalance = $party->calculateAccountBalance($startDate);

        $activities = collect();

        foreach ($invoices as $invoice) {
            $activities->push([
                'date' => $invoice->created_at,
                'description' => __('Invoice').' #'.($invoice->serial_number ?: $invoice->id),
                'debit' => (float) $invoice->total,
                'credit' => 0,
                'type' => 'invoice',
            ]);
        }

        foreach ($payments as $payment) {
            $activities->push([
                'date' => $payment->paid_at,
                'description' => __('Payment').' — '.($payment->payment_method?->label() ?? '—'),
                'debit' => 0,
                'credit' => (float) $payment->amount,
                'type' => 'payment',
            ]);
        }

        $activities = $activities->sortBy('date')->values();
        $totalDebits = $activities->sum('debit');
        $totalCredits = $activities->sum('credit');

        $runningBalance = $openingBalance;
        $activities = $activities->map(function ($activity) use (&$runningBalance) {
            $runningBalance += $activity['debit'];
            $runningBalance -= $activity['credit'];
            $activity['running_balance'] = $runningBalance;

            return $activity;
        });

        $closingBalance = $openingBalance + $totalDebits - $totalCredits;

        return [
            'party' => $party,
            'invoices' => $invoices,
            'payments' => $payments,
            'opening_balance' => $openingBalance,
            'activities' => $activities,
            'total_debits' => $totalDebits,
            'total_credits' => $totalCredits,
            'closing_balance' => $closingBalance,
        ];
    }
}
