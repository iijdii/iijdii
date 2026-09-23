<meta charset="utf-8">
@php
    /*
     * LEA-Dokumentendesign (Spezifikation v3.2): Anthrazit-Palette als
     * Standard, «blau» = blau-goldene Variante für Bestellungen.
     * dompdf kennt weder Flexbox noch CSS-Variablen — Farben werden hier
     * als PHP-Werte eingesetzt, Kopf/Fuß laufen über Tabellen bzw.
     * position:fixed (Fuß wiederholt sich auf jeder Seite).
     */
    $blau = ($palette ?? '') === 'blau';
    $cHaupt = $blau ? '#26374a' : '#2B2E34';   // Logo, Tabellenkopf, H1
    $cText = $blau ? '#18202a' : '#171A1F';
    $cMuted = $blau ? '#667085' : '#6F7682';
    $cLine = $blau ? '#d8dee8' : '#D8DCE2';
    $cSoft = $blau ? '#f5f7fb' : '#F4F6F8';
    $cAkzent = $blau ? '#d9a441' : '#2f3e50';
@endphp
<style>
    @page { margin: 16mm 18mm 30mm; }
    /* Nur dompdf-gebündelte DejaVu-Fonts — keine externen Ressourcen. */
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10.5px; line-height: 1.45; color: {{ $cText }}; margin: 0; }

    /* Briefkopf: Logo links, Firmendaten rechts */
    .kopf { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
    .kopf td { padding: 0; vertical-align: top; }
    .logo { font-size: 28px; font-weight: bold; text-transform: uppercase; letter-spacing: 2px; color: {{ $cHaupt }}; }
    .logo .akzent { color: {{ $cAkzent }}; }
    .logo-untertitel { font-size: 9px; text-transform: uppercase; letter-spacing: 3px; color: {{ $cMuted }}; margin-top: 2px; }
    .firmen-daten { text-align: right; font-size: 10px; color: {{ $cMuted }}; line-height: 1.35; }

    h1 { font-size: 21px; line-height: 1.15; color: {{ $cHaupt }}; margin: 14px 0 6px; }
    .badge { display: inline-block; border: 1px solid {{ $cLine }}; background: {{ $cSoft }}; padding: 4px 9px;
             font-size: 10px; font-weight: bold; color: {{ $cHaupt }}; text-transform: uppercase; letter-spacing: .5px;
             margin-right: 4px; }
    h2 { font-size: 14px; color: {{ $cHaupt }}; border-bottom: 1px solid {{ $cLine }}; padding-bottom: 5px; margin: 20px 0 9px; }

    /* Block-Karte (Objekt/Kunde) */
    .box { border: 1px solid {{ $cLine }}; background: {{ $cSoft }}; padding: 10px; }
    .box-titel { font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: .8px;
                 color: {{ $cHaupt }}; margin-bottom: 5px; }
    .box-paar { width: 100%; border-collapse: separate; border-spacing: 0; }
    .box-paar td.haelfte { width: 49%; vertical-align: top; padding: 0; }
    .box-paar td.spalte { width: 2%; padding: 0; }

    table { width: 100%; border-collapse: collapse; }
    td, th { padding: 3px 6px; vertical-align: top; text-align: left; }
    .kv td { font-size: 10.5px; padding: 2px 6px 2px 0; }
    .kv td.k { width: 105px; color: {{ $cMuted }}; }

    /* Positionstabelle — dunkler Firmen-Tabellenkopf */
    .positions { width: 100%; border-collapse: collapse; }
    .positions th { background: {{ $cHaupt }}; color: #FFFFFF; font-size: 10px; text-align: left;
                    padding: 7px 6px; font-weight: bold; }
    .positions td { border-bottom: 1px solid {{ $cLine }}; padding: 6px; font-size: 10.5px; vertical-align: top; }
    .num { text-align: right; }

    .summen td { padding: 4px 6px; font-size: 10.5px; }
    .summen .gesamt td { border-top: 1.5px solid {{ $cHaupt }}; font-weight: bold; font-size: 12px; }

    .legal { font-size: 8.5px; color: {{ $cMuted }}; margin: 8px 0 0; line-height: 1.4; }

    .seitenumbruch { page-break-before: always; }
    .hinweis { border: 1px solid {{ $cLine }}; background: {{ $cSoft }}; padding: 9px 11px;
               font-size: 9.5px; color: {{ $cText }}; margin-top: 12px; line-height: 1.45; }
    .check { margin: 4px 0; font-size: 10.5px; }
    .pos-detail { font-size: 9px; color: {{ $cMuted }}; line-height: 1.4; }

    /* Unterschriftenzeile */
    .unterschriften { width: 100%; border-collapse: collapse; margin-top: 34px; }
    .unterschriften td { width: 44%; padding: 0; }
    .unterschriften td.spalte-leer { width: 12%; }
    .signatur-linie { border-top: 1px solid {{ $cHaupt }}; padding-top: 4px; color: {{ $cMuted }}; font-size: 10px; }

    /* Fußzeile: 3 Spalten, fix am Seitenende — wiederholt sich je Seite */
    .fuss { position: fixed; bottom: -21mm; left: 0; right: 0; border-top: 1px solid {{ $cHaupt }};
            padding-top: 6px; font-size: 8.2px; color: {{ $cMuted }}; line-height: 1.35; }
    .fuss table { width: 100%; border-collapse: collapse; }
    .fuss td { padding: 0 8px 0 0; vertical-align: top; }
    .fuss b { color: {{ $cHaupt }}; font-size: 8.2px; }
    .seite { position: fixed; bottom: -26mm; right: 0; font-size: 8px; color: {{ $cMuted }}; text-align: right; }
    .seite:after { content: "Seite " counter(page); }
</style>
