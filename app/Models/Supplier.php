<?php

namespace App\Models;

use App\Traits\ClassMetaAttributes;
use App\Traits\WithTrashScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class Supplier extends BaseModel
{
    use ClassMetaAttributes, HasFactory, SoftDeletes,WithTrashScope;

    /**
     * List of attributes that can be mass assigned.
     *
     * @var array<string>
     */
    protected array $searchable = ['name', 'phone_number', 'address'];

    protected $fillable = ['name', 'phone_number', 'address', 'opening_balance'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
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
     * Cheque wrote to this supplier.
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
     * The categories that belongs to this supplier.
     */
    public function categories(): MorphToMany
    {
        return $this->morphToMany(Category::class, 'categorizable');
    }

    /**
     * Calculate the account balance for this supplier.
     */
    public function getAccountBalanceAttribute(): float
    {
        return $this->calculateAccountBalance();
    }

    /**
     * The payments that belongs to this supplier.
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
     * Get payment history for this supplier.
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
}
