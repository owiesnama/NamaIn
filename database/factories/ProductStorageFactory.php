<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Storage;
use App\Models\ProductStorage;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductStorageFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ProductStorage::class;

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
