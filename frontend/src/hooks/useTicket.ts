import { useQuery } from '@tanstack/react-query'
import { isAxiosError } from 'axios'
import {
  fetchTicket,
  hasPendingNotifications,
  ticketQueryKey,
} from '@/lib/ticket'

export function useTicket(ticketId: number) {
  return useQuery({
    queryKey: ticketQueryKey(ticketId),
    queryFn: () => fetchTicket(ticketId),
    enabled: Number.isInteger(ticketId) && ticketId > 0,
    retry: (failureCount, error) => {
      if (isAxiosError(error) && error.response?.status === 404) {
        return false
      }

      return failureCount < 2
    },
    refetchInterval: (query) => {
      const ticket = query.state.data

      return ticket && hasPendingNotifications(ticket) ? 3000 : false
    },
  })
}
