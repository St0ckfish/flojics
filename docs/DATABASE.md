# Database Design

Planned MySQL schema for the escalation slice. Migrations are not written yet.

## Existing context

The platform already has users, customers, agents, and tickets. This slice **modifies** `tickets` and **adds** `notification_logs`.

## Tables

### `tickets` (modified)

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | Existing |
| `subject` | string | Existing |
| `priority` | string / enum | Existing (`low`, `medium`, `high`, `urgent`) |
| `status` | enum | **Add / tighten:** `open`, `in_progress`, `escalated`, `closed` |
| `escalated_at` | timestamp nullable | **Add.** Set once when the ticket is first escalated |
| `timestamps` | | Existing |

Constraints:

- `status` default `open`
- index on `tickets(status)` for staff filters and future SLA jobs

### `notification_logs` (new)

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `ticket_id` | FK → `tickets.id` | `cascadeOnDelete()` |
| `channel` | string(32) | `email`, `slack`, later `whatsapp`, `sms`, ... |
| `status` | enum | `pending`, `sent`, `failed` |
| `attempts` | unsigned int | default `0` |
| `error_message` | text nullable | Last exception message |
| `sent_at` | timestamp nullable | Set when status becomes `sent` |
| `timestamps` | | |

Constraints and indexes:

- `notification_logs(ticket_id)`
- `notification_logs(status)`
- composite `notification_logs(ticket_id, channel)` for “latest email attempt”

## Relationships

```
tickets 1 ───────< notification_logs
```

A ticket can have many log rows (one per channel, and historically more if re-escalation is allowed later).

## Why this shape

- **`escalated_at` on the ticket** is the business timestamp the UI shows.
- **`notification_logs`** is the operational record: which channel, how many tries, what failed.
- Keeping channel as a string (not a MySQL enum of two values) avoids a migration every time WhatsApp or Teams is added.
