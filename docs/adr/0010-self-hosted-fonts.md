# ADR-0010: Self-hosted fonts from npm; no third-party requests

**Status:** Accepted (11 October 2026).

**Context.** The kit's `bunny('Instrument Sans')` downloads fonts from `fonts.bunny.net` **at build time** (`laravel-vite-plugin` fonts). That host was refused by the network policy in the workspace where this was checked, which would break `npm run build`. The brand typeface is IBM Plex Sans (spec §15.7).

**Decision.** In T0.1, replace it with `fontsource('IBM Plex Sans', { weights: [400, 500, 600] })` from `laravel-vite-plugin/fonts`, which reads the `@fontsource/ibm-plex-sans` npm package (OFL-1.1). Fonts are emitted with the build and served by nginx.

**Re-checked 13 October 2026.** Building on the laptop no longer depends on that host, but the decision stands: no build-time download, no request to another site, and the brand typeface.

**Consequences.** The build needs only the npm registry; pages make no request to any other site (privacy, speed, CSP `font-src 'self'`).
