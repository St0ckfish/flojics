<?php

use App\Actions\EscalateTicketAction;
use App\Exceptions\TicketAlreadyEscalatedException;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/*
 * SQLite (local phpunit.xml) does not honor InnoDB-style row locks across
 * connections. CI exports DB_CONNECTION=mysql and runs this on MySQL 8.4.
 * DatabaseMigrations is applied only then, so a missing pdo_sqlite driver
 * cannot fail this file before skip.
 *
 * Two processes call EscalateTicketAction at the same time; lockForUpdate
 * plus UNIQUE(ticket_id, channel) must leave one escalated ticket and one log.
 */
$supportsMysqlLocks = getenv('DB_CONNECTION') === 'mysql' && function_exists('pcntl_fork');

if ($supportsMysqlLocks) {
    uses(DatabaseMigrations::class);
}

test('overlapping escalations persist one status change and one log per channel', function () {
    Queue::fake();

    $ticketId = Ticket::factory()->create()->id;
    $pid = pcntl_fork();

    expect($pid)->not->toBe(-1);

    if ($pid === 0) {
        DB::purge();
        DB::reconnect();

        try {
            app(EscalateTicketAction::class)->execute($ticketId, ['email']);
            exit(0);
        } catch (TicketAlreadyEscalatedException) {
            exit(1);
        } catch (Throwable $e) {
            exit(2);
        }
    }

    DB::purge();
    DB::reconnect();

    $parentWon = false;
    $parentError = null;

    try {
        app(EscalateTicketAction::class)->execute($ticketId, ['email']);
        $parentWon = true;
    } catch (TicketAlreadyEscalatedException) {
        $parentWon = false;
    } catch (Throwable $e) {
        $parentError = $e;
    }

    pcntl_waitpid($pid, $status);

    if ($parentError !== null) {
        throw $parentError;
    }

    $childExit = pcntl_wifexited($status) ? pcntl_wexitstatus($status) : 99;

    expect($childExit)->toBeIn([0, 1]);

    $wins = (int) $parentWon + (int) ($childExit === 0);

    expect($wins)->toBe(1);

    $ticket = Ticket::query()->with('notificationLogs')->findOrFail($ticketId);

    expect($ticket->isEscalated())->toBeTrue()
        ->and($ticket->escalated_at)->not->toBeNull()
        ->and($ticket->notificationLogs)->toHaveCount(1)
        ->and($ticket->notificationLogs->first()->channel)->toBe('email');
})->skip(
    ! $supportsMysqlLocks,
    'lockForUpdate concurrency requires MySQL InnoDB and pcntl.',
);
