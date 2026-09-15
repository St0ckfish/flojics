<?php

use App\Enums\NotificationLogStatus;
use App\Enums\TicketStatus;
use App\Jobs\DispatchEscalationNotificationJob;
use App\Models\Ticket;
use App\NotificationChannels\Contracts\EscalationChannel;
use App\NotificationChannels\EscalationChannelRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use RuntimeException;

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

    $channel = Mockery::mock(EscalationChannel::class);
    $channel->shouldReceive('key')->andReturn('email');
    $channel->shouldReceive('send')
        ->once()
        ->andThrow(new RuntimeException('Email service unavailable'));
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
        ->and($log->sent_at)->toBeNull();
});

test('a failed notification is marked sent after a later retry succeeds', function () {
    $ticket = Ticket::factory()->escalated()->create();
    $log = $ticket->notificationLogs()->create([
        'channel' => 'email',
        'status' => NotificationLogStatus::Pending,
    ]);

    $attempts = 0;

    $channel = Mockery::mock(EscalationChannel::class);
    $channel->shouldReceive('key')->andReturn('email');
    $channel->shouldReceive('send')->twice()->andReturnUsing(function () use (&$attempts): void {
        $attempts++;

        if ($attempts === 1) {
            throw new RuntimeException('Timeout');
        }
    });
    bindChannel($channel);

    $job = new DispatchEscalationNotificationJob($ticket->id, 'email', $log->id);

    expect(fn () => $job->handle(app(EscalationChannelRegistry::class)))
        ->toThrow(RuntimeException::class, 'Timeout');

    expect($log->fresh()->status)->toBe(NotificationLogStatus::Pending)
        ->and($log->fresh()->attempts)->toBe(1);

    $job->handle(app(EscalationChannelRegistry::class));

    $log->refresh();

    expect($log->status)->toBe(NotificationLogStatus::Sent)
        ->and($log->attempts)->toBe(2)
        ->and($log->sent_at)->not->toBeNull();
});

test('retry exhaustion stores the final failed result', function () {
    $ticket = Ticket::factory()->escalated()->create();
    $log = $ticket->notificationLogs()->create([
        'channel' => 'slack',
        'status' => NotificationLogStatus::Pending,
    ]);

    $channel = Mockery::mock(EscalationChannel::class);
    $channel->shouldReceive('key')->andReturn('slack');
    $channel->shouldReceive('send')
        ->times(3)
        ->andThrow(new RuntimeException('Slack webhook failure'));
    bindChannel($channel);

    $job = new DispatchEscalationNotificationJob($ticket->id, 'slack', $log->id);
    $registry = app(EscalationChannelRegistry::class);

    $lastError = null;

    for ($i = 0; $i < 3; $i++) {
        try {
            $job->handle($registry);
        } catch (RuntimeException $e) {
            $lastError = $e;
        }
    }

    expect($lastError)->toBeInstanceOf(RuntimeException::class);

    $job->failed($lastError);

    $log->refresh();

    expect($log->status)->toBe(NotificationLogStatus::Failed)
        ->and($log->attempts)->toBe(3)
        ->and($log->error_message)->toBe('Slack webhook failure')
        ->and($ticket->fresh()->isEscalated())->toBeTrue();
});
