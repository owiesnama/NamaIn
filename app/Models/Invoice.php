<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $casts = [
        'status' => InvoiceStatus::class,
    ];

    protected $appends = ['locked'];

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function locked(): Attribute
    {
        return Attribute::make(get: fn () => $this->status == InvoiceStatus::Delivered);
    }

    public static function purchase($attributes)
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

    public static function sale($attributes)
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

    public static function createInvoiceFor($invoicable, $attributes)
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

    public function addTransaction($products)
    {
        $this->fresh()
            ->transactions()
            ->createMany($products);
    }

    public function markAs(InvoiceStatus $status)
    {
        $this->status = $status;
        $this->save();

        return $this;
    }
}
