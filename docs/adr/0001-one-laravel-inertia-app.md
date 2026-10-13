# ADR-0001: One Laravel + Inertia + React application

**Status:** Accepted (D-38, 5 October 2026; kit facts re-checked 11 October 2026).

**Context.** Two users, one company, forms and reports. The developer knows PHP. The client wants a modern, good-looking interface that works on phones.

**Decision.** One Laravel 13 application built from the official React starter kit (Inertia 3, React 19, TypeScript, Tailwind 4, shadcn/ui). Controllers return Inertia pages; there is no separate JSON API and no separate front-end deployment. Business logic lives in Action classes so an API can call it later (NFR-09).

**Consequences.**
- One codebase, one deploy, one set of tests; session cookies and CSRF protection work without tokens.
- The server needs no Node.js: the front end is built in CI.
- A future mobile app needs new API routes and token authentication, but no new business logic.

**Rejected alternatives.**
- Separate React SPA + Laravel API: two deploys, token handling, CORS, more attack surface, no benefit for two users.
- Livewire/Blade: fine technically, but the client asked for a React-style interface and the kit's React components.
