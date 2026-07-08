<?php

use App\Models\Cheque;
use App\Notifications\ChequeDueNotification;

it('delivers cheque-due reminders in-app as well as by mail', function () {
    actingAsTenantUser();

    $cheque = Cheque::factory()->create();
    $notification = new ChequeDueNotification($cheque);

    expect($notification->via(auth()->user()))->toBe(['mail', 'database', 'broadcast'])
        ->and($notification->databaseType(auth()->user()))->toBe('cheque_due')
        ->and($notification->broadcastType())->toBe('cheque_due');
});

it('exposes a translatable title and a relative url in the payload', function () {
    actingAsTenantUser();

    $cheque = Cheque::factory()->create();
    $payload = (new ChequeDueNotification($cheque))->toArray(auth()->user());

    expect($payload['title'])->toBe('Cheque :reference is due soon')
        ->and($payload['title_params'])->toBe(['reference' => "#{$cheque->reference_number}"])
        ->and($payload['url'])->toStartWith('/cheques?search=')
        ->and($payload['meta']['reference_number'])->toBe($cheque->reference_number);
});
