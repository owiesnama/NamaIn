<?php

use Tests\TestCase;

uses(TestCase::class);

/**
 * Guards against the classic Horizon misconfiguration where a worker's
 * `timeout` is greater than or equal to the queue connection's `retry_after`.
 * When that happens, long jobs are released back onto the queue while still
 * running, so they get processed by multiple workers at once, saturating the
 * supervisors and making every queue appear to hang.
 */
it('keeps every horizon supervisor timeout below its connection retry_after', function () {
    $supervisors = config('horizon.defaults');

    expect($supervisors)->not->toBeEmpty();

    foreach ($supervisors as $name => $options) {
        $timeout = $options['timeout'] ?? 60;
        $connection = $options['connection'];
        $retryAfter = config("queue.connections.{$connection}.retry_after");

        expect($retryAfter)
            ->not->toBeNull("Connection [{$connection}] used by [{$name}] has no retry_after configured.")
            ->toBeGreaterThan(
                $timeout,
                "Supervisor [{$name}] timeout ({$timeout}s) must be lower than its connection "
                ."[{$connection}] retry_after ({$retryAfter}s) to avoid jobs being retried while still running."
            );
    }
});
