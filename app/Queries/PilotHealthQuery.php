<?php

namespace App\Queries;

use App\Models\Device;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The pilot SLO queries (Design 04 §6.2, R14), computed on demand from the
 * audit trail — sync_logs, sync_idempotency, reconciliation_items, devices —
 * for one tenant and window. No metrics stack; the pilot is one store.
 *
 * (a) sale latency p95    = sync_logs.created_at − client_pushed_at over push logs
 * (b) duplicated sales    = sale.create idempotency keys minus distinct results (gate ⇒ 0)
 * (c) resolution p95      = resolved_at − detected_at over resolved items
 * (d) crash-free sessions = 1 − Σ crash_count / Σ session_count (heartbeat-reported)
 */
class PilotHealthQuery
{
    /**
     * @return array<string, mixed>
     */
    public function get(int $tenantId, CarbonInterface $from, CarbonInterface $to): array
    {
        return [
            'sale_latency_p95_seconds' => $this->saleLatencyP95($tenantId, $from, $to),
            'duplicated_sales' => $this->duplicatedSales($tenantId, $from, $to),
            'applied_sales' => $this->appliedSales($tenantId, $from, $to),
            'resolution_p95_hours' => $this->resolutionP95Hours($tenantId, $from, $to),
            'open_items' => $this->openItems($tenantId),
            'crash_free_rate' => $this->crashFreeRate($tenantId),
        ];
    }

    /**
     * (a) p95 of push landing latency: log receipt time minus the moment the
     * device worker began the push (≈ connectivity return).
     */
    private function saleLatencyP95(int $tenantId, CarbonInterface $from, CarbonInterface $to): ?float
    {
        $latencies = DB::table('sync_logs')
            ->where('tenant_id', $tenantId)
            ->where('endpoint', 'push')
            ->whereNotNull('client_pushed_at')
            ->whereBetween('created_at', [$from, $to])
            ->get(['created_at', 'client_pushed_at'])
            ->map(fn (object $row): float => max(
                0,
                strtotime((string) $row->created_at) - strtotime((string) $row->client_pushed_at),
            ));

        return $this->percentile($latencies, 0.95);
    }

    /**
     * (b) Duplicates: sale.create keys minus distinct produced invoices. The
     * unique (tenant_id, idempotency_key) gate makes this 0 by construction;
     * the pilot asserts it stays 0.
     */
    private function duplicatedSales(int $tenantId, CarbonInterface $from, CarbonInterface $to): int
    {
        $rows = DB::table('sync_idempotency')
            ->where('tenant_id', $tenantId)
            ->where('mutation_type', 'sale.create')
            ->where('status', 'applied')
            ->whereBetween('created_at', [$from, $to])
            ->pluck('result_public_id');

        return $rows->count() - $rows->filter()->unique()->count();
    }

    private function appliedSales(int $tenantId, CarbonInterface $from, CarbonInterface $to): int
    {
        return DB::table('sync_idempotency')
            ->where('tenant_id', $tenantId)
            ->where('mutation_type', 'sale.create')
            ->where('status', 'applied')
            ->whereBetween('created_at', [$from, $to])
            ->count();
    }

    /**
     * (c) p95 hours from detection to human resolution.
     */
    private function resolutionP95Hours(int $tenantId, CarbonInterface $from, CarbonInterface $to): ?float
    {
        $durations = DB::table('reconciliation_items')
            ->where('tenant_id', $tenantId)
            ->where('status', 'resolved')
            ->whereNotNull('resolved_at')
            ->whereBetween('detected_at', [$from, $to])
            ->get(['detected_at', 'resolved_at'])
            ->map(fn (object $row): float => max(
                0,
                (strtotime((string) $row->resolved_at) - strtotime((string) $row->detected_at)) / 3600,
            ));

        return $this->percentile($durations, 0.95);
    }

    private function openItems(int $tenantId): int
    {
        return DB::table('reconciliation_items')
            ->where('tenant_id', $tenantId)
            ->where('status', 'open')
            ->count();
    }

    /**
     * (d) 1 − Σ crash / Σ session from the heartbeat-reported counters. Null
     * until any session is reported.
     */
    private function crashFreeRate(int $tenantId): ?float
    {
        $totals = Device::query()
            ->where('tenant_id', $tenantId)
            ->selectRaw('COALESCE(SUM(crash_count), 0) as crashes, COALESCE(SUM(session_count), 0) as sessions')
            ->first();

        if ((int) $totals->sessions === 0) {
            return null;
        }

        return round(1 - ((int) $totals->crashes / (int) $totals->sessions), 4);
    }

    /**
     * @param  Collection<int, float>  $values
     */
    private function percentile(Collection $values, float $percentile): ?float
    {
        if ($values->isEmpty()) {
            return null;
        }

        $sorted = $values->sort()->values();
        $index = (int) ceil($percentile * $sorted->count()) - 1;

        return round((float) $sorted->get(max($index, 0)), 2);
    }
}
