<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Config;

class ResetPassword extends BaseResetPassword implements ShouldQueue
{
    use Queueable;

    public function toMail($notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        $expireMinutes = Config::get(
            'auth.passwords.'.Config::get('auth.defaults.passwords').'.expire'
        );

        return (new MailMessage)
            ->subject(__('Reset Password Notification'))
            ->view('emails.reset-password', [
                'notifiable' => $notifiable,
                'url' => $url,
                'expireMinutes' => $expireMinutes,
            ]);
    }
}
