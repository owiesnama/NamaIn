<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingAddon extends BaseModel
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_delta' => MoneyCast::class,
        ];
    }

    /**
     * The booking this snapshot line belongs to.
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * The source add-on this line was snapshotted from (nullable — nulls out if
     * the source add-on is later deleted; the snapshot name/price remain).
     */
    public function serviceAddon(): BelongsTo
    {
        return $this->belongsTo(ServiceAddon::class);
    }
}
