import { cn } from '@/lib/utils'
import type { TicketPriority } from '@/lib/ticket'

const labels: Record<TicketPriority, string> = {
  low: 'Low',
  medium: 'Medium',
  high: 'High',
  urgent: 'Urgent',
}

const tones: Record<TicketPriority, string> = {
  low: 'bg-stone-100 text-stone-600',
  medium: 'bg-teal-100 text-teal-800',
  high: 'bg-orange-100 text-orange-800',
  urgent: 'bg-red-100 text-red-800',
}

export function PriorityBadge({ priority }: { priority: TicketPriority }) {
  return (
    <span
      className={cn(
        'inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium',
        tones[priority],
      )}
    >
      {labels[priority]}
    </span>
  )
}
