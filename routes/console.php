<?php

use App\Models\Cheque;
use App\Models\Invoice;
use App\Models\User;
use App\Notifications\ChequeDueNotification;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('cheques:notify-for-due', function () {
    $admins = User::whereIn('email', config('app.admins'));
    $admins->each(fn ($admin) => $this->info($admin->email));
    $cheques =  Cheque::where('due', '<', now()->subDay(config('cheques_notify_before_days', 3)));
    $admins->each(function ($admin) use ($cheques) {
        $cheques->each(fn ($cheque) => $admin->notify(new ChequeDueNotification($cheque)));
    });
});
