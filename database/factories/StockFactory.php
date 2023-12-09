<?php

namespace Database\Factories;

<<<<<<<< HEAD:database/factories/StockFactory.php
use App\Models\Product;
use App\Models\Stock;
========
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\Product;
use App\Models\ProductStorage;
>>>>>>>> master:database/factories/ProductStorageFactory.php
use App\Models\Storage;
use Illuminate\Database\Eloquent\Factories\Factory;

<<<<<<<< HEAD:database/factories/StockFactory.php
class StockFactory extends Factory
========
class ProductStorageFactory extends Factory
>>>>>>>> master:database/factories/ProductStorageFactory.php
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
<<<<<<<< HEAD:database/factories/StockFactory.php
    protected $model = Stock::class;
========
    protected $model = ProductStorage::class;
>>>>>>>> master:database/factories/ProductStorageFactory.php

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'product_id' => Product::factory(),
            'storage_id' => Storage::factory(),
        ];
    }
}
