<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Traits\WithTrashScope;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
    protected $fillable = ['total', 'payment_method', 'payment_status', 'paid_amount', 'discount', 'invocable_id', 'invocable_type', 'serial_number', 'currency'];

    protected static function booted(): void
    {
        static::creating(function (Invoice $invoice) {
            $invoice->currency = $invoice->currency ?? preference('currency', 'USD');
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

    /**
     * Adds an attribute to the invoice showing whether it's delivered and should
     * be locked.
     */
    public function getLockedAttribute(): bool
    {
        return $this->status == InvoiceStatus::Delivered;
    }

    /**
     * Adds a purchase transaction for the invoice.
     */
    public static function purchase(Collection $attributes): Invoice
    {
        $invoice = static::createInvoice($attributes);
        $products = collect($attributes->get('products'));
        $unitIds = $products->pluck('unit')->filter()->unique();
        $units = Unit::whereIn('id', $unitIds)->get()->keyBy('id');

        $invoice->addTransaction($products->map(function ($product) use ($units) {
            $product['product_id'] = $product['product'];
            $unitId = $product['unit_id'] = $product['unit'] ?? null;
            $product['base_quantity'] = $product['quantity'];

            if ($unitId && isset($units[$unitId])) {
                $product['base_quantity'] = $units[$unitId]->conversion_factor * $product['quantity'];
            }

            return $product;
        }));

        return $invoice;
    }

    /**
     * Adds a sale transaction for the invoice.
     */
    public static function sale(Collection $attributes): Invoice
    {
        $invoice = static::createInvoice($attributes);
        $products = collect($attributes->get('products'));
        $unitIds = $products->pluck('unit')->filter()->unique();
        $units = Unit::whereIn('id', $unitIds)->get()->keyBy('id');

        $invoice->addTransaction($products->map(function ($product) use ($units) {
            $product['product_id'] = $product['product'];
            $product['base_quantity'] = $product['quantity'];
            $unitId = $product['unit_id'] = $product['unit'] ?? null;

            if ($unitId && isset($units[$unitId])) {
                $product['base_quantity'] = $units[$unitId]->conversion_factor * $product['quantity'];
            }

            return $product;
        }));

        return $invoice;
    }

    /**
     * Create an invoice for invoice-ables.
     */
    public static function createInvoice(Collection $attributes): Invoice
    {
        $invocable = $attributes->get('invocable');
        $invocableClass = $invocable['type'];
        $invocableId = $invocable['id'];
        $invocable = $invocableClass::find($invocableId);

        $paymentMethod = $attributes->get('payment_method', 'credit');
        $discount = $attributes->get('discount', 0);

        return $invocable->invoices()->create([
            'total' => $attributes->get('total'),
            'payment_method' => $paymentMethod,
            'payment_status' => PaymentStatus::Unpaid,
            'paid_amount' => 0,
            'discount' => $discount,
        ]);
    }

    /**
     * Add new transactions to this invoice.
     */
    public function addTransaction(mixed $products): void
    {
        $this->transactions()
            ->createMany($products);
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
        return $this->transactions()->sum(\DB::raw('price * quantity'));
    }

    /**
     * Check if invoice is fully paid.
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
        ?string $paidAt = null
    ): Payment {
        $payment = $this->payments()->create([
            'payable_id' => $this->invocable_id,
            'payable_type' => $this->invocable_type,
            'amount' => $amount,
            'payment_method' => $method,
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
        $this->paid_amount = $this->payments()->sum('amount');

        $netTotal = $this->total - $this->discount;

        if ($this->paid_amount >= $netTotal) {
            $this->payment_status = PaymentStatus::Paid;
        } elseif ($this->paid_amount > 0) {
            $this->payment_status = PaymentStatus::PartiallyPaid;
        } else {
            $this->payment_status = PaymentStatus::Unpaid;
        }

        $this->save();
    }
}
