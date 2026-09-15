import { z } from 'zod'
import { api } from '@/lib/api'

export const ticketStatusSchema = z.enum([
  'open',
  'in_progress',
  'escalated',
  'closed',
])

export const ticketPrioritySchema = z.enum(['low', 'medium', 'high', 'urgent'])

export const notificationLogSchema = z.object({
  id: z.number(),
  channel: z.string(),
  status: z.enum(['pending', 'sent', 'failed']),
  attempts: z.number(),
  error_message: z.string().nullable(),
  sent_at: z.string().nullable(),
})

export const ticketSchema = z.object({
  id: z.number(),
  subject: z.string(),
  description: z.string().nullable(),
  priority: ticketPrioritySchema,
  status: ticketStatusSchema,
  escalated_at: z.string().nullable(),
  customer_id: z.number(),
  agent_id: z.number().nullable(),
  notification_logs: z.array(notificationLogSchema).default([]),
})

const ticketResponseSchema = z.object({
  data: ticketSchema,
})

export type Ticket = z.infer<typeof ticketSchema>
export type TicketStatus = z.infer<typeof ticketStatusSchema>
export type TicketPriority = z.infer<typeof ticketPrioritySchema>
export type NotificationLog = z.infer<typeof notificationLogSchema>

export const ticketQueryKey = (ticketId: number) =>
  ['tickets', ticketId] as const

export async function fetchTicket(ticketId: number): Promise<Ticket> {
  const { data } = await api.get(`/tickets/${ticketId}`)

  return ticketResponseSchema.parse(data).data
}

export async function escalateTicket(ticketId: number): Promise<Ticket> {
  const { data } = await api.post(`/tickets/${ticketId}/escalate`)

  return ticketResponseSchema.parse(data).data
}

export function hasPendingNotifications(ticket: Ticket): boolean {
  return ticket.notification_logs.some((log) => log.status === 'pending')
}
