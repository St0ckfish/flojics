# Testing

Pest covers the cases the PDF asked for. Feature tests use `RefreshDatabase` and SQLite in memory (see `phpunit.xml`). CI installs `pdo_sqlite`.

## Test cases

| Case | Where | Expected |
|---|---|---|
| **Successful escalation** | `TicketEscalationTest` | `200`. `status=escalated`, `escalated_at` set, one pending log per channel, two jobs queued. Sends happen in the worker, not in the HTTP response. |
| **Invalid ticket** | `TicketEscalationTest` | Unknown id → `404`. |
| **Already escalated** | `TicketEscalationTest` | `409`. No extra logs. |
| **Validation** | `TicketEscalationTest` | Channel `fax` → `422`. Ticket stays open. |
| **Notification failure** | `NotificationRetryTest` | Mock `send()` throws. Ticket stays escalated. Log `pending`, `attempts=1`. |
| **Retry success** | `NotificationRetryTest` | First `send()` throws, second succeeds. `attempts=2`, `status=sent`. |
| **Retry exhausted** | `NotificationRetryTest` | Three throws + `failed()`. `status=failed`, `error_message` stored. |
| **Job dispatch** | `TicketEscalationTest` | `Queue::fake()` — one job when only `email` is requested. |
| **Job send** | `TicketEscalationTest` | Job `handle()` + `Notification::fake()` — both channels become `sent`. |
| **Show ticket** | `TicketEscalationTest` | `GET /api/tickets/{id}` returns subject and status. |
| **Schema / relations** | `TicketSchemaTest` | Customer + optional agent + logs. |

## How they are tested

- `Queue::fake()` on the HTTP escalate path: the response has `pending` logs and the jobs are queued, not sent inline.
- `Notification::fake()` when job `handle()` runs the real Email/Slack adapters.
- Mockery `EscalationChannel` bound into `EscalationChannelRegistry` to simulate timeout / webhook / mailer failure without hitting the network.
- Job `handle()` / `failed()` called directly to simulate Laravel's 3 attempts.

## Self-testing notes

Ran locally after implementation:

```bash
cd backend
./vendor/bin/pint --test
./vendor/bin/phpstan analyse --memory-limit=1G
./vendor/bin/pest
```

Pint and Larastan passed on this machine. Feature tests use in-memory SQLite (`phpunit.xml`). They need the `pdo_sqlite` PHP extension (installed in CI). Without that extension they fail with `could not find driver` — that is an environment gap, not a failing assertion.

Manual check with a **fresh** MySQL seed (`php artisan migrate:fresh --seed`, database `flojics`):

1. `php artisan serve` and `php artisan queue:work`
2. `GET /api/tickets/1` — open ticket, empty logs (ids 1–4 start open; 7 and 8 start escalated)
3. `POST /api/tickets/1/escalate` — status escalated, two `notification_logs` rows (`pending` until the worker runs)
4. Email (`MAIL_MAILER=log`) should become `sent` after the worker runs (written to `storage/logs/laravel.log`, not Gmail)
5. Slack without a token should retry 3 times and finish `failed` with a clear error — that is the required failure path, not a bug
6. `POST /api/tickets/7/escalate` — already escalated in the seeder → `409`
7. UI: [http://localhost:5173/tickets/4](http://localhost:5173/tickets/4) — Escalate, badges, logs. Ticket `7` shows the button disabled. Ticket `9999` shows the compact 404.

CI (`.github/workflows/ci.yml`) runs Pint, Larastan, and Pest on every push.
