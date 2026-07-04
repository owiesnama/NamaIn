<?php

use App\Enums\BookingStatus;
use App\Enums\ChequeStatus;
use App\Models\BackupSetting;
use App\Models\Booking;
use App\Models\Cheque;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\BookingReminderNotification;
use App\Notifications\ChequeDueNotification;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('cheques:notify-for-due', function () {
    $admins = User::whereIn('email', config('app.admins', []))->get();

    // Runs without a tenant bound, so the tenant scope must be lifted explicitly.
    $cheques = Cheque::withoutGlobalScopes()
        ->whereIn('status', [ChequeStatus::Issued, ChequeStatus::Deposited])
        ->where('due', '<=', now()->addDays(config('app.cheques_notify_before_days', 3)))
        ->get();

    $admins->each(function ($admin) use ($cheques) {
        $cheques->each(fn ($cheque) => $admin->notify(new ChequeDueNotification($cheque)));
    });
})->purpose('Notify platform admins of cheques due within the configured window');

Artisan::command('bookings:notify-upcoming', function () {
    // Runs without a tenant bound, so the tenant scope must be lifted; each
    // booking's own tenant resolves the merchant recipients. Reminds once,
    // deduped by `reminder_sent_at`.
    $bookings = Booking::withoutGlobalScopes()
        ->with(['service', 'customer'])
        ->where('status', BookingStatus::Confirmed)
        ->whereNull('reminder_sent_at')
        ->where('starts_at', '>=', now())
        ->where('starts_at', '<=', now()->addDay())
        ->get();

    $bookings->groupBy('tenant_id')->each(function ($group, $tenantId) {
        $recipients = Tenant::find($tenantId)?->merchantUsers() ?? collect();

        $group->each(function (Booking $booking) use ($recipients) {
            $recipients->each(fn (User $user) => $user->notify(new BookingReminderNotification($booking)));
            $booking->forceFill(['reminder_sent_at' => now()])->saveQuietly();
        });
    });
})->purpose('Remind merchants of bookings starting within the next 24 hours');

Schedule::command('cheques:notify-for-due')->daily();
Schedule::command('bookings:notify-upcoming')->hourly();
Schedule::call(fn () => DB::table('notifications')->where('created_at', '<', now()->subDays(90))->delete())
    ->daily()
    ->name('notifications:prune');
Schedule::command('expenses:generate-recurring')->daily();
// Post-cutover guardrail: surface any drift between the stocks cache and the
// stock-movement ledger (source of truth). Read-only.
Schedule::command('stock:reconcile')->daily();
Schedule::command('exports:prune')->daily();
Schedule::command('sync:prune-snapshots')->daily();
Schedule::command('telescope:prune --hours=48')->daily();
Schedule::command('horizon:snapshot')->everyFiveMinutes();
try {
    $backupSettings = BackupSetting::resolve();
    if ($backupSettings->is_enabled) {
        Schedule::command('backup:scheduled')->cron($backupSettings->cronExpression());
    }
} catch (Throwable) {
    // Table may not exist yet (e.g., during migrations or testing)
}

Schedule::call(function () {
    $files = Storage::disk('local')->files('tmp');
    foreach ($files as $file) {
        if (Storage::disk('local')->lastModified($file) < now()->subDay()->getTimestamp()) {
            Storage::disk('local')->delete($file);
        }
    }
})->daily();
