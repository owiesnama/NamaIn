<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Traits\ClassMetaAttributes;
use App\Traits\WithTrashScope;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class Customer extends BaseModel
{
    use ClassMetaAttributes, HasFactory, SoftDeletes,WithTrashScope;

    /**
     *  List of attributes that can be mass assigned.
     *
     * @var array<string>
     */
    protected array $searchable = ['name', 'address', 'phone_number'];

    protected $fillable = ['name', 'address', 'phone_number', 'credit_limit', 'opening_balance'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:2',
            'opening_balance' => 'decimal:2',
        ];
    }

    /**
     * List of attributes to append.
     *
     * @var array<string>
     */
    protected $appends = ['created_at_human'];

    /**
     * The cheques that belongs to this customer.
     */
    public function cheques(): MorphMany
    {
        return $this->morphMany(Cheque::class, 'chequeable');
    }

    /**
     * Invoices issued for this supplier
     */
    public function invoices(): MorphMany
    {
        return $this->morphMany(Invoice::class, 'invocable');
    }

    /**
     * The categories that belongs to this customer.
     */
    public function categories(): MorphToMany
    {
        return $this->morphToMany(Category::class, 'categorizable');
    }

    /**
     * Calculate the account balance for this customer.
     */
    public function getAccountBalanceAttribute(): float
    {
        return $this->calculateAccountBalance();
    }

    /**
     * The payments that belongs to this customer.
     */
    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    /**
     * Calculate the total account balance (unpaid invoices and direct payments).
     * If $asOfDate is provided, calculate balance as of that date.
     */
    public function calculateAccountBalance(?string $asOfDate = null): float
    {
        if ($asOfDate) {
            $totalInvoiced = $this->invoices()
                ->where('created_at', '<', $asOfDate)
                ->sum(DB::raw('total - discount'));

            $totalPaidOnInvoices = Payment::whereHas('invoice', function ($query) {
                $query->where('invocable_id', $this->id)
                    ->where('invocable_type', self::class);
            })->where('paid_at', '<', $asOfDate)
                ->sum('amount');

            $totalDirectPayments = $this->payments()
                ->where('paid_at', '<', $asOfDate)
                ->sum('amount');

            return (float) ($totalInvoiced - $totalPaidOnInvoices - $totalDirectPayments + $this->opening_balance);
        }

        $invoiceBalance = (float) $this->invoices()
            ->sum(DB::raw('(total - discount) - paid_amount'));

        $directPayments = (float) $this->payments()
            ->sum('amount');

        return $invoiceBalance - $directPayments + (float) $this->opening_balance;
    }

    /**
     * Get all unpaid invoices for this customer.
     */
    public function getUnpaidInvoices(): Collection
    {
        return $this->invoices()
            ->whereIn('payment_status', [PaymentStatus::Unpaid, PaymentStatus::PartiallyPaid])
            ->with('payments')
            ->get();
    }

    /**
     * Get payment history for this customer.
     */
    public function getPaymentHistory(): Collection
    {
        return Payment::where(function ($query) {
            $query->whereHas('invoice', function ($query) {
                $query->where('invocable_id', $this->id)
                    ->where('invocable_type', self::class);
            })->orWhere(function ($query) {
                $query->where('payable_id', $this->id)
                    ->where('payable_type', self::class);
            });
        })->with('invoice', 'payable')->latest()->get();
    }

    /**
     * Scope to filter customers with outstanding balance.
     */
    public function scopeWithOutstandingBalance(Builder $query): Builder
    {
        return $query->whereHas('invoices', function ($q) {
            $q->whereIn('payment_status', [PaymentStatus::Unpaid, PaymentStatus::PartiallyPaid]);
        });
    }

    /**
     * Scope to filter customers who exceeded their credit limit.
     */
    public function scopeExceededCreditLimit(Builder $query): Builder
    {
        return $query->whereHas('invoices', function ($q) {
            $q->whereIn('payment_status', [PaymentStatus::Unpaid, PaymentStatus::PartiallyPaid]);
        })->where(function ($query) {
            $query->where('credit_limit', '>', 0);
        });
    }
}
