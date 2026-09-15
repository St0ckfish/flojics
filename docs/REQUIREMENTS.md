# Requirement Analysis

Written before implementation. Answers were not waited for; the assumptions below are the working contract.

## Questions

These are the questions I would normally ask the Product Owner.

1. **Who can escalate a ticket?** Any authenticated agent, only the assignee, or a supervisor role?
2. **Which channels are selected, and where?** Per request body, per ticket, per customer, or a global default?
3. **Who receives the notifications?** Assigned agent, team lead, a configured mailbox, a Slack channel, or the customer?
4. **What happens if the ticket is already escalated?** Reject, no-op, or allow re-escalation with a new timestamp?
5. **Should a notification failure roll back the ticket status?** Or should the ticket stay escalated and only the log record fail?
6. **Is authentication required for this slice?** Sanctum / session / internal service token?
7. **Can a customer escalate, or is this staff-only?**
8. **Should priority change on escalate?** For example Open + High becomes Escalated + Critical?
9. **Is there an SLA / auto-escalate path**, or is this button-only for now?
10. **What is the notification content and locale?** Fixed English template, or customer language?
11. **Should partial channel failure be visible in the UI**, or only in logs?
12. **Do we need idempotency** if the user double-clicks Escalate?

## Assumptions

| Topic | Assumption |
|---|---|
| Existing domain | The PDF describes an existing Help Desk with users, customers, agents, and tickets. That is a product scenario, not a database we were given. This repo therefore ships **minimal tables** for those entities plus escalation fields, so `migrate --seed` is enough to review the feature. |
| Auth | The API is treated as an internal staff endpoint. Auth / policies can be added without changing the action or channels. |
| Channel selection | Request body accepts `channels: ("email" \| "slack")[]`. If omitted, both Email and Slack are used. The Escalate button does not send `channels`; it relies on that default. |
| Recipients | Email goes to `ESCALATION_MAIL_TO` (falls back to `MAIL_FROM_ADDRESS`). Slack goes to `SLACK_BOT_USER_DEFAULT_CHANNEL`. |
| Ticket update | On escalate: `status = escalated`, `escalated_at = now()`. |
| Already escalated | Return `409 Conflict` (design decision). No second notification storm. Concurrent callers are serialized with `lockForUpdate`; the database also enforces `UNIQUE(ticket_id, channel)`. |
| Missing ticket | Return `404`. |
| Failure isolation | The ticket is persisted as escalated first. Each channel is an independent queued job. One channel failing does not undo the ticket or the other channel. |
| Retry | Laravel queue job: `$tries = 3`, backoff `[10, 30]` seconds. Final status and attempt count live on `notification_logs`. |
| Queue | `QUEUE_CONNECTION=database` locally so retries are inspectable. Tests use `sync` / fakes. |
| Frontend | Single ticket page. Ticket id comes from the URL. |
| Database | MySQL 8 in development (Docker) and in CI. Local Pest defaults to in-memory SQLite so a reviewer can run tests without MySQL. The lock concurrency test skips unless the driver is MySQL. |

## Recommendations

1. **Channel config table** later (`notification_channel_settings`) so Slack workspace / email recipients are not hardcoded in env only.
2. **Sanctum + policies** so only the assigned agent or a supervisor can escalate.
3. **SLA scheduler** (`tickets.due_at`) to auto-escalate without a button click.
4. **Idempotency key** on the request to make double-submit safe.
5. **Dead-letter visibility** in an admin UI after retries are exhausted.
6. **Observability**: structured logs + a metric for `notification_logs.status=failed`.
7. **Outbox is already implied** by `notification_logs` + queued jobs; keep that as the source of truth, not the mailer.
8. **WhatsApp / SMS / Teams / Push** should be new classes behind `EscalationChannel`, not new branches in the controller.
