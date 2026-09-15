<?php

use App\Enums\NotificationLogStatus;
use App\Enums\TicketStatus;
use App\Jobs\DispatchEscalationNotificationJob;
use App\Models\Ticket;
use App\NotificationChannels\EscalationChannelRegistry;
use App\Notifications\TicketEscalatedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.slack.notifications.bot_user_oauth_token', 'xoxb-test-token');
    config()->set('services.slack.notifications.channel', '#escalations');
    config()->set('escalation.slack_channel', '#escalations');
    config()->set('escalation.mail_to', 'ops@example.com');
});

test('a ticket can be escalated and notifications are queued per channel', function () {
    Queue::fake();

    $ticket = Ticket::factory()->create();

    $this->postJson("/api/tickets/{$ticket->id}/escalate")
        ->assertOk()
        ->assertJsonPath('data.id', $ticket->id)
        ->assertJsonPath('data.status', TicketStatus::Escalated->value)
        ->assertJsonPath('data.notification_logs.0.status', NotificationLogStatus::Pending->value)
        ->assertJsonCount(2, 'data.notification_logs');

    $ticket->refresh();

    expect($ticket->status)->toBe(TicketStatus::Escalated)
        ->and($ticket->escalated_at)->not->toBeNull()
        ->and($ticket->notificationLogs)->toHaveCount(2)
        ->and($ticket->notificationLogs->every(
            fn ($log): bool => $log->status === NotificationLogStatus::Pending,
        ))->toBeTrue();

    Queue::assertPushed(DispatchEscalationNotificationJob::class, 2);
});

test('queued jobs send email and slack and mark logs sent', function () {
    Notification::fake();

    $ticket = Ticket::factory()->create();

    $this->postJson("/api/tickets/{$ticket->id}/escalate")->assertOk();

    $registry = app(EscalationChannelRegistry::class);

    foreach ($ticket->fresh()->notificationLogs as $log) {
        (new DispatchEscalationNotificationJob($ticket->id, $log->channel, $log->id))
            ->handle($registry);
    }

    expect($ticket->fresh()->notificationLogs->every(
        fn ($log): bool => $log->status === NotificationLogStatus::Sent && $log->sent_at !== null,
    ))->toBeTrue();

    Notification::assertSentOnDemandTimes(TicketEscalatedNotification::class, 2);
});

test('an unknown ticket cannot be escalated', function () {
    $this->postJson('/api/tickets/999999/escalate')
        ->assertNotFound()
        ->assertExactJson(['message' => 'Ticket [999999] was not found.']);
});

test('an unknown ticket cannot be retrieved', function () {
    $this->getJson('/api/tickets/999999')
        ->assertNotFound()
        ->assertExactJson(['message' => 'Ticket [999999] was not found.']);
});

test('an already escalated ticket is rejected', function () {
    $ticket = Ticket::factory()->escalated()->create();

    $this->postJson("/api/tickets/{$ticket->id}/escalate")
        ->assertConflict()
        ->assertJsonPath('message', "Ticket [{$ticket->id}] is already escalated.");

    expect($ticket->notificationLogs()->count())->toBe(0);
});

test('unknown notification channels are rejected', function () {
    $ticket = Ticket::factory()->create();

    $this->postJson("/api/tickets/{$ticket->id}/escalate", [
        'channels' => ['fax'],
    ])->assertUnprocessable();

    expect($ticket->fresh()->isEscalated())->toBeFalse();
});

test('duplicate channel keys in the request are rejected', function () {
    $ticket = Ticket::factory()->create();

    $this->postJson("/api/tickets/{$ticket->id}/escalate", [
        'channels' => ['email', 'email'],
    ])->assertUnprocessable();

    expect($ticket->fresh()->isEscalated())->toBeFalse()
        ->and($ticket->notificationLogs()->count())->toBe(0);
});

test('a single ticket can be retrieved after seeding', function () {
    $ticket = Ticket::factory()->create([
        'subject' => 'VPN drops every hour',
    ]);

    $this->getJson("/api/tickets/{$ticket->id}")
        ->assertOk()
        ->assertJsonPath('data.subject', 'VPN drops every hour')
        ->assertJsonPath('data.status', TicketStatus::Open->value);
});

test('jobs are dispatched once per selected channel', function () {
    Queue::fake();

    $ticket = Ticket::factory()->create();

    $this->postJson("/api/tickets/{$ticket->id}/escalate", [
        'channels' => ['email'],
    ])->assertOk();

    Queue::assertPushed(DispatchEscalationNotificationJob::class, 1);
    Queue::assertPushed(
        DispatchEscalationNotificationJob::class,
        fn (DispatchEscalationNotificationJob $job): bool => $job->channelKey === 'email' && $job->ticketId === $ticket->id,
    );
});
