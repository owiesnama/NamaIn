<?php

namespace Database\Seeders;

use App\Models\Bank;
use Illuminate\Database\Seeder;

class BankSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $banks = [
            ['name' => 'Bank of Palestine'],
            ['name' => 'Arab Bank'],
            ['name' => 'Palestine Islamic Bank'],
            ['name' => 'Quds Bank'],
            ['name' => 'National Bank'],
            ['name' => 'Cairo Amman Bank'],
            ['name' => 'Palestine Investment Bank'],
        ];

        foreach ($banks as $bank) {
            Bank::updateOrCreate(['name' => $bank['name']], $bank);
        }
    }
}
