# Front end: how it is built

A summary for anyone working on screens. It collects, in one place, the front-end rules that live in `docs/spec.md` (§5, §11, §15, §16), `docs/conventions.md` (§3, §10), `docs/brand.md`, `docs/glossary.md` and the ADRs. **If this page and those documents ever disagree, they win** and the conflict must be reported.

## 1. What the front end is

- **One Laravel app with React pages**, not a separate single-page app with its own API. Laravel controllers render React pages through **Inertia 3** (ADR-0001). There is no public API in version 1.
- Built from the **official Laravel React starter kit**: React 19, TypeScript (strict), Tailwind 4, shadcn/ui components, **Wayfinder** for typed routes, **vite-plus** for build, lint, format and unit tests. There is no ESLint and no separate Vitest (`docs/stack-baseline.md`).
- **No server-side rendering**, and no Node.js process on the server. Node.js is only used to build the files.
- Users: the Owner and the Accountant, mainly on **Android phones (Chrome)** for daily entries and on **Windows desktops (Chrome or Edge)** for reports (NFR-03).

## 2. Folder layout

| What | Where | Example |
| --- | --- | --- |
| Pages, one folder per module | `resources/js/pages/<module>/` | `purchases/index.tsx`, `create.tsx`, `show.tsx`, `edit.tsx` |
| Shared app components | `resources/js/components/app/` | `PartyPicker.tsx` |
| shadcn/ui building blocks | `resources/js/components/ui/` (from the kit) | `button.tsx`, `dialog.tsx` |
| Helpers | `resources/js/lib/` | `format.ts` (numbers, dates), `money.ts` (preview maths) |
| Generated routes (do not edit, not committed) | `resources/js/actions/`, `resources/js/routes/` | rebuilt by `npm run build` |

The 48 pages, their routes and permissions are in spec §11.1. URL paths are exactly those.

## 3. Screens and layout (spec §15.1)

- **Desktop:** a left sidebar grouped as Dashboard · Daily entries · People · Money · Checks · Month-end · Reports · Audit log · Settings. The top bar holds party search and a "New entry" menu.
- **Phone:** a bottom bar with New purchase, New sale, New payment, Quick expense; the sidebar opens from a menu button.
- **Mobile-first pages** are designed at **375 px wide first**: login, dashboard, the four "new entry" forms, approvals and the daily cash check (spec §11.1).
- Menu items and buttons are hidden when the user lacks the permission. **The server check is the real protection; hiding is only convenience.**
- Settings screens use the same components. There is no separate admin panel.
- Dark mode comes from the kit.

## 4. Money, kg and rate on screen (the most important rules)

- **The browser never decides a money value.** Money, kg and rate arrive from the server as **strings** and are shown as received (conventions §3).
- The live total on a form (kg × rate) is a **preview** made with `lib/money.ts` (string-based whole-paisa maths). **The server recalculates and saves its own value** (BR-GEN-06).
- **Forbidden:** `parseFloat`, `Number()`, `+` or `toFixed` on money, kg or rate for anything that is saved or shown as a final figure.
- Numbers are **right-aligned with tabular figures** (`tabular-nums`) and formatted only through `lib/format.ts`. Lakh grouping (1,00,000) when the setting `number_format` is `lakh`.
- Money in and out use **words and colour together** ("Received" green, "Paid" red); colour is never the only signal.
- Party balances are shown **in words, never with a minus sign** ("We owe him", "Advance", "He owes us"): glossary §2 and spec §8.2.

## 5. Forms (spec §15.4, conventions §2a, §5, §10)

- Built with Inertia **`useForm`**. Laravel validates; its error messages appear **under the field**, in plain words. No second validation library.
- Every **create** form makes a one-time `idempotency_key` (`crypto.randomUUID()`) when it opens. A double tap or a retry on a slow phone can never save twice: the server answers "Already saved. {voucher}" (BR-GEN-11).
- **Warnings** (unusual rate, duplicate, credit limit, negative cash) come back as a 422 with a warning code. The `WarningDialog` offers **Go back** or **Save anyway**; "Save anyway" re-sends with the code confirmed, and the server audits it.
- **Party picker:** shadcn Command, searchable, showing "Name – Area – Mobile" (BR-PTY-09).
- **Kg, rate and amount** fields open the number keypad on phones (`inputmode="decimal"`). Touch targets are large on mobile-first pages.
- **Enter** moves to the next field; **Ctrl+S** saves on desktop.
- **Cancelling an entry** always asks for a reason in a confirmation dialog (`ConfirmWithReason`).
- After every save a **toast** shows the voucher number; lists show loading placeholders.
- **Photos** (weight slips, receipts) are **resized in the browser** to a longest side of 2000 px before upload. That keeps uploads small on site and drops location data; the server also re-encodes every image (BR-SEC-06).
- **Shared components** (spec §15.6): `PartyPicker`, `MoneyInput`, `KgInput`, `RateInput` (with the last-rate hint), `StatusBadge`, `BalanceInWords`, `KhataTable`, `DateRangeFilter`, `AttachmentUploader`, `ConfirmWithReason`, `WarningDialog`.

## 6. Lists and reports

- Lists: current month by default; filters for date range, party and status; search by voucher number and party; a totals row for kg and amount (active entries only); 50 rows per page, newest first (spec §11.2).
- Tables use **TanStack Table**; charts use shadcn charts (Recharts); toasts use Sonner.
- Status badges have fixed meanings and colours (spec §15.3): Rate pending, Awaiting approval, Advance, Overdue, Needs review, Rejected, Cancelled.
- Reports print with the browser's print function and a print-friendly layout. Only the party statement and the entry slip are server-made PDFs (spec §13).

## 7. Words, brand and language

- **Every word on screen comes from `docs/glossary.md`.** Do not invent labels. All interface text sits in translation files, so Urdu can be added later; every text field already accepts Urdu (NFR-04).
- Brand (`docs/brand.md`, spec §15.7):
  - **Logo** only on the login page and in PDFs;
  - **Sidebar:** a small "ZB" monogram tile and the company name as text;
  - **Colours:** `brand-deep` #04291A, `brand-green` #0A5223, `brand-gold` #D89707 (highlights only, never text on white). `brand-red` is not used in the interface;
  - **Typeface:** IBM Plex Sans, self-hosted.
- Times are shown in **Asia/Karachi**; the date format follows the `date_format` setting.

## 8. Speed (NFR-01, NFR-02, NFR-11)

| Target | How |
| --- | --- |
| Mobile-first pages open in under 2 s on 4G | Each page loads only its own code (per-page code splitting from Vite and Inertia), React Compiler from the kit, small dependencies |
| Repeat visits are fast | Built files have content hashes and are cached for one year by nginx; text is compressed |
| Reports under 3 s at 100,000 entries a year | Paging and totals are done in the database (back end), never by loading everything into the browser |
| Measured, not assumed | Task T8.2 measures page weight and timings and records them |

## 9. Safety in the browser (BR-SEC-14, ADR-0008, ADR-0010)

- A **Content Security Policy** with a fresh nonce per request: no inline scripts without the nonce, no `eval`, no `dangerouslySetInnerHTML`, no inline event handlers.
- **Nothing is loaded from another site**: no CDN scripts, no Google Fonts, no external images or analytics. Fonts come from the `@fontsource/ibm-plex-sans` npm package.
- Shared Inertia props contain only: the user (id, name, roles, permissions), display formats, flash messages and the environment name. **Never secrets, `.env` values or other users' data** (conventions §10).
- Routes come from **Wayfinder**, so a renamed route fails the TypeScript build instead of breaking in a user's hands. Never hard-code URLs.

## 10. Checks and tests

| Command | What it checks |
| --- | --- |
| `npm run build` | The app builds; also generates the Wayfinder route files |
| `npm run check` (fix: `npm run check:fix`) | Lint and format (vite-plus: Oxlint, Oxfmt) |
| `npm run types:check` | TypeScript strict mode |
| `npm test` (from T2.1) | Front-end unit tests (`vp test`), for example `money.ts` and `format.ts` |
| `bash scripts/check-all.sh` | All of the above plus the back-end checks, in CI order |

After each merge you pull on your laptop and check the screens on a real phone and on a desktop (`docs/prompts.md` §5, START-HERE Step 8).
