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
| **Strategy + registry** | Email and Slack implement `EscalationChannel`. The job and action never `switch` on channel names. Adding WhatsApp is a new class + one registry line (Open/Closed). |
| **Domain exceptions** | `TicketNotFound` / `TicketAlreadyEscalated` stay HTTP-agnostic. `ConfigureApiExceptions` maps them to 404 / 409, and turns Laravel route-binding 404s into the same compact JSON (no debug stack). |
| **`lockForUpdate` + transaction** | Two Escalate clicks cannot both create a second wave of jobs. |
| **Escalate, then notify** | Status and `escalated_at` commit first. Jobs use `afterCommit()`. A downed Slack API cannot roll the ticket back to `open`. |
| **One job per channel** | Email retry does not block Slack. Each `notification_logs` row is its own outbox entry. |
| **Laravel `$tries` / `backoff()`** | No hand-rolled retry loop. `$tries = 3`, backoff `10s / 30s / 60s`, `failed()` writes the final error. |
| **String columns + PHP enums** | Compatible with SQLite tests; no `ALTER TABLE` to add a channel. |
| **Form Request + API Resource** | Validation and response shape stay out of the controller. |

## Notification architecture

```
POST /api/tickets/{id}/escalate
        │
        ▼
EscalateTicketRequest          channels[] optional, default email+slack
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

`send()` throws on transport failure (missing Slack token, mailer exception, timeout). The job increments `attempts` first. Success sets `sent` + `sent_at`. After three failures Laravel calls `failed()` and the log becomes `failed` with `error_message`.

Laravel Notifications stay behind the adapters so Pest can `Notification::fake()` or swap the registry with a Mockery channel.

## Retry strategy

| Setting | Value |
|---|---|
| Max attempts | `$tries = 3` |
| Backoff | `10`, `30`, `60` seconds |
| Success | `notification_logs.status = sent` |
| Exhausted | `failed()` → `status = failed` |
| Isolation | One queued job per ticket + channel |

This covers email unavailable, Slack webhook/token failure, and timeouts without custom retry infrastructure. Run `php artisan queue:work` so retries actually happen (`QUEUE_CONNECTION=database`).

## Adding a new channel

Example: WhatsApp.

1. Add `WhatsAppEscalationChannel` implementing `EscalationChannel` (`key(): 'whatsapp'`).
2. Register it in the `EscalationChannelRegistry` singleton in `AppServiceProvider`.
3. Add `WhatsApp = 'whatsapp'` to `EscalationChannelKey`.
4. Add a Pest case that mocks the new channel.

No changes to the action, job, or controller.

## HTTP surface

| Method | Path | Result |
|---|---|---|
| `GET` | `/api/tickets/{id}` | Ticket + logs (for the React page) |
| `POST` | `/api/tickets/{id}/escalate` | Escalates; optional `{ "channels": ["email"] }` |

The React app reads the ticket id from `/tickets/:ticketId`. `useTicket` loads `GET /api/tickets/{id}`. `useEscalateTicket` posts, then writes the response into the query cache. Logs with `pending` status refetch every 3 seconds so a running `queue:work` shows sent/failed without a reload.
