<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends BaseModel
{
    use HasFactory, SoftDeletes;

    public $guarded = [];

    public function details()
    {
        return $this->hasMany(InvoiceDetails::class);
    }

    public static function purchase($attributes)
    {
        $invoice = static::createInvoiceFor(Vendor::class, $attributes);
        $invoice->addDetails(collect($attributes->get('products'))->map(function ($prodcut) {
            $prodcut['product_id'] = $prodcut['product'];
            unset($prodcut['product']);

            return $prodcut;
        }));

        return $invoice;
    }

    public static function sale($attributes)
    {
        $invoice = static::createInvoiceFor(Customer::class, $attributes);
        $invoice->addDetails(collect($attributes->get('products'))->map(function ($prodcut) {
            $prodcut['product_id'] = $prodcut['product'];
            $prodcut['unit_id'] = $prodcut['unit'] ?? null;
            unset($prodcut['product']);

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

    public function addDetails($products)
    {
        $this->fresh()
            ->details()
            ->createMany($products);
    }

    public function markAsUsed($used = true)
    {
        $this->has_used = $used;
        $this->save();

        return $this;
    }
}
