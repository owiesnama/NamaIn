<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Traits\HasAccountBalance;
use App\Traits\HasPaymentHistory;
use App\Traits\WithTrashScope;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Customer extends BaseModel
{
    use HasAccountBalance,
        HasFactory,
        HasPaymentHistory,
        SoftDeletes,
        WithTrashScope;

    /**
     *  List of attributes that can be mass assigned.
     *
     * @var array<string>
     */
    protected array $searchable = ['name', 'address', 'phone_number'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:2',
            'opening_debit' => 'decimal:2',
            'opening_credit' => 'decimal:2',
            'is_system' => 'boolean',
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
     * The payments that belongs to this customer.
     */
    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    /**
     * Customer advances given to this customer.
     */
    public function advances(): HasMany
    {
        return $this->hasMany(CustomerAdvance::class);
    }

    /**
     * Payments coming IN settle a customer's balance (money they pay us).
     */
    protected function settlingDirection(): string
    {
        return 'in';
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
