<?php

namespace App\Queries\Reports;

use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

/**
 * Ages a party's outstanding invoice balances into 30-day buckets.
 * Customers and suppliers share the exact same shape; children only
 * name the party.
 */
abstract class PartyAgingQuery extends ReportQuery
{
    /** The party table, e.g. 'customers'. */
    abstract protected function partyTable(): string;

    /** The invoice morph class, e.g. Customer::class. */
    abstract protected function partyMorphClass(): string;

    /** The singular key used in row and summary arrays, e.g. 'customer'. */
    abstract protected function partyKey(): string;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get(?int $partyId = null): array
    {
        return $this->remember(
            "{$this->partyKey()}_aging_data_{$partyId}",
            fn () => $this->buildData($partyId),
        );
    }

    /**
     * @return array<string, int|float>
     */
    public function summary(?int $partyId = null): array
    {
        return $this->remember(
            "{$this->partyKey()}_aging_summary_{$partyId}",
            fn () => $this->buildSummary($partyId),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildData(?int $partyId): array
    {
        $table = $this->partyTable();
        $key = $this->partyKey();
        $dateDiff = $this->dateDiff();
        $outstanding = Invoice::outstandingBalanceSql('invoices');

        $query = Invoice::query()
            ->where('invocable_type', $this->partyMorphClass())
            ->outstanding()
            ->join($table, function ($join) use ($table) {
                $join->on('invoices.invocable_id', '=', "{$table}.id")
                    ->where('invoices.invocable_type', $this->partyMorphClass());
            })
            ->select(
                "{$table}.id as {$key}_id",
                "{$table}.name as {$key}_name",
                DB::raw("SUM(CASE WHEN $dateDiff BETWEEN 0 AND 30 THEN $outstanding ELSE 0 END) / 100.0 as bucket_0_30"),
                DB::raw("SUM(CASE WHEN $dateDiff BETWEEN 31 AND 60 THEN $outstanding ELSE 0 END) / 100.0 as bucket_31_60"),
                DB::raw("SUM(CASE WHEN $dateDiff BETWEEN 61 AND 90 THEN $outstanding ELSE 0 END) / 100.0 as bucket_61_90"),
                DB::raw("SUM(CASE WHEN $dateDiff > 90 THEN $outstanding ELSE 0 END) / 100.0 as bucket_90_plus"),
                DB::raw("SUM($outstanding) / 100.0 as total"),
            )
            ->groupBy("{$table}.id", "{$table}.name")
            ->orderByDesc('total');

        if ($partyId) {
            $query->where("{$table}.id", $partyId);
        }

        // Aggregate rows must stay plain: hydrating Invoice models lets the
        // `total` alias collide with the invoice's cast `total` attribute.
        return $query->toBase()->get()
            ->map(fn ($row) => [
                "{$key}_id" => $row->{"{$key}_id"},
                "{$key}_name" => $row->{"{$key}_name"},
                'bucket_0_30' => round((float) $row->bucket_0_30, 2),
                'bucket_31_60' => round((float) $row->bucket_31_60, 2),
                'bucket_61_90' => round((float) $row->bucket_61_90, 2),
                'bucket_90_plus' => round((float) $row->bucket_90_plus, 2),
                'total' => round((float) $row->total, 2),
            ])
            ->all();
    }

    /**
     * @return array<string, int|float>
     */
    private function buildSummary(?int $partyId): array
    {
        $data = $this->buildData($partyId);

        return [
            "{$this->partyKey()}_count" => count($data),
            'bucket_0_30' => round(array_sum(array_column($data, 'bucket_0_30')), 2),
            'bucket_31_60' => round(array_sum(array_column($data, 'bucket_31_60')), 2),
            'bucket_61_90' => round(array_sum(array_column($data, 'bucket_61_90')), 2),
            'bucket_90_plus' => round(array_sum(array_column($data, 'bucket_90_plus')), 2),
            'total' => round(array_sum(array_column($data, 'total')), 2),
        ];
    }
}
