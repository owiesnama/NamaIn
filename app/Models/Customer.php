<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Traits\ClassMetaAttributes;
use App\Traits\WithTrashScope;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Customer extends BaseModel
{
    use ClassMetaAttributes, HasFactory, SoftDeletes,WithTrashScope;

    /**
     *  List of attributes that can be mass assigned.
     *
     * @var array<string>
     */
    protected $fillable = ['name', 'address', 'phone_number', 'type', 'credit_limit'];

    /**
     * List of attributes to cast.
     *
     * @var array<string,string>
     */
    protected $casts = [
        'credit_limit' => 'decimal:2',
    ];

    /**
     * List of attributes to append.
     *
     * @var array<string>
     */
    protected $appends = ['account_balance'];

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
    public function accountBalance(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->calculateAccountBalance()
        );
    }

    /**
     * Calculate the total account balance (unpaid invoices).
     * If $asOfDate is provided, calculate balance as of that date.
     */
    public function calculateAccountBalance(?string $asOfDate = null): float
    {
        if ($asOfDate) {
            $totalInvoiced = $this->invoices()
                ->where('created_at', '<', $asOfDate)
                ->sum('total');

            $totalPaid = Payment::whereHas('invoice', function ($query) {
                $query->where('invocable_id', $this->id)
                    ->where('invocable_type', self::class);
            })->where('paid_at', '<', $asOfDate)
                ->sum('amount');

            return $totalInvoiced - $totalPaid;
        }

        return $this->invoices()
            ->get()
            ->sum('remaining_balance');
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
        return Payment::whereHas('invoice', function ($query) {
            $query->where('invocable_id', $this->id)
                ->where('invocable_type', self::class);
        })->with('invoice')->latest()->get();
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
        })->get()->filter(function ($customer) {
            return $customer->credit_limit > 0 && $customer->account_balance > $customer->credit_limit;
        });
    }
}
