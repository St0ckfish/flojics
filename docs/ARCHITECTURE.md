# Architecture

How ticket escalation is built: one use-case, swappable channels, and Laravel's queue for retries.

## Folder structure

```
backend/app/
├── Actions/EscalateTicketAction.php
├── Enums/                      # TicketStatus, TicketPriority, EscalationChannelKey, NotificationLogStatus
├── Exceptions/                 # domain exceptions + ConfigureApiExceptions (compact JSON 404/409)
├── Http/
│   ├── Controllers/Api/        # Thin: show ticket, escalate ticket
│   ├── Requests/EscalateTicketRequest.php
│   └── Resources/              # Stable JSON for the React page
├── Jobs/DispatchEscalationNotificationJob.php
├── Models/
├── NotificationChannels/
│   ├── Contracts/EscalationChannel.php
│   ├── EscalationChannelRegistry.php
│   ├── EmailEscalationChannel.php
│   └── SlackEscalationChannel.php
└── Notifications/TicketEscalatedNotification.php
```

The frontend stays a separate Vite app. PHP does not go through Turborepo.

```
frontend/src/
├── pages/TicketPage.tsx
├── hooks/useTicket.ts
├── hooks/useEscalateTicket.ts
├── lib/api.ts                  # Axios client
├── lib/ticket.ts               # Zod schemas + GET/POST
└── components/                 # TicketNav (shadcn Select), badges, notification logs
```

## Design decisions

| Decision | Why |
|---|---|
| **Action, not a fat controller** | `EscalateTicketAction` is the use-case. HTTP only validates and maps exceptions. The same action can be called later from an SLA command. |
| **Strategy + config registry** | Email and Slack implement `EscalationChannel`. Classes are listed in `config/escalation.php`. The action and controller never `switch` on channel names. |
| **Domain exceptions** | `TicketNotFound` / `TicketAlreadyEscalated` stay HTTP-agnostic. `ConfigureApiExceptions` maps them to 404 / 409, and turns Laravel route-binding 404s into the same compact JSON (no debug stack). |
| **`lockForUpdate` + transaction** | The row is locked inside the same transaction as `markEscalated()` and outbox inserts. The second caller waits, then sees `escalated` and gets `409`. |
| **Escalate, then notify** | Status and `escalated_at` commit first. Jobs use `afterCommit()`. A downed Slack API cannot roll the ticket back to `open`. |
| **One job per channel** | Email retry does not block Slack. Each `notification_logs` row is its own outbox entry. |
| **Laravel `$tries` / `backoff()`** | Three attempts total. Two waits: `10s` then `30s`. `failed()` writes the final error. A replay after `sent` or `failed` does not send again. |
| **String columns + PHP enums** | Compatible with SQLite tests; no `ALTER TABLE` to add a channel. |
| **Form Request + API Resource** | Validation and response shape stay out of the controller. |
| **No Event/Listener** | Outbox rows are written in the same transaction as the ticket. Jobs run `afterCommit()`. An event after commit would split that atomic write; an event inside the transaction would be the same design with extra files. |
| **Already escalated → 409** | Design decision, not a silent no-op. No second wave of jobs or logs. `UNIQUE(ticket_id, channel)` is a database backstop. Duplicate keys in `channels[]` are rejected (`422`) so a unique clash cannot roll back a first-time escalate. |
| **Notification is not queued twice** | `TicketEscalatedNotification` is sent inside the job. Retry, `attempts`, and final status stay on `DispatchEscalationNotificationJob`. |

## Notification architecture

```
POST /api/tickets/{id}/escalate
        │
        ▼
EscalateTicketRequest          channels[] optional, distinct, default email+slack
        │
        ▼
EscalateTicketAction           lock → markEscalated() → log + dispatch
        │
        ▼
DispatchEscalationNotificationJob   tries=3, afterCommit
        │
        ▼
EscalationChannelRegistry.resolve(key)
        │
        ├── EmailEscalationChannel  → TicketEscalatedNotification (mail)
        └── SlackEscalationChannel  → TicketEscalatedNotification (slack)
```

`send()` throws on transport failure (missing Slack token, mailer exception, timeout). The job increments `attempts` first and stores the last error on failure. Success sets `sent` + `sent_at` and clears `error_message`. After three failures Laravel calls `failed()` and the log becomes `failed`. The worker logs ticket id, channel, log id, attempts, and the error — not tokens.

Laravel Notifications stay behind the adapters. Retry tests bind `Tests\Support\FakeFlakyChannel` into the registry. Happy-path job tests can `Notification::fake()`.

## Retry strategy

| Setting | Value |
|---|---|
| Max attempts | `$tries = 3` |
| Backoff | `10`, `30` seconds (two waits for three attempts) |
| Success | `notification_logs.status = sent` |
| Exhausted | `failed()` → `status = failed` |
| Isolation | One queued job per ticket + channel |

This covers email unavailable, Slack webhook/token failure, and timeouts without custom retry infrastructure. Run `php artisan queue:work` so retries actually happen (`QUEUE_CONNECTION=database`).

## Adding a new channel

Example: WhatsApp.

1. Add `WhatsAppEscalationChannel` implementing `EscalationChannel` (`key(): 'whatsapp'`).
2. Register the class in `config/escalation.php` under `channels`.
3. Add a Pest case that binds a fake/mock for that key.

No changes to the action, job, or controller. `AppServiceProvider` builds the registry from that config map.

If the new channel reuses `TicketEscalatedNotification`, add an `EscalationChannelKey` case and a `via()` branch. A channel that sends through its own client does not touch that class.

## HTTP surface

| Method | Path | Result |
|---|---|---|
| `GET` | `/api/tickets/{id}` | Ticket + logs (for the React page) |
| `POST` | `/api/tickets/{id}/escalate` | Escalates; optional `{ "channels": ["email"] }` |

The React app reads the ticket id from `/tickets/:ticketId`. `useTicket` loads `GET /api/tickets/{id}`. `useEscalateTicket` posts with no body, so the API default (email + Slack) is used. Channel selection is on the API (`{"channels":["email"]}`), not a UI picker. After success the mutation writes the response into the query cache. Logs with `pending` status refetch every 3 seconds so a running `queue:work` shows sent/failed without a reload.
