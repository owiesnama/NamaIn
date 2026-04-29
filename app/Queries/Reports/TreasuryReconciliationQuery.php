<?php

namespace App\Queries\Reports;

use App\Models\TreasuryAccount;
use App\Models\TreasuryMovement;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TreasuryReconciliationQuery
{
    use ResolvesReportDates;

    public function getData(Carbon $from, Carbon $to, ?int $accountId = null): array
    {
        return Cache::remember(
            $this->cacheKey("treasury_data_{$from->toDateString()}_{$to->toDateString()}_{$accountId}"),
            $this->cacheTtl(),
            fn () => $this->buildData($from, $to, $accountId),
        );
    }

    public function getSummary(Carbon $from, Carbon $to, ?int $accountId = null): array
    {
        return Cache::remember(
            $this->cacheKey("treasury_summary_{$from->toDateString()}_{$to->toDateString()}_{$accountId}"),
            $this->cacheTtl(),
            fn () => $this->buildSummary($from, $to, $accountId),
        );
    }

    private function buildData(Carbon $from, Carbon $to, ?int $accountId): array
    {
        $query = TreasuryMovement::query()
            ->join('treasury_accounts', 'treasury_movements.treasury_account_id', '=', 'treasury_accounts.id')
            ->whereBetween('treasury_movements.occurred_at', [$from, $to])
            ->select(
                'treasury_movements.*',
                'treasury_accounts.name as account_name',
            )
            ->orderBy('treasury_movements.occurred_at');

        if ($accountId) {
            $query->where('treasury_movements.treasury_account_id', $accountId);
        }

        return $query->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'account_name' => $m->account_name,
                'occurred_at' => $m->occurred_at->toDateTimeString(),
                'reason' => $m->reason?->value,
                'amount' => $m->amount,
                'balance_after' => $m->balance_after,
            ])
            ->all();
    }

    private function buildSummary(Carbon $from, Carbon $to, ?int $accountId): array
    {
        $accounts = TreasuryAccount::active();

        if ($accountId) {
            $accounts->where('id', $accountId);
        }

        $accountData = $accounts->get()->map(function (TreasuryAccount $account) use ($from, $to) {
            $openingBalance = $account->opening_balance
                + (int) $account->movements()
                    ->where('occurred_at', '<', $from)
                    ->sum('amount');

            $periodMovements = $account->movements()
                ->whereBetween('occurred_at', [$from, $to])
                ->select(
                    DB::raw('SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as total_credits'),
                    DB::raw('SUM(CASE WHEN amount < 0 THEN amount ELSE 0 END) as total_debits'),
                )
                ->first();

            $closingBalance = $openingBalance
                + (int) ($periodMovements->total_credits ?? 0)
                + (int) ($periodMovements->total_debits ?? 0);

            return [
                'account_name' => $account->name,
                'opening_balance' => $openingBalance,
                'total_credits' => (int) ($periodMovements->total_credits ?? 0),
                'total_debits' => (int) ($periodMovements->total_debits ?? 0),
                'closing_balance' => $closingBalance,
            ];
        })->all();

        return [
            'accounts' => $accountData,
            'total_opening' => array_sum(array_column($accountData, 'opening_balance')),
            'total_credits' => array_sum(array_column($accountData, 'total_credits')),
            'total_debits' => array_sum(array_column($accountData, 'total_debits')),
            'total_closing' => array_sum(array_column($accountData, 'closing_balance')),
        ];
    }
}
