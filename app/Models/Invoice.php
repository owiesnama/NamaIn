<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentDirection;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Traits\WithTrashScope;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class Invoice extends BaseModel
{
    use HasFactory, SoftDeletes, WithTrashScope;

    /**
     * List of searchable model's relation attributes
     *
     * @var array<string>
     */
    protected array $searchable = ['serial_number', 'payment_method', 'payment_status', 'currency'];

    protected array $searchableRelationsAttributes = ['invocable.name'];

    /**
     * Attributes can be mass assigned.
     */
    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (Invoice $invoice) {
            $invoice->currency = $invoice->currency ?? preference('currency', 'SDG');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'payment_method' => PaymentMethod::class,
            'payment_status' => PaymentStatus::class,
            'total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'discount' => 'decimal:2',
        ];
    }

    /**
     * List of attributes to append to this invoice
     *
     * @var array<string>
     */
    protected $appends = ['locked', 'remaining_balance', 'is_fully_paid'];

    /**
     * Transactions belong to this invoice
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Payments made for this invoice
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * related invocable to the invoice
     */
    public function invocable(): MorphTo
    {
        return $this->morphTo();
    }

    public function posSession(): BelongsTo
    {
        return $this->belongsTo(PosSession::class);
    }

    /**
     * Adds an attribute to the invoice showing whether it's delivered and should
     * be locked.
     */
    public function getLockedAttribute(): bool
    {
        return $this->status == InvoiceStatus::Delivered;
    }

    /**
     * Mark the invoice with a given status
     */
    public function markAs(InvoiceStatus $status): Invoice
    {
        $this->status = $status;
        $this->save();

        return $this;
    }

    public function isSale(): bool
    {
        return $this->invocable_type === Customer::class;
    }

    /**
     * Scope for filtering invoices by customer.
     */
    public function scopeForCustomer(Builder $builder): Builder
    {
        return $builder->where('invocable_type', Customer::class);
    }

    /**
     * Scope for invoices belonging to a supplier.
     */
    public function scopeForSupplier(Builder $builder): Builder
    {
        return $builder->where('invocable_type', Supplier::class);
    }

    /**
     * Scope for filtering outstanding invoices.
     */
    public function scopeOutstanding(Builder $builder): Builder
    {
        return $builder->whereIn('payment_status', [PaymentStatus::Unpaid, PaymentStatus::PartiallyPaid]);
    }

    /**
     * Filter invoices to delivered.
     */
    public function scopeDelivered(Builder $builder, ?Carbon $datetime = null): Builder
    {
        return $builder->where('delivered', true)
            ->when($datetime, fn ($query) => $query->where('created_at', '>', $datetime));
    }

    public function scopeFromPos(Builder $builder): Builder
    {
        return $builder->whereNotNull('pos_session_id');
    }

    public function scopeNotFromPos(Builder $builder): Builder
    {
        return $builder->whereNull('pos_session_id');
    }

    /**
     * Get the remaining balance for this invoice.
     */
    public function getRemainingBalanceAttribute(): float
    {
        return max(0, ($this->total - $this->discount) - $this->paid_amount);
    }

    /**
     * Get the subtotal for this invoice.
     */
    public function getSubtotalAttribute(): float
    {
        return $this->transactions()->sum(\DB::raw('(price * quantity) - COALESCE(discount, 0)'));
    }

    /**
     * Check if invoice can be inversed (returned).
     */
    public function getCanBeInversedAttribute(): bool
    {
        return ! $this->is_inverse && $this->status !== InvoiceStatus::Returned;
    }

    /**
     * Relationship: parent invoice for return invoices.
     */
    public function parentInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'parent_invoice_id');
    }

    /**
     * Relationship: inverse invoices (returns) for this invoice.
     */
    public function inverseInvoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'parent_invoice_id');
    }

    /**
     * Create an inverse invoice (return/credit note) for this invoice.
     */
    public function createInverseInvoice(Collection $attributes, string $reason): Invoice
    {
        $inverseInvoice = $this->replicate();
        $inverseInvoice->is_inverse = true;
        $inverseInvoice->parent_invoice_id = $this->id;
        $inverseInvoice->inverse_reason = $reason;
        $inverseInvoice->total = $attributes->get('refund_amount', 0);
        $inverseInvoice->paid_amount = 0;
        $inverseInvoice->discount = 0;
        $inverseInvoice->serial_number = null;
        $inverseInvoice->payment_status = PaymentStatus::Unpaid;
        $inverseInvoice->status = InvoiceStatus::Pending;
        $inverseInvoice->save();

        $products = $attributes->get('products', []);

        // Bulk-load all referenced transactions to avoid N+1 (one query vs one per line).
        $transactionIds = collect($products)->pluck('transaction_id')->filter()->unique();
        $transactions = Transaction::findMany($transactionIds)->keyBy('id');

        foreach ($products as $productData) {
            $transaction = $transactions->get($productData['transaction_id']);

            if ($transaction) {
                $inverseTransaction = $transaction->replicate();
                $inverseTransaction->invoice_id = $inverseInvoice->id;
                $inverseTransaction->quantity = $productData['quantity'];
                $inverseTransaction->base_quantity = $productData['quantity'] * ($transaction->base_quantity / $transaction->quantity);
                $inverseTransaction->save();
            }
        }

        return $inverseInvoice;
    }

    /**
     * Check if the invoice is fully paid.
     */
    public function getIsFullyPaidAttribute(): bool
    {
        return $this->remaining_balance <= 0;
    }

    /**
     * Record a payment for this invoice.
     */
    public function recordPayment(
        float $amount,
        PaymentMethod $method,
        ?string $reference = null,
        ?string $notes = null,
        ?array $metadata = null,
        ?string $receiptPath = null,
        ?string $paidAt = null,
        PaymentDirection $direction = PaymentDirection::In
    ): Payment {
        $payment = $this->payments()->create([
            'payable_id' => $this->invocable_id,
            'payable_type' => $this->invocable_type,
            'amount' => $amount,
            'payment_method' => $method,
            'direction' => $direction,
            'reference' => $reference,
            'notes' => $notes,
            'paid_at' => $paidAt ?? now(),
            'created_by' => auth()->id(),
            'metadata' => $metadata,
            'receipt_path' => $receiptPath,
        ]);

        $this->updatePaymentStatus();

        return $payment;
    }

    /**
     * Update the payment status based on paid amount.
     */
    public function updatePaymentStatus(): void
    {
        $locked = self::lockForUpdate()->find($this->id);

        $settlingDirection = $locked->invocable_type === Customer::class ? 'in' : 'out';
        $reversingDirection = $settlingDirection === 'in' ? 'out' : 'in';

        $settlingPayments = (float) $locked->payments()->where('direction', $settlingDirection)->sum('amount');
        $reversingPayments = (float) $locked->payments()->where('direction', $reversingDirection)->sum('amount');
        $locked->paid_amount = $settlingPayments - $reversingPayments;

        $netTotal = $locked->total - $locked->discount;

        if ($locked->paid_amount >= $netTotal) {
            $locked->payment_status = PaymentStatus::Paid;
        } elseif ($locked->paid_amount > 0) {
            $locked->payment_status = PaymentStatus::PartiallyPaid;
        } else {
            $locked->payment_status = PaymentStatus::Unpaid;
        }

        $locked->save();

        $this->refresh();
    }
}
