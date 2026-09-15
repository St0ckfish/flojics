import {
  createBrowserRouter,
  Navigate,
  Outlet,
  RouterProvider,
} from 'react-router'
import { TicketNav } from '@/components/TicketNav'
import { TicketPage } from '@/pages/TicketPage'

function Shell() {
  return (
    <div className="min-h-svh text-foreground">
      <header className="border-b border-border bg-card">
        <div className="mx-auto flex max-w-3xl flex-wrap items-center justify-between gap-3 px-5 py-3">
          <div className="min-w-0">
            <p className="text-[13px] font-medium tracking-tight">Flojics</p>
            <p className="text-xs text-muted-foreground">Help desk</p>
          </div>
          <TicketNav />
        </div>
      </header>
      <main className="mx-auto max-w-3xl px-5 py-10">
        <Outlet />
      </main>
    </div>
  )
}

const router = createBrowserRouter([
  {
    path: '/',
    element: <Shell />,
    children: [
      { index: true, element: <Navigate to="/tickets/1" replace /> },
      { path: 'tickets/:ticketId', element: <TicketPage /> },
    ],
  },
])

export default function App() {
  return <RouterProvider router={router} />
}
