<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Storage;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
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
        $faker = app()->make(Generator::class);

        $customers = Customer::factory(10)->create();
        $vendors = Supplier::factory(3)->create();
        $products = Product::factory(3)->create();
        $storages = Storage::factory(3)->create();

        $customers->concat($vendors)->each(function ($customer) use ($faker) {
            $customer->cheques()->create([
                'amount' => $faker->numberBetween(5000, 1000000),
                'type' => $faker->randomElement([0, 1]),
                'Bank' => $faker->colorName(),
                'due' => $faker->dateTimeBetween('now', '+6 months'),
            ]);
        });

        $products->each(fn($product) => $product->units()->create(Unit::factory()->make()->toArray()));
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
