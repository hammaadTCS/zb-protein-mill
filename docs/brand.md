# Brand – ZB Poultry Protein Mill

The logo is used minimally because it is a raster image with a white background and a white bird (spec §15.7). The interface carries the brand through colour and the "ZB" monogram, not the logo.

## Name

- Full name: **ZB Poultry Protein Mill**. Sidebar header: the monogram tile plus the full name as text.
- `APP_NAME="ZB Poultry Protein Mill"`. Browser tab titles: `{Page} – ZB Poultry Protein Mill`.
- Page headings never repeat the company name.

## Logo: two places only

| Place | File | Size |
| --- | --- | --- |
| Login page, above the form | `brand/zb-logo-256.png` | Display at most 120 px wide |
| PDF header (statement and slip) | `brand/zb-logo-96.png` | Display at most 48 px tall |

- Always on a white background. In dark mode, place it on a white rounded tile with 12 px padding.
- Never stretch, recolour, crop or place it on green or gold.
- Never use it in the sidebar, dashboard, emails or as a favicon.
- `brand/zb-logo-original.png` is the file as received. `brand/zb-logo-trimmed.png` has the extra white border removed.

## Monogram and icons

| File | Use |
| --- | --- |
| `brand/zb-monogram.svg` | Sidebar tile, 32 px |
| `brand/favicon-32.png` | Browser favicon |
| `brand/apple-touch-icon-180.png` | Phone home-screen icon |
| `brand/zb-monogram-512.png` | Large icon if needed later |

## Colours (sampled from the logo)

| Token | Hex | Use | Contrast on white |
| --- | --- | --- | --- |
| `brand-deep` | #04291A | Sidebar background, headings | 15.7 : 1 |
| `brand-green` | #0A5223 | Primary buttons, links, focus ring | 9.4 : 1 |
| `brand-gold` | #D89707 | Active menu marker, small highlights | 2.5 : 1, so **never** text on white |
| `brand-red` | #CB1222 | Not used in the interface | — |

- Gold works as a background with `brand-deep` text on it (6.2 : 1).
- Red is kept out of the interface so it is never confused with errors, "Paid" or "Overdue".
- Status badge colours (spec §15.3) are separate from brand colours.

## Typeface

- **IBM Plex Sans**, self-hosted since task T0.1: the `@fontsource/ibm-plex-sans` npm package, loaded by `fontsource('IBM Plex Sans', { weights: [400, 500, 600] })` from `laravel-vite-plugin/fonts` in `vite.config.ts` (ADR-0010). Do not import the font files by hand and never load fonts from another site.
- Every number uses tabular figures (Tailwind class `tabular-nums`), right-aligned in tables.
- Sentence case for buttons and headings. No all-caps labels.

## Tailwind 4 tokens (add to `resources/css/app.css` in task T2.1)

```css
@theme {
  --color-brand-deep: #04291A;
  --color-brand-green: #0A5223;
  --color-brand-gold: #D89707;
  --font-sans: "IBM Plex Sans", ui-sans-serif, system-ui, sans-serif;
}
```

Point the starter kit's shadcn `--primary` variable at `brand-green` and `--ring` at `brand-green`, in both light and dark themes. Check text contrast in dark mode after the change.

## Later (out of scope for version 1)

Ask the client to get the logo redrawn as a vector (SVG) by a designer. Then it can be used at any size and on dark backgrounds.
