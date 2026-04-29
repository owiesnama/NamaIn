<?php

namespace App\Queries\Reports;

use App\Models\PosSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PosSessionReportQuery
{
    use ResolvesReportDates;

    public function getData(Carbon $from, Carbon $to): array
    {
        return Cache::remember(
            $this->cacheKey("pos_sessions_data_{$from->toDateString()}_{$to->toDateString()}"),
            $this->cacheTtl(),
            fn () => $this->buildData($from, $to),
        );
    }

    public function getSummary(Carbon $from, Carbon $to): array
    {
        return Cache::remember(
            $this->cacheKey("pos_sessions_summary_{$from->toDateString()}_{$to->toDateString()}"),
            $this->cacheTtl(),
            fn () => $this->buildSummary($from, $to),
        );
    }

    private function buildData(Carbon $from, Carbon $to): array
    {
        return PosSession::whereBetween('pos_sessions.created_at', [$from, $to])
            ->with(['storage', 'openedBy'])
            ->withSum(['invoices' => fn ($q) => $q->where('payment_method', 'cash')], 'total')
            ->withCount('invoices')
            ->latest('pos_sessions.created_at')
            ->get()
            ->map(fn (PosSession $session) => [
                'id' => $session->id,
                'operator' => $session->openedBy?->name,
                'storage' => $session->storage?->name,
                'opened_at' => $session->created_at->toDateTimeString(),
                'closed_at' => $session->closed_at?->toDateTimeString(),
                'opening_float' => $session->opening_float,
                'cash_sales' => (int) ($session->invoices_sum_total ?? 0),
                'expected_close' => $session->opening_float + (int) ($session->invoices_sum_total ?? 0),
                'closing_float' => $session->closing_float,
                'variance' => $session->closing_float
                    ? $session->closing_float - ($session->opening_float + (int) ($session->invoices_sum_total ?? 0))
                    : null,
                'invoice_count' => $session->invoices_count,
            ])
            ->all();
    }

    private function buildSummary(Carbon $from, Carbon $to): array
    {
        $result = PosSession::whereBetween('pos_sessions.created_at', [$from, $to])
            ->select(
                DB::raw('COUNT(*) as session_count'),
                DB::raw('SUM(opening_float) as total_opening'),
                DB::raw('SUM(closing_float) as total_closing'),
            )
            ->first();

        $cashSales = PosSession::whereBetween('pos_sessions.created_at', [$from, $to])
            ->withSum(['invoices' => fn ($q) => $q->where('payment_method', 'cash')], 'total')
            ->get()
            ->sum('invoices_sum_total');

        return [
            'session_count' => (int) ($result->session_count ?? 0),
            'total_opening' => (int) ($result->total_opening ?? 0),
            'total_cash_sales' => (int) $cashSales,
            'total_closing' => (int) ($result->total_closing ?? 0),
            'total_variance' => (int) ($result->total_closing ?? 0)
                - ((int) ($result->total_opening ?? 0) + (int) $cashSales),
        ];
    }
}
