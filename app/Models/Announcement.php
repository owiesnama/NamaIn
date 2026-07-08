<?php

namespace App\Models;

use App\Enums\AnnouncementAudience;
use Database\Factories\AnnouncementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    /** @use HasFactory<AnnouncementFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::unguard();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'audience_type' => AnnouncementAudience::class,
            'audience_meta' => 'array',
            'send_email' => 'boolean',
            'sent_at' => 'datetime',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }
}
