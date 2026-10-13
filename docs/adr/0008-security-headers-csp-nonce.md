# ADR-0008: Security headers in nginx; CSP with a per-request nonce in the app

**Status:** Accepted (BR-SEC-14).

**Context.** The starter kit's root view has one inline script (dark mode) and one inline style. Inertia 3 puts page data in a `<script type="application/json">`, which a Content Security Policy does not block. React and Radix set inline `style` attributes.

**Decision.**
- nginx adds static headers to every response (`infra/files/nginx/snippets/zb-mill-headers.conf`): HSTS 1 year, `nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: same-origin`, `Permissions-Policy` (camera, microphone, location off), `X-Robots-Tag: noindex`, `Cross-Origin-Opener-Policy`.
- Laravel middleware (T1.12) sets the CSP with a fresh nonce: `default-src 'self'; script-src 'self' 'nonce-…'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'`. `Vite::useCspNonce()` adds the nonce to Vite's tags; the inline dark-mode script gets `nonce="{{ Vite::cspNonce() }}"`.

**Consequences.** Injected scripts do not run. `style-src` keeps `'unsafe-inline'` because inline style attributes cannot carry a nonce; styles cannot run code, so this is an accepted, common trade-off. Any new third-party script or font would need an ADR (none planned, ADR-0010).

**Rejected alternative.** CSP in nginx: cannot produce a per-request nonce for the inline script.
