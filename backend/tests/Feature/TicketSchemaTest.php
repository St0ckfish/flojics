<?php

use App\Enums\TicketStatus;
use App\Models\Customer;
use App\Models\NotificationLog;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a ticket belongs to a customer and can have notification logs', function () {
    $customer = Customer::factory()->create();

    $ticket = Ticket::factory()->recycle($customer)->unassigned()->create([
        'subject' => 'VPN drops every hour',
    ]);

    expect($ticket->customer->is($customer))->toBeTrue()
        ->and($ticket->agent)->toBeNull()
        ->and($ticket->status)->toBe(TicketStatus::Open)
        ->and($ticket->escalated_at)->toBeNull();

    $log = $ticket->notificationLogs()->create([
        'channel' => 'email',
        'status' => 'pending',
    ]);

    expect($log)->toBeInstanceOf(NotificationLog::class)
        ->and($ticket->notificationLogs)->toHaveCount(1)
        ->and($log->ticket->is($ticket))->toBeTrue();
});
