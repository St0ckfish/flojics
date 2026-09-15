<?php

use App\Enums\NotificationLogStatus;
use App\Enums\TicketStatus;
use App\Jobs\DispatchEscalationNotificationJob;
use App\Models\Ticket;
use App\NotificationChannels\Contracts\EscalationChannel;
use App\NotificationChannels\EscalationChannelRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\FakeFlakyChannel;

uses(RefreshDatabase::class);

function bindChannel(EscalationChannel $channel): void
{
    app()->instance(
        EscalationChannelRegistry::class,
        new EscalationChannelRegistry([$channel]),
    );
}

test('a notification failure leaves the ticket escalated and the log pending', function () {
    Queue::fake();

    $ticket = Ticket::factory()->create();
    $channel = new FakeFlakyChannel(failuresBeforeSuccess: 3, errorMessage: 'Email service unavailable');
    bindChannel($channel);

    $this->postJson("/api/tickets/{$ticket->id}/escalate", [
        'channels' => ['email'],
    ])->assertOk()
        ->assertJsonPath('data.status', TicketStatus::Escalated->value);

    $log = $ticket->fresh()->notificationLogs()->first();

    expect($log)->not->toBeNull()
        ->and($ticket->fresh()->isEscalated())->toBeTrue()
        ->and($log->status)->toBe(NotificationLogStatus::Pending)
        ->and($log->attempts)->toBe(0);

    $job = new DispatchEscalationNotificationJob($ticket->id, 'email', $log->id);

    expect(fn () => $job->handle(app(EscalationChannelRegistry::class)))
        ->toThrow(RuntimeException::class, 'Email service unavailable');

    $log->refresh();

    expect($log->status)->toBe(NotificationLogStatus::Pending)
        ->and($log->attempts)->toBe(1)
        ->and($log->error_message)->toBe('Email service unavailable')
        ->and($log->sent_at)->toBeNull();
});

test('a notification is marked sent on the third attempt after two failures', function () {
    $ticket = Ticket::factory()->escalated()->create();
    $log = $ticket->notificationLogs()->create([
        'channel' => 'email',
        'status' => NotificationLogStatus::Pending,
    ]);

    $channel = new FakeFlakyChannel(failuresBeforeSuccess: 2, errorMessage: 'Timeout');
    bindChannel($channel);

    $job = new DispatchEscalationNotificationJob($ticket->id, 'email', $log->id);
    $registry = app(EscalationChannelRegistry::class);

    expect(fn () => $job->handle($registry))->toThrow(RuntimeException::class, 'Timeout');
    expect(fn () => $job->handle($registry))->toThrow(RuntimeException::class, 'Timeout');

    expect($log->fresh()->status)->toBe(NotificationLogStatus::Pending)
        ->and($log->fresh()->attempts)->toBe(2)
        ->and($log->fresh()->error_message)->toBe('Timeout');

    $job->handle($registry);

    $log->refresh();

    expect($log->status)->toBe(NotificationLogStatus::Sent)
        ->and($log->attempts)->toBe(3)
        ->and($log->sent_at)->not->toBeNull()
        ->and($log->error_message)->toBeNull()
        ->and($channel->sendCount)->toBe(3);
});

test('retry exhaustion stores the final failed result and does not send again', function () {
    $ticket = Ticket::factory()->escalated()->create();
    $log = $ticket->notificationLogs()->create([
        'channel' => 'slack',
        'status' => NotificationLogStatus::Pending,
    ]);

    $channel = new FakeFlakyChannel(
        channelKey: 'slack',
        failuresBeforeSuccess: 3,
        errorMessage: 'Slack webhook failure',
    );
    bindChannel($channel);

    $job = new DispatchEscalationNotificationJob($ticket->id, 'slack', $log->id);
    $registry = app(EscalationChannelRegistry::class);

    $lastError = null;

    for ($i = 0; $i < $job->tries; $i++) {
        try {
            $job->handle($registry);
        } catch (RuntimeException $e) {
            $lastError = $e;
        }
    }

    expect($lastError)->toBeInstanceOf(RuntimeException::class)
        ->and($channel->sendCount)->toBe(3);

    $job->failed($lastError);

    $log->refresh();

    expect($log->status)->toBe(NotificationLogStatus::Failed)
        ->and($log->attempts)->toBe(3)
        ->and($log->error_message)->toBe('Slack webhook failure')
        ->and($ticket->fresh()->isEscalated())->toBeTrue()
        ->and($channel->sendCount)->toBe(3);

    $job->handle($registry);

    expect($channel->sendCount)->toBe(3)
        ->and($log->fresh()->attempts)->toBe(3)
        ->and($log->fresh()->status)->toBe(NotificationLogStatus::Failed);
});

test('a sent log is not delivered again when the job is replayed', function () {
    $ticket = Ticket::factory()->escalated()->create();
    $log = $ticket->notificationLogs()->create([
        'channel' => 'email',
        'status' => NotificationLogStatus::Sent,
        'attempts' => 1,
        'sent_at' => now(),
    ]);

    $channel = new FakeFlakyChannel(failuresBeforeSuccess: 0);
    bindChannel($channel);

    (new DispatchEscalationNotificationJob($ticket->id, 'email', $log->id))
        ->handle(app(EscalationChannelRegistry::class));

    expect($channel->sendCount)->toBe(0)
        ->and($log->fresh()->attempts)->toBe(1);
});

test('notification jobs use three attempts and two backoff delays', function () {
    $job = new DispatchEscalationNotificationJob(1, 'email', 1);

    expect($job->tries)->toBe(3)
        ->and($job->backoff())->toBe([10, 30]);
});
