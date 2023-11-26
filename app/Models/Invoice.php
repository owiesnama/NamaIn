<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends BaseModel
{
    use SoftDeletes;

    /**
     * @var string[]
     */
    protected $casts = [
        'status' => InvoiceStatus::class,
    ];

    /**
     * @var string[]
     */
    protected $appends = ['locked'];

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Adds an attribute to the invoice showing whether it's delivered and should
     * be locked
     */
    public function locked(): Attribute
    {
        return Attribute::make(get: fn () => $this->status == InvoiceStatus::Delivered);
    }

    /**
     * Adds a purchase transaction for the invoice
     */
    public static function purchase($attributes): static
    {
        $invoice = static::createInvoiceFor(Supplier::class, $attributes);
        $invoice->addTransaction(collect($attributes->get('products'))->map(function ($prodcut) {
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
     * Adds a sale transaction for the invoice
     */
    public static function sale($attributes): static
    {
        $invoice = static::createInvoiceFor(Customer::class, $attributes);
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

    public static function createInvoiceFor($invoicable, $attributes): static
    {
        $invoice = new static();
        $invoice->invoicable_type = $invoicable;
        $invoice->invoicable_id = (new $invoicable)->firstOrCreate([
            'name' => 'Random',
            'address' => 'no-address',
        ])->id;
        $invoice->total = $attributes->get('total');
        $invoice->save();

        return $invoice;
    }

    public function addTransaction($products): void
    {
        $this->fresh()
            ->transactions()
            ->createMany($products);
    }

    /**
     * Mark the invoice with a given status
     *
     * @return $this
     */
    public function markAs(InvoiceStatus $status): static
    {
        $this->status = $status;
        $this->save();

        return $this;
    }
}
