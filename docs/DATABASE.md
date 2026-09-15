# Database Design

MySQL 8 schema for the escalation slice. The PDF treats users, customers, agents, and tickets as already present. That is a product scenario, not a dump we received, so this repo creates those tables with a small, reviewable shape and then adds escalation.

No `.sql` dump is committed. Reviewers get the same database with:

```bash
php artisan migrate --seed
```

## Entity relationship

```
users 1 ──────── < agents 1 ──────── < tickets
                                      ^
customers 1 ──────────────────────────┘
                                      │
                                      │ 1
                                      ▼
                              notification_logs
```

- A **customer** can have many tickets.
- An **agent** is a `users` row with a department. A ticket may be unassigned (`agent_id` nullable).
- A **ticket** can have many **notification_logs** (one row per channel per escalation; retries increment `attempts` on that row).

## Tables created

Laravel already ships `users`, `sessions`, `jobs`, and `cache`. This slice adds four tables.

### `customers`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `name` | string | |
| `email` | string unique | Who we notify about, later |
| `phone` | string nullable | Ready for SMS / WhatsApp |
| `timestamps` | | |

### `agents`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `user_id` | FK → `users.id` | `cascadeOnDelete()` |
| `department` | string nullable | Support, Billing, … |
| `timestamps` | | |

### `tickets`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | Shown on the ticket page |
| `customer_id` | FK → `customers.id` | `cascadeOnDelete()` |
| `agent_id` | FK → `agents.id` nullable | `nullOnDelete()` |
| `subject` | string | |
| `description` | text nullable | |
| `priority` | string | `low`, `medium`, `high`, `urgent` (default `medium`) |
| `status` | string | `open`, `in_progress`, `escalated`, `closed` (default `open`) |
| `escalated_at` | timestamp nullable | Set when status first becomes escalated |
| `timestamps` | | |

Indexes: `tickets(status)`, `tickets(priority)`.

`status` and `priority` are strings, not MySQL `ENUM`. PHP enums (`TicketStatus`, `TicketPriority`) enforce allowed values. That stays compatible with SQLite in tests and avoids an `ALTER TABLE` when a new status is added.

### `notification_logs`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `ticket_id` | FK → `tickets.id` | `cascadeOnDelete()` |
| `channel` | string | `email`, `slack`, later `whatsapp`… |
| `status` | string | `pending`, `sent`, `failed` |
| `attempts` | unsigned tinyint | Default `0`. Incremented by the queue job |
| `error_message` | text nullable | Last exception |
| `sent_at` | timestamp nullable | Set when status becomes `sent` |
| `timestamps` | | |

Indexes: `UNIQUE(ticket_id, channel)`, `status`.

`channel` is a free string so a new channel does not need a schema change.

`UNIQUE(ticket_id, channel)` matches the business rule: one escalation per ticket, one outbox row per channel. Re-escalation is rejected (`409`), so a batch id is not needed. Retries update `attempts` on the same row.

## Why this shape

- **`escalated_at` on the ticket** is the business timestamp the UI shows.
- **`notification_logs`** is the operational outbox: which channel, how many tries, what failed.
- Escalating the ticket and sending notifications are separate writes. A downed Slack webhook must not roll back `status = escalated`.

## Seed data

`DatabaseSeeder` creates one test user, four customers, three agents, and **ten tickets** across open / in progress / unassigned / escalated / closed so the reviewer can open a ticket and press Escalate without inserting rows by hand.
