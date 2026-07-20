<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\BookingStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        parent::booted();

        // Keep the persisted `ends_at` consistent with the service duration
        // whenever the start moves (or on first save). B2 overlap detection and
        // the B3 calendar range-scan by the stored interval.
        static::saving(function (Booking $booking) {
            if ($booking->isDirty('starts_at') || $booking->ends_at === null) {
                $booking->ends_at = $booking->deriveEndsAt();
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => BookingStatus::class,
            'base_price' => MoneyCast::class,
            'total' => MoneyCast::class,
            'reminder_sent_at' => 'datetime',
        ];
    }

    /**
     * The service product being booked.
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'service_product_id');
    }

    /**
     * The customer the booking is for.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * The snapshotted add-ons selected on this booking.
     */
    public function addons(): HasMany
    {
        return $this->hasMany(BookingAddon::class);
    }

    /**
     * Derive the end instant from the start plus the service duration. Falls
     * back to the start itself when the service has no duration (guarded so a
     * data-layer booking never crashes; a real duration is enforced in B3).
     */
    public function deriveEndsAt(): Carbon
    {
        $start = $this->starts_at instanceof Carbon
            ? $this->starts_at->copy()
            : Carbon::parse($this->starts_at);

        return $start->addMinutes((int) ($this->service?->duration_minutes ?? 0));
    }

    /**
     * Scope to a given booking status.
     */
    public function scopeStatus(Builder $query, BookingStatus $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to confirmed bookings (the only status that constrains scheduling).
     */
    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', BookingStatus::Confirmed);
    }
}
