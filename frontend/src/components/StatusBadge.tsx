import { cn } from '@/lib/utils'
import type { TicketStatus } from '@/lib/ticket'

const labels: Record<TicketStatus, string> = {
  open: 'Open',
  in_progress: 'In progress',
  escalated: 'Escalated',
  closed: 'Closed',
}

const tones: Record<TicketStatus, string> = {
  open: 'bg-sky-100 text-sky-800',
  in_progress: 'bg-amber-100 text-amber-900',
  escalated: 'bg-orange-100 text-orange-900',
  closed: 'bg-stone-100 text-stone-600',
}

export function StatusBadge({ status }: { status: TicketStatus }) {
  return (
    <span
      className={cn(
        'inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium',
        tones[status],
      )}
    >
      {labels[status]}
    </span>
  )
}
