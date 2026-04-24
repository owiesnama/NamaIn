<?php

namespace App\Http\Controllers\Sales;

use App\Filters\PosInvoiceFilter;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PosSession;

class PosInvoicesController extends Controller
{
    public function index(PosInvoiceFilter $filter)
    {
        $invoicesQuery = Invoice::query()
            ->forCustomer()
            ->fromPos()
            ->with(['invocable', 'transactions.product', 'transactions.unit', 'posSession.openedBy'])
            ->filter($filter);

        return inertia('Pos/Invoices', [
            'invoices' => (clone $invoicesQuery)
                ->latest()
                ->paginate(10)
                ->withQueryString(),

            'sessions' => PosSession::query()
                ->with(['openedBy:id,name', 'closedBy:id,name', 'storage:id,name'])
                ->latest()
                ->paginate(15)
                ->through(fn (PosSession $session) => [
                    'id' => $session->id,
                    'opened_at' => $session->created_at,
                    'closed_at' => $session->closed_at,
                    'cashier_name' => $session->openedBy?->name,
                    'closed_by_name' => $session->closedBy?->name,
                    'storage_name' => $session->storage?->name,
                    'opening_float' => $session->opening_float / 100,
                    'closing_float' => $session->closing_float !== null ? $session->closing_float / 100 : null,
                    'cash_sales_total' => $session->cashSalesTotal() / 100,
                    'expected_closing_float' => $session->expectedClosingFloat() / 100,
                    'variance' => $session->closing_float !== null ? $session->variance() / 100 : null,
                    'invoice_count' => $session->invoices()->count(),
                    'is_open' => $session->isOpen(),
                ])
                ->withQueryString(),

            'filter_sessions' => PosSession::query()
                ->with('openedBy:id,name')
                ->latest()
                ->get(['id', 'created_at', 'opened_by'])
                ->map(fn (PosSession $session) => [
                    'id' => $session->id,
                    'opened_at' => $session->created_at,
                    'cashier_name' => $session->openedBy?->name,
                ]),

            'summary' => [
                'total_revenue' => (float) (clone $invoicesQuery)->sum('total'),
                'total_sales' => (clone $invoicesQuery)->count(),
                'walk_in_sales' => (clone $invoicesQuery)
                    ->whereHasMorph('invocable', [Customer::class], fn ($query) => $query->where('is_system', true))
                    ->count(),
                'named_customer_sales' => (clone $invoicesQuery)
                    ->whereHasMorph('invocable', [Customer::class], fn ($query) => $query->where('is_system', false))
                    ->count(),
            ],

            'filters' => request()->only(['search', 'session_id', 'from_date', 'to_date', 'customer_type']),
        ]);
    }
}
