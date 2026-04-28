<?php

namespace App\Actions;

use App\Models\Bank;

class ResolveChequeBankAction
{
    public function handle(int|string|null $bankId): ?int
    {
        if (! $bankId) {
            return null;
        }

        if (is_numeric($bankId)) {
            return (int) $bankId;
        }

        return Bank::firstOrCreate(['name' => trim($bankId)])->id;
    }
}
