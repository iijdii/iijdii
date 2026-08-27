# Handoff: LEA CRM — Vertrieb, Lager, Montage & Abnahme

## Overview

LEA CRM is an internal system for a company that sells and installs terrace roofs
(Terrassenüberdachungen). It covers the whole chain: inbound enquiry → quote →
project → supplier orders → warehouse → installation on site → signed acceptance
protocol.

The prototype in this bundle is a complete, clickable design of 11 modules with
real domain data (article numbers, profile names, glass types, German legal text).
The task is to reimplement it as a real application with persistence, users and
permissions.

## About the Design Files

The files in this bundle are **design references created in HTML** — prototypes
showing intended look and behaviour, not production code to copy. All state lives
in browser memory; nothing is saved.

The target is the company's existing PHP theme (`lea_crm_v.3.3`, referenced in the
project but not part of this bundle). Recreate these screens as templates in that
codebase using its established patterns, or — if a new stack is preferred — pick
one and implement the same screens and data model there. Do not ship the HTML file
as the product.

## Fidelity

**High-fidelity.** Colors, typography, spacing, borders, shadows and interaction
states are final and should be matched closely. Exact values are in *Design
Tokens* below and in the prototype's `<style>` block.

The one exception: the five technical drawings are SVG generated from real
dimensions (see *Technical drawings*). They are functional, not decorative — the
geometry logic must be ported, not the markup.

---

## Data model

The prototype fakes a database. These are the entities it implies. Names are
suggestions; the field lists are what the UI actually needs.

### `article` (Material-Katalog — single source of truth)
The catalogue and the warehouse are the **same** article list in the prototype.
This must stay one table; two lists will drift.

| field | type | note |
|---|---|---|
| `art_nr` | string, unique | e.g. `PF-110110`, `35722`, `GL-VSG8-K` |
| `name` | string | `Alu-Pfosten 110×110` |
| `kategorie` | enum | `profile, glas, zubehoer, dicht, verbind, elektro, verbrauch` |
| `bild` | string, nullable | filename of the component drawing |
| `einheit` | enum | `Stück, Satz, Feld, lfm, Rolle, Kart., Flasche` |
| `lagerort` | string | `H1-B-01`, `G-Bock 01`, `V-01` |
| `bestand` | int | current stock |
| `min_bestand` | int | reorder threshold |
| `ek_preis` | decimal | purchase price |
| `lieferant_id` | fk | Sunshine / Solarlux / Würth |
| `aliase` | string[] | **important**, see below |

`aliase` maps free-text names used in orders and configurator output onto the
article (`Pfosten 90×90` → `PF-110110`, `Gigarinne 8630` → `35720`). Matching is
case-insensitive with `×`/`x` normalised and whitespace collapsed. Without this,
delivered order lines cannot be booked into stock automatically.

41 articles are seeded in the prototype (`_lagerMaster()` in the logic class) —
use that list as the initial catalogue import.

### `bestellung` (supplier order)
`nr` (`BST-2026-112`), `lieferant_id`, `titel`, `projekt_id`, `kunde_id`,
`liefertermin`, `status`, plus three line collections: glass fields, sliding
elements, material lines. Status enum, in order:
`entwurf → geprueft → bestellt → bereit → geliefert → montiert`, plus `storniert`.

### `wareneingang` (goods receipt)
`bestellung_id`, `datum`, `benutzer`, `lieferschein_nr` (`LS-88214`), and one row
per article with quantity. Created when an order reaches `geliefert`.

### `lagerbewegung` (stock movement)
`datum`, `typ` (`Eingang, Ausgang, Reservierung, Korrektur`), `art_nr`, `menge`
(signed), `referenz` (order nr, project nr, `Inventur KW 28`), `benutzer`.
Every stock change writes one row — the Bewegungen tab is this table verbatim.

### `reservierung`
`projekt_id`, `art_nr`, `menge`. Reserved stock is subtracted from available but
stays in `bestand`.

### `projekt`
`nr` (`PRJ-2026-011`), `kunde_id`, `objekt` (address), `status`, `termin`,
plus the configurator payload (dimensions, glass type/colour, posts, drainage,
wall connection, LED count) and the on-site Aufmaß values.

### `abnahmeprotokoll`
`nr` (`AP-2026-0114`), `projekt_id`, `art` (`ohne | vorbehalt | verweigert`),
checklist flags, `ort`, `datum`, two signature images, and n `maengel` rows
(`text`, `frist`). Immutable once signed; stored as a PDF in project documents.

### `dokument`
`projekt_id`, `typ`, `dateiname`, `groesse`, `datum`, `badge` (status chip).

---

## Modules / Screens

Navigation is a dropdown from the burger button in the header, grouped:
**Vertrieb** (Dashboard, Kunden, Anfragen, Angebote), **Abwicklung** (Projekte,
Bestellungen, Logistik, Kalender), **Lager** (Lager, Material-Katalog,
Lieferanten), **System** (Einstellungen). On screens ≤1200px the sidebar is gone
entirely and this dropdown is the only navigation.

### 1. Dashboard
KPI row (4 cards: Umsatz, offene Angebote, laufende Projekte, Montagen), a
Vertriebs-Trichter funnel with 5 stages, a monthly Auftragseingang bar chart, and
a warnings list. Responsive: 2 KPI columns ≤1200px, single column ≤760px.

### 2. Anfragen
Inbound enquiries as cards with source, date, contact, and a CTA to convert into
a quote. Filter chips by status.

### 3. Angebote
Quote list with the configurator behind it (dimensions, roof type, glass, posts,
drainage, wall connection, LED count, extras). The configurator computes the
material list — this is the calculation core and must be ported exactly.

### 4. Projekte
List → detail. Detail has tabs: Übersicht, Technische Zeichnungen, Technische
Daten, Materialliste, Dokumente, Aktivitäten, Übergabe. Header holds the actions:
PDF, Bearbeiten, Aktionen, **Montage-Modus**.

**Materialliste** is live: status (`Auf Lager / Bestellt / Geliefert`), Lagerort
and the source document per line come from warehouse state, not from a static
list. When a goods receipt is booked, the line flips to `Geliefert` with the
order number and date.

**Dokumente** lists project files; signed acceptance protocols are appended here
automatically (see module 11).

### 5. Montage-Modus (installer, on site — tablet)
Full-screen mode with 11 sections: Objekt & Termin, Adresse & Anfahrt, Kernmaße,
Pfosten, Profile, Verglasung, Beleuchtung, Endmaße der Extras, Notiz,
Zusatzmaterial, Montage-Checkliste.

Every measurement card shows **Soll** (from the configurator), an editable **Ist**
field, and a computed **Δ** with tolerance colouring: ≤5 mm green, ≤15 mm yellow,
>15 mm red. These thresholds are business rules — keep them configurable.

LED placement: exactly 3 candidate positions per inner rafter; the installer picks
which to use, up to the configured total (6 or 12). The final drawing shows only
placed lamps plus the resulting dimension chain.

Endmaße der Extras: large drawing left (~63%), stacked measurement fields right by
dimension (A/B/C/D). Stacks vertically below 1080px.

The Checkliste is the one unfinished flow: office-side task creation (seller or
project lead assigns tasks per date), installer checks them off, protocol is
generated from completed items.

### 6. Bestellungen
Order list → detail with status stepper, positions, and documents. When an order
is delivered, the detail shows a green banner **„Geliefert & eingelagert"** with
date, warehouse user, piece count and Lieferschein number, and every position
gains a `Lager` column showing `Eingelagert` / `Offen`.

### 7. Lager (warehouse)
Four tabs.

**Bestand** — table of all 41 articles: thumbnail, name + Art.-Nr. + supplier +
last receipt, category chip, Lagerort, Bestand with a fill bar, Reserviert,
Verfügbar, Min., EK, status badge (`Auf Lager / Niedrig / Nachbestellen`).
Category filter chips with counts. A red banner lists articles below minimum with
a Bestellvorschlag action. Row click opens the article modal (stock cells,
metadata, last receipt, reservations, movements, Korrektur / Nachbestellen).

**Wareneingang** — every non-cancelled order as a card. Not-yet-delivered orders
have a **Wareneingang buchen** button: booking adds all positions to stock, sets
the order to `geliefert`, writes movements, and issues a Lieferschein number.
Booked cards show the receipt line and links to Lieferschein and order.

**Reservierungen** — grouped by project: reserved quantity, coverage
(`Im Lager` / `Fehlt N`) and inbound status per article, plus a link to picking.

**Bewegungen** — the movement journal.

**Automatic booking is the central rule**: an order reaching `geliefert` books its
positions into stock in one transaction, and every document touching those
articles (order positions, project material list, Lieferschein) must then show
them as delivered.

### 8. Material-Katalog
The same article list, presented for procurement. KPIs (articles, suppliers,
average EK, how many have drawings), full-text search over name / Art.-Nr. /
supplier, category chips, and two views: **Karten** (grouped by category, with
component drawing, Art.-Nr., EK, supplier, availability) and **Liste** (all
fields). Row/card click opens the same article modal as the warehouse.

### 9. Logistik
Tour and picking planning per project.

### 10. Kalender
Installation and delivery dates.

### 11. Abnahmeprotokoll (modal)
Opened by **Protokoll erstellen** in Montage-Modus. German construction-law
conventions; the legal text below is final and must not be paraphrased.

Sections:
1. **Vertragsparteien** — Auftraggeber (name, address, phone, e-mail) and
   Auftragnehmer (company, address, Bauleitung, Montageteam).
2. **Bauvorhaben & Leistungsgegenstand** — project nr, order nr, site,
   construction, dimensions, completion date, and a table of executed positions.
3. **Erklärung des Auftraggebers** — three mutually exclusive options:
   - *Abnahme ohne Vorbehalt* — „Der Auftraggeber bestätigt, dass die Leistung
     vertragsgemäß, vollständig und mängelfrei ausgeführt wurde. Die Leistung wird
     hiermit ohne Vorbehalt abgenommen; Beanstandungen bestehen nicht."
   - *Abnahme unter Vorbehalt* — accepted, subject to the listed defects, with a
     nachbesserung deadline.
   - *Abnahme verweigert* — refused due to material defects; new date after repair.
4. **Beanstandungen** — shown for the latter two: rows of `Beschreibung` +
   `Frist`, add/remove. Footnote cites § 634 BGB and the retention right at twice
   the repair cost.
5. **Übergabe & Einweisung** — four checkboxes (instruction in lighting/awning/
   sliding elements, care and maintenance notes, conformity declaration + statics
   + manuals, site cleaned). Footnote: risk passes on acceptance; limitation
   period five years per § 634a Abs. 1 Nr. 2 BGB; final invoice after acceptance.
6. **Ort, Datum und Unterschriften** — two canvas signature pads (Auftraggeber,
   Monteur), each with its own clear button.

Validation: confirm is blocked until **both** signatures exist; with
Vorbehalt/Verweigerung at least one defect row must have text.

On confirm the protocol is written to project documents as
`Abnahmeprotokoll_AP-2026-0114[_mit_Vorbehalt|_verweigert].pdf` with a status
badge (`abgenommen` green / `N Mängel` yellow / `verweigert` red). Server side:
render the PDF with both signature images embedded, store immutably, and log who
signed and when.

---

## Technical drawings

Five SVG drawings generated from project dimensions: Keil (trapezoid),
Fest-/Wandelement, Schiebe-Elemente, Markisen, Sonnensegel. They open in a
lightbox with prev/next, a thumbnail rail, and a Maße toggle.

Visual language (match exactly):

- glass fill `#eef4fb`, outline `#26374a`
- dimension lines and text gold `#d9a441`
- walls drawn with a wall hatch pattern
- Rohmaß frame around trapezoids (Keil, Fest-Trapez, Segel)
- sliding elements: sash arrows plus a `LAUFSCHIENE` label
- Markise: cassette outline with bracket spacing
- no DIN-style hatching, no ISO ticks

`RoofDrawing.dc.html` holds the generators — port the geometry, not the markup.

---

## Interactions & Behavior

- **Navigation**: burger → dropdown panel (262px, radius 12px, shadow
  `0 14px 40px rgba(27,31,35,.16)`, 140ms fade-in-and-up). Closes on selection and
  on backdrop click. >1200px the same button collapses the sidebar instead.
- **Modals**: dark backdrop, click outside closes, content click stops
  propagation, `max-height:92vh` on touch devices.
- **Tables**: whole row is clickable where it opens a detail; `cursor:default` and
  an `:active` background on touch instead of hover.
- **Signature pads**: pointer events, devicePixelRatio-scaled canvas, 2.4px round
  cap stroke in `#1B1F23`, pointer capture on down so a stroke survives leaving
  the canvas.
- **Toasts** confirm every action that would hit the server.
- **Tolerance colouring** on all measurement inputs: ≤5 green, ≤15 yellow, else red.

## Responsive behaviour

| breakpoint | change |
|---|---|
| ≤1200px | sidebar removed, dropdown nav only; 3-col → 2-col; KPIs 2 per row; tables scroll horizontally (`min-width:640px`); catalogue grid 3 columns; dashboard chart 118px |
| ≤1024px | all columns single-flow; segmented controls full width, equal parts; lightbox thumbnails 3 columns; protocol two-column blocks stack; dashboard chart 150px |
| ≤820px | catalogue grid 2 columns |
| ≤760px | KPIs one per row |
| `hover:none` / `pointer:coarse` | buttons 44px (small 40px), chips/segments 42px, table cells 13px vertical padding, inputs 15px / min 42px (prevents iOS zoom), icon buttons min 44×44 |

Primary field device is a tablet in landscape (iPad 1180/1366) for
Montage-Modus; office use is desktop.

## Design Tokens

```css
--ink:#1B1F23;  --ink2:#4b535d;  --ink3:#828a94;
--bg:#fff;      --bg2:#F5F6F8;   --bg3:#eef1f5;
--bd:#E2E5EA;   --bd2:#edf0f4;
--gold:#D4A853; --goldd:#8a6a26; --goldbg:#faf3e2;   /* accent */
--blue:#4A6FA5; --blued:#3a5a88; --bluebg:#eef2f8;
--green:#2A7D4F;--greenbg:#e7f2ec;
--red:#C53030;  --redbg:#fbeaea;
--sh:0 1px 2px rgba(27,31,35,.04), 0 1px 3px rgba(27,31,35,.05);
--shl:0 12px 40px rgba(27,31,35,.16);
```

Accent is gold (`--acc: --gold`) and switchable via the prototype's `accent` prop.

**Type**: DM Sans (400/500/600/700) for UI, DM Mono (400/500) for numbers,
article numbers, dates and dimensions, DM Serif Display / Fraunces for document
headings (Abnahmeprotokoll title). Google Fonts:
`family=DM+Serif+Display&family=DM+Sans:opsz,wght@9..40,400..700&family=DM+Mono:wght@400;500`

**Radii**: 4px checkbox, 7–9px small controls and cells, 10–12px cards and panels,
14px lightbox.

**Spacing**: 4 / 6 / 8 / 10 / 12 / 14 / 16 / 18 / 20 px. Card padding 16–20px,
grid gaps 12–16px.

**Status badges**: green = done/available, yellow = in progress/tight,
red = blocked/missing, gray = neutral/open, blue = reservation.

## Assets

- `assets/comp/*.png` — 14 component drawings (Pfosten, Rinne, Wandanschluss,
  Deckleiste, Eckleiste, Rundleiste, Bodenprofil, Fallrohr, Gummi, Kappe,
  Tragprofil, Seitenträger, Wandblende, Trapezblech). Used as article thumbnails
  in catalogue and warehouse; supplied by the company.
- `anfahrt-map.js` — route/map view for Montage-Modus.
- Icons are an inline SVG sprite (`#ic-*`) in the prototype — reuse or replace
  with the codebase's icon set.

## Files

| file | contents |
|---|---|
| `LEA CRM.dc.html` | the full prototype: all 11 modules, warehouse logic, catalogue, protocol, seed data (`_lagerMaster`, `_lagerRes`, `_lagerMoves`, `bestRaw`) |
| `RoofDrawing.dc.html` | the five SVG drawing generators |
| `anfahrt-map.js` | map component |
| `assets/comp/` | component thumbnails |
| `lea-crm-standalone.html` | single-file offline build — open in a browser to click through everything without a server |

## Open items

1. **Montage-Checkliste** — office-side task creation and auto-generated protocol
   from completed items (designed, not wired).
2. **Persistence** — everything above is in-memory; needs the schema, migrations
   and endpoints.
3. **Users and permissions** — roles are implied (Verkäufer, Projektleiter,
   Lager, Monteur) but no auth exists in the prototype.
4. **PDF rendering** server-side for quotes, orders, Lieferscheine and protocols.
