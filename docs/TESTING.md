# Testing

Pest covers the cases the PDF asked for. Local Feature tests use `RefreshDatabase` and SQLite in memory (`phpunit.xml`). GitHub Actions runs the same suite against MySQL 8.4 so `lockForUpdate` and the unique outbox index are exercised on the required engine.

## Test cases

| Case | Where | Expected |
|---|---|---|
| **Successful escalation** | `TicketEscalationTest` | `200`. `status=escalated`, `escalated_at` set, one pending log per channel, two jobs queued. Sends happen in the worker, not in the HTTP response. |
| **Invalid ticket** | `TicketEscalationTest` | Unknown id → `404`. |
| **Already escalated** | `TicketEscalationTest` | `409`. No extra logs. |
| **Validation** | `TicketEscalationTest` | Channel `fax` or `["email","email"]` → `422`. Ticket stays open. |
| **Unique outbox** | `TicketSchemaTest` | A second log for the same ticket + channel raises `UniqueConstraintViolationException`. |
| **Selected channels** | `TicketEscalationTest` | `channels: ["email"]` → one job. |
| **Notification failure** | `NotificationRetryTest` | `FakeFlakyChannel` throws. Ticket stays escalated. Log `pending`, `attempts=1`. |
| **Retry success** | `NotificationRetryTest` | Fail, fail, success. `attempts=3`, `status=sent`, `sent_at` set, `error_message` cleared. |
| **Retry exhausted** | `NotificationRetryTest` | Three throws + `failed()`. `status=failed`, last error stored. A fourth `handle()` does not send. |
| **Job replay** | `NotificationRetryTest` | A `sent` log is not delivered again. |
| **Job send** | `TicketEscalationTest` | Job `handle()` + `Notification::fake()` — both channels become `sent`. |
| **Show ticket** | `TicketEscalationTest` | `GET /api/tickets/{id}` returns subject and status. |
| **Schema / relations** | `TicketSchemaTest` | Customer + optional agent + logs. |
| **Concurrent escalate** | `ConcurrentEscalationTest` | Two overlapping Action calls. One win, one `TicketAlreadyEscalated`. One email log. MySQL + `pcntl` only. |

## How they are tested

- `Queue::fake()` on the HTTP escalate path: the response has `pending` logs and the jobs are queued, not sent inline.
- `Notification::fake()` when job `handle()` runs the real Email/Slack adapters.
- `Tests\Support\FakeFlakyChannel` bound into `EscalationChannelRegistry` to control fail/success counts without Email or Slack.
- Job `handle()` / `failed()` called directly to simulate Laravel's 3 attempts.
- Concurrent coverage uses `pcntl_fork` and `DatabaseMigrations` (committed rows, two connections). The file skips before connecting when `DB_CONNECTION` is not `mysql` or `pcntl` is missing, so local SQLite (or a missing `pdo_sqlite`) does not fail this case.

## Self-testing notes

```bash
cd backend
./vendor/bin/pint --test
./vendor/bin/phpstan analyse --memory-limit=1G
./vendor/bin/pest
```

Feature tests need `pdo_sqlite` locally. CI uses `pdo_mysql` and MySQL 8.4 (`flojics_test`). The concurrency test is skipped locally unless you point Pest at MySQL.

Manual check with a **fresh** MySQL seed (`php artisan migrate:fresh --seed`, database `flojics`):

1. `php artisan serve` and `php artisan queue:work`
2. `GET /api/tickets/1` — open ticket, empty logs (ids 1–4 start open; 7 and 8 start escalated)
3. `POST /api/tickets/1/escalate` — status escalated, two `notification_logs` rows (`pending` until the worker runs)
4. Email (`MAIL_MAILER=log`) should become `sent` after the worker runs (written to `storage/logs/laravel.log`, not Gmail)
5. Slack without a token should retry 3 times and finish `failed` with a clear error — that is the required failure path, not a bug
6. `POST /api/tickets/7/escalate` — already escalated in the seeder → `409`
7. UI: [http://localhost:5173/tickets/4](http://localhost:5173/tickets/4) — Escalate, badges, logs. Ticket `7` shows the button disabled. Ticket `9999` shows the compact 404.

CI (`.github/workflows/ci.yml`) runs Pint, Larastan, and Pest on MySQL on every push.
