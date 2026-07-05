<?php

namespace App\Queries\Reports;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Item-level reconciliation detail for the `report-reconciliation` export
 * (Design 04 §6.3, R14): one row per item in the window — type, device,
 * register, status, resolution, and the occurred→detected→resolved timeline.
 */
class ReconciliationReportQuery extends ReportQuery
{
    /**
     * @return list<array<string, mixed>>
     */
    public function get(Carbon $from, Carbon $to): array
    {
        return $this->remember(
            "reconciliation_report_{$from->toDateString()}_{$to->toDateString()}",
            fn (): array => $this->buildData($from, $to),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildData(Carbon $from, Carbon $to): array
    {
        return DB::table('reconciliation_items')
            ->leftJoin('devices', 'devices.id', '=', 'reconciliation_items.device_id')
            ->leftJoin('registers', 'registers.id', '=', 'reconciliation_items.register_id')
            ->leftJoin('users', 'users.id', '=', 'reconciliation_items.resolved_by')
            ->where('reconciliation_items.tenant_id', $this->tenantId())
            ->whereBetween('reconciliation_items.detected_at', [$from, $to])
            ->orderByDesc('reconciliation_items.detected_at')
            ->select(
                'reconciliation_items.public_id',
                'reconciliation_items.type',
                'reconciliation_items.status',
                'reconciliation_items.resolution',
                'devices.name as device_name',
                'registers.code as register_code',
                'reconciliation_items.occurred_at',
                'reconciliation_items.detected_at',
                'reconciliation_items.resolved_at',
                'users.name as resolved_by_name',
            )
            ->get()
            ->map(fn (object $row): array => [
                'public_id' => $row->public_id,
                'type' => $row->type,
                'status' => $row->status,
                'resolution' => $row->resolution,
                'device' => $row->device_name,
                'register' => $row->register_code,
                'occurred_at' => $row->occurred_at,
                'detected_at' => $row->detected_at,
                'resolved_at' => $row->resolved_at,
                'resolved_by' => $row->resolved_by_name,
            ])
            ->all();
    }
}
