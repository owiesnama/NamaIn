<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Storage;
use App\Models\Vendor;
use Faker\Generator;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        /**
         * create cutomers
         *
         * create vendors
         * create cheques
         * create products
         * create storage
         * create invocies for sales and purchases
         */
        $faker = app()->make(Generator::class);
        $customers = Customer::factory(10)->create();
        $vendors = Vendor::factory(10)->create();
        $products = Product::factory(10)->create();
        $storages = Storage::factory(10)->create();
        /**
         *             'total' => 'required|integer',
            'products.*.product' => 'integer|required',
            'products.*.quantity' => 'integer|required',
            'products.*.price' => 'integer|required',
         */
        $customers->concat($vendors)->each(function ($customer) use ($faker) {
            $customer->cheques()->create([
                'amount' => $faker->numberBetween(5000, 1000000),
                'type' => $faker->randomElement([0, 1]),
                'due' => $faker->dateTimeBetween('now', '+6 months'),
            ]);
        });
        $attributes = collect([
            'products' => $items = $products->map(function ($prodcut) {
                return [
                    'product' => $prodcut->id,
                    'quantity' => rand(1, 20),
                    'price' => $prodcut->cost * 1.75,
                ];
            }),
            'total' => $items->sum(function ($item) {
                return $item['price'] * $item['quantity'];
            }),
        ]);

        Invoice::sale($attributes)->save();
        Invoice::purchase($attributes)->save();

        \App\Models\User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
