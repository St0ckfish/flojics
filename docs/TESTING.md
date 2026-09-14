# Testing

Feature tests are not implemented yet. This is the case list the Pest suite will cover, plus how they will be self-tested.

## Test cases

| Case | Expected |
|---|---|
| **Successful escalation** | `200/201`. Ticket `status=escalated`, `escalated_at` set. One `notification_logs` row per requested channel starts as `pending` (or `sent` when the queue is `sync` and channels succeed). |
| **Invalid ticket** | Unknown id → `404`. No log rows. |
| **Already escalated** | Second `POST` → `409`. No extra jobs. |
| **Validation** | Unknown channel key → `422`. |
| **Notification failure** | Channel `send()` throws. Log stays `pending`/`failed` depending on attempt number. Ticket remains escalated. |
| **Retry success** | First `send()` throws, second succeeds. `attempts=2`, `status=sent`, `sent_at` set. |
| **Retry exhausted** | All 3 attempts throw. Job `failed()` sets `status=failed` and `error_message`. |
| **Channel isolation** | Email throws, Slack succeeds. Email log failed/retrying; Slack log sent. |

## How they will be tested

- Pest + `pestphp/pest-plugin-laravel`
- `Illuminate\Support\Facades\Notification::fake()` for mail/Slack payloads
- A mock `EscalationChannel` registered in the registry to simulate timeouts and webhook errors
- `Queue::fake()` / `Bus::fake()` to assert a job is dispatched per channel
- `Queue::partialMock()` or a real fake job `failed()` call for the exhausted path
- HTTP tests against `POST /api/tickets/{id}/escalate`
- SQLite in-memory (`phpunit.xml`) so CI does not need MySQL

## Self-testing notes

After implementation, the checks below should be run locally before opening a PR:

```bash
# backend
cd backend
composer lint
composer analyse
composer test

# frontend
cd frontend
bun run lint
bun run typecheck
bun run build
```

CI (`.github/workflows/ci.yml`) runs the same commands on every push and pull request.

Manual smoke test (after the feature is built):

1. `docker compose up -d` and migrate
2. Open the ticket page, confirm fields render
3. Click Escalate — status and escalation date update without a full reload
4. Stop the mailer / point Slack at a bad token — confirm three attempts and a `failed` row in `notification_logs`
5. Restore the channel — confirm a later ticket can still send

Current repo state: Laravel / Pest smoke tests and frontend lint/build only. Escalation cases will be added with the feature code.
