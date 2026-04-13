<?php

namespace App\Queries;

use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardStatsQuery
{
    public function getMonthlyStats(): array
    {
        $months = collect(range(5, 0))->map(fn ($i) => now()->subMonths($i)->format('Y-m'));
        $dateFormat = $this->monthlyDateFormat();

        $sales = Transaction::delivered(now()->subMonths(6))
            ->forCustomer()
            ->select(DB::raw("$dateFormat as month"), DB::raw('SUM(price * base_quantity) as total'))
            ->groupBy('month')
            ->pluck('total', 'month');

        $purchases = Transaction::delivered(now()->subMonths(6))
            ->forSupplier()
            ->select(DB::raw("$dateFormat as month"), DB::raw('SUM(price * base_quantity) as total'))
            ->groupBy('month')
            ->pluck('total', 'month');

        return [
            'labels' => $months->map(fn ($m) => Carbon::parse($m)->locale(app()->getLocale())->translatedFormat('M Y')),
            'sales' => $months->map(fn ($m) => $sales->get($m, 0)),
            'purchases' => $months->map(fn ($m) => $purchases->get($m, 0)),
        ];
    }

    private function monthlyDateFormat(): string
    {
        return match (DB::getDriverName()) {
            'sqlite' => "strftime('%Y-%m', created_at)",
            'pgsql' => "TO_CHAR(created_at, 'YYYY-MM')",
            default => "DATE_FORMAT(created_at, '%Y-%m')",
        };
    }
}
