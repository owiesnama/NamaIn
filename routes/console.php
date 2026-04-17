<?php

use App\Models\Cheque;
use App\Models\User;
use App\Notifications\ChequeDueNotification;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('cheques:notify-for-due', function () {
    $admins = User::whereIn('email', config('app.admins'));
    $cheques = Cheque::where('due', '<', now()->subDay(config('cheques_notify_before_days', 3)));
    $admins->each(function ($admin) use ($cheques) {
        $cheques->each(fn ($cheque) => $admin->notify(new ChequeDueNotification($cheque)));
    });
});

Schedule::command('cheques:notify-for-due')->daily();
Schedule::command('expenses:generate-recurring')->daily();

Schedule::call(function () {
    $files = Storage::disk('local')->files('tmp');
    foreach ($files as $file) {
        if (Storage::disk('local')->lastModified($file) < now()->subDay()->getTimestamp()) {
            Storage::disk('local')->delete($file);
        }
    }
})->daily();
