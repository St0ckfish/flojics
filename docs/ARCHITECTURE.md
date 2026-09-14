# Architecture

Planned design for the escalation slice. Application classes are not implemented yet; this document is the contract the code will follow.

## Folder structure

```
flojics/
├── backend/                          # Laravel 11 API
│   ├── app/
│   │   ├── Actions/                  # One use-case per class
│   │   ├── Http/Controllers/Api/     # Thin HTTP adapters
│   │   ├── Http/Requests/            # Form Request validation
│   │   ├── Jobs/                     # Queued send + retry
│   │   ├── Models/
│   │   ├── NotificationChannels/
│   │   │   └── Contracts/            # EscalationChannel interface
│   │   └── Notifications/            # Laravel Notification (email/slack payload)
│   └── tests/Feature/                # Pest
├── frontend/                         # React + Vite + TypeScript
│   └── src/
│       ├── api/                      # HTTP clients
│       ├── components/ui/            # shadcn primitives
│       ├── hooks/                    # TanStack Query mutations
│       ├── lib/                      # shared utils
│       └── pages/                    # Ticket page
├── docs/
└── .github/workflows/ci.yml
```

PHP and JavaScript do not share a bundler, so this is a polyglot repo rather than Turborepo. Quality gates still run together in CI.

## Design decisions

| Decision | Why |
|---|---|
| **Action class** (`EscalateTicketAction`) | Keeps the controller thin. The HTTP layer validates and calls one method. The same action can be reused from a command or an SLA scheduler later. |
| **Strategy + registry for channels** | Email and Slack are different adapters behind `EscalationChannel`. New channels do not change the action, job, or controller (Open/Closed). |
| **Queue jobs for send + retry** | Do not invent retry loops. Laravel already provides `$tries`, `backoff()`, and `failed()`. |
| **`notification_logs` as the outbox** | Every dispatch has a row before the job runs. Final status, attempts, and error message are queryable. |
| **Escalate the ticket first** | Status change is the business event. Notifications are a side effect. A downed Slack webhook must not leave the ticket stuck on `open`. |
| **Form Request** | Channel list validation stays out of the controller. |
| **Pest + Pint + Larastan** | Tests, style, and static analysis are first-class, same as the frontend oxlint / TypeScript / build pipeline. |

## Notification architecture

```
POST /api/tickets/{id}/escalate
        │
        ▼
EscalateTicketRequest   (validate id + channels)
        │
        ▼
EscalateTicketAction
        │  1. lock ticket, reject 404 / 409
        │  2. status = escalated, escalated_at = now()
        │  3. for each channel:
        │        create notification_logs (pending, attempts=0)
        │        dispatch DispatchEscalationNotificationJob
        ▼
DispatchEscalationNotificationJob   (ShouldQueue, tries=3)
        │
        ▼
EscalationChannelRegistry.resolve(channel)
        │
        ├── EmailEscalationChannel
        └── SlackEscalationChannel
```

Each channel `send()` throws on failure. The job increments `attempts`, marks `sent` on success, and `failed()` writes the final error after retries are exhausted.

Laravel Notifications (mail / Slack Block Kit) live behind the channel adapters so the registry stays easy to test and mock.

## Retry strategy

| Setting | Value |
|---|---|
| Max attempts | `public int $tries = 3` |
| Backoff | `10s, 30s, 60s` |
| Success | `notification_logs.status = sent`, `sent_at = now()` |
| Exhausted | `failed()` sets `status = failed` and stores `error_message` |
| Isolation | One job per ticket + channel. Email retry does not block Slack. |

This covers the required failure examples (mailer down, Slack webhook/API error, timeout) without custom retry infrastructure.

## Adding a new channel

Example: WhatsApp.

1. Add `app/NotificationChannels/WhatsAppEscalationChannel.php` implementing `EscalationChannel` (`key(): 'whatsapp'`).
2. Register it in the `EscalationChannelRegistry` binding in `AppServiceProvider`.
3. Allow `'whatsapp'` in `EscalateTicketRequest`.
4. Add a Pest case that mocks the new channel.

No changes to the action, job, controller, or frontend mutation beyond sending the new key if the UI exposes it.

## Frontend structure (planned)

- `src/pages/TicketPage.tsx` — Ticket ID, subject, priority, status, escalation date, Escalate button.
- `src/hooks/useEscalateTicket.ts` — `useMutation` → `POST /api/tickets/{id}/escalate`, then invalidate the ticket query.
- shadcn `Badge` for Open / Escalated.

The current frontend is a clean Vite starter plus this folder layout and tooling. Feature UI is intentionally not written yet.
