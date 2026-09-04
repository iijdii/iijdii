# LEA CRM

Internes CRM/ERP für einen Terrassenüberdachungs-Betrieb: Anfrage → Angebot →
Projekt → Bestellungen → Lager → Logistik → Montage → unterschriebenes
Abnahmeprotokoll. Umsetzung des Design-Handoffs unter `design/`
(HTML-Prototyp = verbindliche Referenz für Layout, Texte und Rechenkern;
`design/README.md` = fachliche Regeln).

**Stack:** Laravel 13 · Blade (server-rendered, PRG) · Vite (plain CSS, keine
Frameworks) · SQLite (Dev/CI) / MySQL (Prod, `.env.example`) · dompdf.

## Loslegen

```sh
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite
php artisan migrate:fresh --seed
npm ci && npm run build
php artisan serve
```

Demo-Logins (Passwort jeweils `password`): `admin@` · `verkauf@` (Verkäufer)
· `projekt@` (Projektleitung) · `lager@` · `monteur@` — alle `@lea.test`.

Tests: `php artisan test` (PHPUnit, SQLite in-memory, CI in
`.github/workflows/ci.yml`).

## Umsetzungsstand

Alle 11 Module des Handoffs sind umgesetzt (Meilensteine 1–5), dazu die
Vervollständigung (Meilensteine 6–8):

- **M1–M5:** Fundament, Lager mit automatischer Einlagerung (zentrale Regel),
  Bestellungen, Material-Katalog, Kunden, Anfragen, Angebote, Projekte mit
  exakt portiertem Konfigurator-Rechenkern, Montage-Modus (Tablet,
  Soll/Ist/Δ mit konfigurierbaren Toleranzen, LED-Plan), Abnahmeprotokoll
  (Canvas-Unterschriften, echtes PDF, unveränderlich), fünf technische
  Dachzeichnungen + Lightbox, Dashboard (alle Zahlen live), Logistik mit
  Kommissionierung/Touren/Lade-Modus, Kalender, Lieferanten, Einstellungen.
- **M6:** Angebots-Lebenszyklus (Status-Übergänge, Gesamtsumme →
  Zahlungsplan/Dashboard leben), vier PDFs (Angebot, Bestellung,
  Lieferschein, Projektmappe), Dokumenten-Upload, Deep-Links.
- **M7:** Stammdaten & Beschaffung — Kunden-/Artikel-CRUD, Alias-Verwaltung
  (automatische Wareneingangs-Zuordnung), Lagerkorrektur, Bestellungen
  anlegen inkl. Positions-Editor (Glas/Schiebe/Material), Bestellvorschlag/
  Nachbestellen, Projekt-Reservierungen.
- **M8:** Betrieb — Projektleitung/Termine/Status am Projekt, Kalender-
  Terminanlage, Office-Pflege der Montage-Checkliste (READMEs Open Item 1),
  Abnahme-Positionen aus erledigten Aufgaben, Rollen-Matrix auf allen
  Schreibrouten (Open Item 3; PDFs = Open Item 4 in M6).

## Bewusst offen / zurückgestellt

- **Live-Draufsicht im Anfrage-Formular** (Prototyp `cfg-drawcard`): die
  numerische Live-Kalkulation existiert, die mitzeichnende SVG nicht —
  statische Draufsicht liegt im Anfrage-Detail.
- **Globale Suche, Benachrichtigungen, Benutzermenü** der Prototyp-Kopfzeile.
- **XLSX-Exporte** (Inventurliste, Katalog) — auch im Prototyp nur Toasts.
- **Kalender-Legende** führt Service/Puffer als künftige Termintypen
  (README nennt sie, Datenquellen existieren noch nicht).
- **Anfrage-Formular** deckt die Kernfelder ab; die vollständige Feldliste
  aus `docs/anfrage-fields.txt` ist im Schema angelegt, aber nicht komplett
  im Formular verdrahtet.
- **Lieferanten-Detailansicht** (Liste + Kennzahlen vorhanden, Pflege der
  Stammdaten über den Seeder).
