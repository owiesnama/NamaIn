<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The daily reconciliation digest (Design 04 §3.2, R8). Deliberately a once-daily
 * summary — never per-event — so an "oversell storm" can't flood inboxes. Carries
 * open-item counts by type, a few most recent items, and the device-health
 * warnings (skew/offline) in one section. Localized per tenant via `->locale()`.
 *
 * @phpstan-type DigestSummary array{tenant: string, total_open: int, by_type: array<int, array{label: string, count: int}>, recent: array<int, array{type: string, occurred_at: ?string}>, device_warnings: array<int, array{name: string, health: string}>}
 */
class ReconciliationDigestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  DigestSummary  $summary
     */
    public function __construct(public array $summary) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__(':count open reconciliation items', ['count' => $this->summary['total_open']]))
            ->greeting(__('Reconciliation summary for :tenant', ['tenant' => $this->summary['tenant']]))
            ->line(__('There are :count open reconciliation items that need a decision.', [
                'count' => $this->summary['total_open'],
            ]));

        foreach ($this->summary['by_type'] as $type) {
            $mail->line("• {$type['label']}: {$type['count']}");
        }

        if (! empty($this->summary['device_warnings'])) {
            $mail->line(__('Device health warnings:'));
            foreach ($this->summary['device_warnings'] as $warning) {
                $mail->line("• {$warning['name']} — {$warning['health']}");
            }
        }

        return $mail
            ->action(__('Open reconciliation inbox'), route('reconciliation.index'))
            ->line(__('Resolve items promptly to keep stock and balances accurate.'));
    }
}
