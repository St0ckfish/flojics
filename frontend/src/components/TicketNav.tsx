import { ChevronLeft, ChevronRight } from 'lucide-react'
import { useLocation, useNavigate } from 'react-router'
import { Button } from '@/components/ui/button'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'

const seededTicketIds = Array.from({ length: 10 }, (_, index) => index + 1)

function ticketIdFromPath(pathname: string): number | null {
  const match = pathname.match(/^\/tickets\/(\d+)$/)

  return match ? Number(match[1]) : null
}

export function TicketNav() {
  const navigate = useNavigate()
  const { pathname } = useLocation()
  const currentId = ticketIdFromPath(pathname)
  const index = currentId ? seededTicketIds.indexOf(currentId) : -1
  const options =
    currentId && !seededTicketIds.includes(currentId)
      ? [currentId, ...seededTicketIds]
      : seededTicketIds

  return (
    <div className="flex items-center gap-2">
      <Button
        variant="outline"
        size="icon-sm"
        disabled={index <= 0}
        aria-label="Previous ticket"
        onClick={() => navigate(`/tickets/${seededTicketIds[index - 1]}`)}
      >
        <ChevronLeft />
      </Button>
      <Select
        value={currentId ? String(currentId) : null}
        items={Object.fromEntries(
          options.map((id) => [String(id), `Ticket #${id}`]),
        )}
        onValueChange={(value) => {
          if (value) {
            navigate(`/tickets/${value}`)
          }
        }}
      >
        <SelectTrigger
          size="sm"
          aria-label="Open ticket"
          className="min-w-36 bg-card"
        >
          <SelectValue placeholder="Choose ticket" />
        </SelectTrigger>
        <SelectContent
          align="end"
          alignItemWithTrigger={false}
          className="min-w-44"
        >
          {options.map((id) => (
            <SelectItem key={id} value={String(id)}>
              Ticket #{id}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
      <Button
        variant="outline"
        size="icon-sm"
        disabled={index === -1 || index >= seededTicketIds.length - 1}
        aria-label="Next ticket"
        onClick={() => navigate(`/tickets/${seededTicketIds[index + 1]}`)}
      >
        <ChevronRight />
      </Button>
    </div>
  )
}
