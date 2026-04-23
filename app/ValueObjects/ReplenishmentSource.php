<?php

namespace App\ValueObjects;

use App\Models\Storage;

readonly class ReplenishmentSource
{
    public function __construct(
        public Storage $warehouse,
        public int $availableQuantity,
    ) {}
}
