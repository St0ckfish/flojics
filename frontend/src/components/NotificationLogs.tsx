import { Mail, MessageSquare } from 'lucide-react'
import type { NotificationLog } from '@/lib/ticket'
import { formatTimestamp } from '@/lib/format'
import { cn } from '@/lib/utils'

const statusCopy = {
  pending: 'Sending',
  sent: 'Delivered',
  failed: 'Failed',
} as const

function ChannelIcon({ channel }: { channel: string }) {
  if (channel === 'email') {
    return <Mail className="size-4 text-muted-foreground" aria-hidden />
  }

  return <MessageSquare className="size-4 text-muted-foreground" aria-hidden />
}

export function NotificationLogs({ logs }: { logs: NotificationLog[] }) {
  return (
    <section className="mt-10">
      <div className="mb-4 flex items-baseline justify-between gap-4">
        <h2 className="text-sm font-medium">Notifications</h2>
        {logs.some((log) => log.status === 'pending') ? (
          <p className="text-xs text-muted-foreground">Updating…</p>
        ) : null}
      </div>

      {logs.length === 0 ? (
        <p className="text-sm text-muted-foreground">
          Nothing sent yet. Escalate to notify the team by email and Slack.
        </p>
      ) : (
        <ul className="divide-y divide-border border-y border-border">
          {logs.map((log) => (
            <li key={log.id} className="flex gap-3 py-3.5">
              <div className="mt-0.5">
                <ChannelIcon channel={log.channel} />
              </div>
              <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                  <p className="text-sm font-medium capitalize">
                    {log.channel}
                  </p>
                  <p
                    className={cn(
                      'text-sm',
                      log.status === 'failed' && 'text-destructive',
                      log.status === 'sent' && 'text-teal-700',
                      log.status === 'pending' && 'text-muted-foreground',
                    )}
                  >
                    {statusCopy[log.status]}
                  </p>
                </div>
                <p className="mt-0.5 text-xs text-muted-foreground">
                  {log.attempts} {log.attempts === 1 ? 'try' : 'tries'}
                  {log.sent_at ? ` · ${formatTimestamp(log.sent_at)}` : null}
                </p>
                {log.error_message ? (
                  <p className="mt-1 text-xs leading-relaxed text-destructive">
                    {log.error_message}
                  </p>
                ) : null}
              </div>
            </li>
          ))}
        </ul>
      )}
    </section>
  )
}
