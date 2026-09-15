import { isAxiosError } from 'axios'
import { Loader2 } from 'lucide-react'
import { useParams } from 'react-router'
import { NotificationLogs } from '@/components/NotificationLogs'
import { PriorityBadge } from '@/components/PriorityBadge'
import { StatusBadge } from '@/components/StatusBadge'
import { Button } from '@/components/ui/button'
import { useEscalateTicket } from '@/hooks/useEscalateTicket'
import { useTicket } from '@/hooks/useTicket'
import { apiErrorMessage } from '@/lib/api'
import { formatTimestamp } from '@/lib/format'

export function TicketPage() {
  const { ticketId: rawId } = useParams()
  const ticketId = Number(rawId)

  if (!Number.isInteger(ticketId) || ticketId < 1) {
    return (
      <p className="text-sm text-muted-foreground">
        Open a ticket with a numeric id, like /tickets/4.
      </p>
    )
  }

  return <TicketDetails ticketId={ticketId} />
}

function TicketDetails({ ticketId }: { ticketId: number }) {
  const ticketQuery = useTicket(ticketId)
  const escalate = useEscalateTicket(ticketId)

  if (ticketQuery.isLoading) {
    return (
      <div className="flex items-center gap-2 text-sm text-muted-foreground">
        <Loader2 className="size-4 animate-spin" />
        Opening ticket #{ticketId}
      </div>
    )
  }

  if (ticketQuery.isError || !ticketQuery.data) {
    return (
      <div className="border border-destructive/25 bg-destructive/5 px-4 py-3 text-sm text-destructive">
        {apiErrorMessage(ticketQuery.error)}
      </div>
    )
  }

  const ticket = ticketQuery.data
  const alreadyEscalated = ticket.status === 'escalated'
  const escalateError =
    escalate.error && isAxiosError(escalate.error) ? escalate.error : null

  return (
    <div>
      <article className="bg-card px-6 py-7 ring-1 ring-border sm:px-8">
        <header className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
          <div className="min-w-0">
            <p className="text-xs text-muted-foreground">Ticket #{ticket.id}</p>
            <h1 className="mt-1 font-serif text-[1.65rem] leading-snug font-medium tracking-tight">
              {ticket.subject}
            </h1>
            <div className="mt-3 flex flex-wrap items-center gap-2">
              <StatusBadge status={ticket.status} />
              <PriorityBadge priority={ticket.priority} />
            </div>
          </div>
          <Button
            className="w-full shrink-0 sm:w-auto"
            onClick={() => escalate.mutate()}
            disabled={alreadyEscalated || escalate.isPending}
          >
            {escalate.isPending ? <Loader2 className="animate-spin" /> : null}
            {alreadyEscalated ? 'Already escalated' : 'Escalate ticket'}
          </Button>
        </header>

        <p className="mt-6 text-[15px] leading-7 text-foreground/90">
          {ticket.description ?? 'No description on this ticket.'}
        </p>

        <dl className="mt-8 grid gap-4 border-t border-border pt-5 text-sm sm:grid-cols-3">
          <div>
            <dt className="text-xs text-muted-foreground">Customer</dt>
            <dd className="mt-1">#{ticket.customer_id}</dd>
          </div>
          <div>
            <dt className="text-xs text-muted-foreground">Assignee</dt>
            <dd className="mt-1">
              {ticket.agent_id ? `#${ticket.agent_id}` : 'Unassigned'}
            </dd>
          </div>
          <div>
            <dt className="text-xs text-muted-foreground">Escalated</dt>
            <dd className="mt-1">
              {ticket.escalated_at
                ? formatTimestamp(ticket.escalated_at)
                : 'Not yet'}
            </dd>
          </div>
        </dl>

        {escalateError ? (
          <p className="mt-4 text-sm text-destructive">
            {apiErrorMessage(escalateError)}
          </p>
        ) : null}
      </article>

      <NotificationLogs logs={ticket.notification_logs} />
    </div>
  )
}
