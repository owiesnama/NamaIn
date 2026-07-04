<?php

use App\Enums\AnnouncementAudience;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Support\Carbon;

it('casts its attributes', function () {
    $announcement = Announcement::factory()->toUsers([1, 2])->sent()->create(['send_email' => 1]);

    expect($announcement->refresh())
        ->audience_type->toBeInstanceOf(AnnouncementAudience::class)
        ->audience_meta->toBe(['user_ids' => [1, 2]])
        ->send_email->toBeTrue()
        ->sent_at->toBeInstanceOf(Carbon::class);
});

it('belongs to the admin who sent it', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $announcement = Announcement::factory()->create(['admin_user_id' => $admin->id]);

    expect($announcement->admin->is($admin))->toBeTrue();
});
