import { useMutation, useQueryClient } from '@tanstack/react-query'
import { escalateTicket, ticketQueryKey } from '@/lib/ticket'

export function useEscalateTicket(ticketId: number) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: () => escalateTicket(ticketId),
    onSuccess: (ticket) => {
      queryClient.setQueryData(ticketQueryKey(ticket.id), ticket)
    },
  })
}
