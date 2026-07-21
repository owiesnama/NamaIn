<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class PosSession extends BaseModel
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'closed_at' => 'datetime',
            'opening_float' => 'integer',
            'closing_float' => 'integer',
        ];
    }

    public function storage(): BelongsTo
    {
        return $this->belongsTo(Storage::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function cashSalesTotal(): int
    {
        // Invoice totals and session floats are both stored in minor units.
        return (int) $this->invoices()
            ->where('payment_method', 'cash')
            ->sum('total');
    }

    public function expectedClosingFloat(): int
    {
        return $this->opening_float + $this->cashSalesTotal();
    }

    public function variance(): int
    {
        if ($this->closing_float === null) {
            return 0;
        }

        return $this->closing_float - $this->expectedClosingFloat();
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }

    /**
     * Total sold in this session grouped by payment method, in minor units.
     *
     * @return Collection<int, array{method: string, total: int, count: int}>
     */
    public function salesByPaymentMethod(): Collection
    {
        return $this->invoices()
            ->selectRaw('payment_method, SUM(total) as total, COUNT(*) as count')
            ->groupBy('payment_method')
            ->orderByRaw('SUM(total) DESC')
            ->get()
            ->map(fn ($row) => [
                'method' => $row->payment_method instanceof PaymentMethod
                    ? $row->payment_method->value
                    : (string) $row->payment_method,
                'total' => (int) $row->total,
                'count' => (int) $row->count,
            ]);
    }

    /**
     * Payments collected in this session grouped by treasury account, in minor units.
     *
     * @return Collection<int, array{account_id: int, account_name: string, total: int, count: int}>
     */
    public function salesByTreasuryAccount(): Collection
    {
        // Attribute each invoice's total to the account its payment landed in.
        // Summing invoices.total (not payments.amount) keeps this on the same
        // minor-unit scale as cashSalesTotal() so the figures reconcile.
        return $this->invoices()
            ->join('payments', 'payments.invoice_id', '=', 'invoices.id')
            ->join('treasury_accounts', 'treasury_accounts.id', '=', 'payments.treasury_account_id')
            ->whereNotNull('payments.treasury_account_id')
            ->groupBy('treasury_accounts.id', 'treasury_accounts.name')
            ->orderByRaw('SUM(invoices.total) DESC')
            ->selectRaw('treasury_accounts.id as account_id, treasury_accounts.name as account_name, SUM(invoices.total) as total, COUNT(DISTINCT invoices.id) as count')
            ->get()
            ->map(fn ($row) => [
                'account_id' => (int) $row->account_id,
                'account_name' => $row->account_name,
                'total' => (int) $row->total,
                'count' => (int) $row->count,
            ]);
    }
}
