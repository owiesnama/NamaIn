<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class Invoice extends BaseModel
{
    use SoftDeletes;

    /**
     * Attributes can be mass assigned.
     */
    protected $fillable = ['total'];

    /**
     * List of attributes to cast along with what to cast to.
     *
     * @var array<string,string>
     */
    protected $casts = [
        'status' => InvoiceStatus::class,
    ];

    /**
     * List of attributes to append to this invoice
     *
     * @var array<string>
     */
    protected $appends = ['locked'];

    /**
     * Transactions belong to this invoice
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * related invocable to the invoice
     *
     * @return MorphTo
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
        return Attribute::make(get: fn() => $this->status == InvoiceStatus::Delivered);
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

        return $invocable->invoices()->create([
            'total' => $attributes->get('total'),
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
    public function scopeDelivered(Builder $builder, Carbon $datetime = null): Builder
    {
        return $builder->where('delivered', true)
            ->when($datetime, fn($query) => $query->where('created_at', '>', $datetime));
    }
}
