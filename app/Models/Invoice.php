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
    protected array $searchableRelationsAttributes = ['invocable.name'];

    /**
     * Attributes can be mass assigned.
     */
    protected $fillable = ['total', 'payment_method', 'payment_status', 'paid_amount', 'discount', 'invocable_id', 'invocable_type', 'serial_number', 'currency'];

    protected static function booted(): void
    {
        static::creating(function (Invoice $invoice) {
            $invoice->currency = $invoice->currency ?? preference('currency', '$');
        });
    }

    /**
     * List of attributes to cast along with what to cast to.
     *
     * @var array<string,string>
     */
    protected $casts = [
        'status' => InvoiceStatus::class,
        'payment_method' => PaymentMethod::class,
        'payment_status' => PaymentStatus::class,
        'total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'discount' => 'decimal:2',
    ];

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
    public function locked(): Attribute
    {
        return Attribute::make(get: fn () => $this->status == InvoiceStatus::Delivered);
    }

    /**
     * Adds a purchase transaction for the invoice.
     */
    public static function purchase(Collection $attributes): Invoice
    {
        $invoice = static::createInvoice($attributes);
        $invoice->addTransaction(collect($attributes->get('products'))
            ->map(function ($prodcut) {
                $prodcut['product_id'] = $prodcut['product'];
                $unitId = $prodcut['unit_id'] = $prodcut['unit'] ?? null;
                $prodcut['base_quantity'] = $prodcut['quantity'];
                if ($unitId) {
                    $prodcut['base_quantity'] = Unit::find($unitId)->conversion_factor * $prodcut['quantity'];
                }

                return $prodcut;
            }));

        return $invoice;
    }

    /**
     * Adds a sale transaction for the invoice.
     */
    public static function sale(Collection $attributes): Invoice
    {
        $invoice = static::createInvoice($attributes);
        $invoice->addTransaction(collect($attributes->get('products'))->map(function ($prodcut) {
            $prodcut['product_id'] = $prodcut['product'];
            $prodcut['base_quantity'] = $prodcut['quantity'];
            $unitId = $prodcut['unit_id'] = $prodcut['unit'] ?? null;
            if ($unitId) {
                $prodcut['base_quantity'] = Unit::find($unitId)->conversion_factor * $prodcut['quantity'];
            }

            return $prodcut;
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
        $this->fresh()
            ->transactions()
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
    public function remainingBalance(): Attribute
    {
        return Attribute::make(
            get: fn () => max(0, ($this->total - $this->discount) - $this->paid_amount)
        );
    }

    /**
     * Check if invoice is fully paid.
     */
    public function isFullyPaid(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->remaining_balance <= 0
        );
    }

    /**
     * Record a payment for this invoice.
     */
    public function recordPayment(float $amount, PaymentMethod $method, ?string $reference = null, ?string $notes = null): Payment
    {
        $payment = $this->payments()->create([
            'amount' => $amount,
            'payment_method' => $method,
            'reference' => $reference,
            'notes' => $notes,
            'paid_at' => now(),
            'created_by' => auth()->id(),
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
