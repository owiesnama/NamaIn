<?php

namespace App\Exceptions;

use App\Models\Product;
use RuntimeException;

class ManualStockIncreaseNotAllowedException extends RuntimeException
{
    public function __construct(public Product $product)
    {
        parent::__construct("Manual stock increases are not allowed for [{$product->name}] under the purchase-driven strategy. Add stock through a purchase invoice.");
    }
}
