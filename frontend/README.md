# Frontend

React + Vite + TypeScript app for the Flojics ticket page.

Open [http://localhost:5173/tickets/1](http://localhost:5173/tickets/1) after `bun run dev`. The ticket id comes from the URL. Escalate posts with no body (API default: email + Slack) and writes the response into the GET cache.

## Scripts

```bash
bun install
bun run dev
bun run lint
bun run typecheck
bun run build
```

Application code lives under `src/`. Shared setup instructions are in the repository root `README.md`.
