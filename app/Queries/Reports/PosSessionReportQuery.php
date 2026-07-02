<?php

namespace App\Queries\Reports;

use App\Models\PosSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PosSessionReportQuery extends ReportQuery
{
    public function get(Carbon $from, Carbon $to): array
    {
        return $this->remember(
            "pos_sessions_data_{$from->toDateString()}_{$to->toDateString()}",
            fn () => $this->buildData($from, $to),
        );
    }

    public function summary(Carbon $from, Carbon $to): array
    {
        return $this->remember(
            "pos_sessions_summary_{$from->toDateString()}_{$to->toDateString()}",
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
            ->map(function (PosSession $session) {
                // Floats and invoice totals are stored in minor units; the report is in major units.
                $openingFloat = $session->opening_float / 100;
                $closingFloat = $session->closing_float !== null ? $session->closing_float / 100 : null;
                $cashSales = ((int) ($session->invoices_sum_total ?? 0)) / 100;
                $expectedClose = round($openingFloat + $cashSales, 2);

                return [
                    'id' => $session->id,
                    'operator' => $session->openedBy?->name,
                    'storage' => $session->storage?->name,
                    'opened_at' => $session->created_at->toDateTimeString(),
                    'closed_at' => $session->closed_at?->toDateTimeString(),
                    'opening_float' => $openingFloat,
                    'cash_sales' => $cashSales,
                    'expected_close' => $expectedClose,
                    'closing_float' => $closingFloat,
                    'variance' => $closingFloat !== null
                        ? round($closingFloat - $expectedClose, 2)
                        : null,
                    'invoice_count' => $session->invoices_count,
                ];
            })
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

        // Floats and invoice totals are stored in minor units; the report is in major units.
        $cashSales = ((int) PosSession::whereBetween('pos_sessions.created_at', [$from, $to])
            ->withSum(['invoices' => fn ($q) => $q->where('payment_method', 'cash')], 'total')
            ->get()
            ->sum('invoices_sum_total')) / 100;

        $totalOpening = ($result->total_opening ?? 0) / 100;
        $totalClosing = ($result->total_closing ?? 0) / 100;

        return [
            'session_count' => (int) ($result->session_count ?? 0),
            'total_opening' => $totalOpening,
            'total_cash_sales' => $cashSales,
            'total_closing' => $totalClosing,
            'total_variance' => round($totalClosing - ($totalOpening + $cashSales), 2),
        ];
    }
}
