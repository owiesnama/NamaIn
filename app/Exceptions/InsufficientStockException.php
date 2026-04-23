<?php

namespace App\Exceptions;

use App\Models\Product;
use App\Models\Storage;
use Exception;

class InsufficientStockException extends Exception
{
    public function __construct(public Product $product, public Storage $storage)
    {
        parent::__construct("Insufficient stock for product [{$product->name}] in storage [{$storage->name}].");
    }
}
