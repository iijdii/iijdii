/* ============================================================================
 * GLASZUSCHNITT-RENDER  (SVG-Zeichnungen + druckfertiges HTML)
 * ----------------------------------------------------------------------------
 * Erzeugt aus dem Ergebnis von GlassEngine.computePlan():
 *   - lesbare SVG-Zeichnung je Glas (Zuschnitt grau gestrichelt, Glas blau,
 *     Schnitte orange, alle Maße, Verschnitt-Beschriftung)
 *   - die Zusammenfassungstabelle
 *   - eine komplette HTML-Datei (A4-Druck, deutsche Beschriftung)
 *
 * Maße überlappen nie: Bemaßungen liegen außerhalb der Figur, der Rand des
 * SVG wächst bei Bedarf automatisch mit (siehe computeMargins()).
 * ========================================================================== */
(function (root, factory) {
  if (typeof module === 'object' && module.exports) {
    module.exports = factory(require('./glass-engine.js'));
  } else {
    root.GlassRender = factory(root.GlassEngine);
  }
}(typeof self !== 'undefined' ? self : this, function (Engine) {
  'use strict';

  var STYLE = {
    blankStroke: '#888',          // Zuschnitt-Platte
    glassFill: 'rgba(56,135,209,0.30)',
    glassStroke: '#2b6cb0',
    cutStroke: '#e8730c',         // Schnitte (Resы)
    dimColor: '#222',
    blankDimColor: '#666',
    wasteColor: '#b23b3b',
    font: 'Arial, Helvetica, sans-serif'
  };

  function round(v, d) {
    var f = Math.pow(10, d || 0);
    return Math.round(v * f) / f;
  }
  function esc(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }
  function mid(a, b) { return { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 }; }

  /* ---- SVG-Bemaßungsbeschriftung außerhalb der Figur --------------------- */
  // Verschiebt das Label entlang der nach außen zeigenden Normale der Kante,
  // damit Text und Figur sich nicht überlagern.
  function edgeLabel(a, b, centroid, text, color, fontSize) {
    var m = mid(a, b);
    var nx = m.x - centroid.x, ny = m.y - centroid.y;
    var nl = Math.hypot(nx, ny) || 1;
    var off = fontSize * 1.4;
    var lx = m.x + (nx / nl) * off;
    var ly = m.y + (ny / nl) * off;
    return '<text x="' + round(lx, 1) + '" y="' + round(ly, 1) + '" ' +
      'fill="' + color + '" font-size="' + fontSize + '" font-family="' + STYLE.font + '" ' +
      'text-anchor="middle" dominant-baseline="middle">' + esc(text) + '</text>';
  }

  /* ---- Layout-Berechnung: Ränder wachsen automatisch --------------------- */
  function computeMargins(fontSize) {
    // Großzügiger Rand, in den die Bemaßungen gelegt werden -> kein Überlapp.
    return fontSize * 6;
  }

  /* ---- Eine Glas-Zeichnung als SVG --------------------------------------- */
  function glassSVG(glass, opt) {
    opt = opt || {};
    var maxDraw = opt.maxDraw || 320;          // px für die größere Kante
    var fontSize = opt.fontSize || 13;

    // Alle relevanten Punkte (Glas + Zuschnitt) bestimmen die Welt-Bounds.
    var pts = glass.quad.concat(glass.blank.corners);
    var minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
    pts.forEach(function (p) {
      if (p.x < minX) minX = p.x; if (p.x > maxX) maxX = p.x;
      if (p.y < minY) minY = p.y; if (p.y > maxY) maxY = p.y;
    });
    var worldW = (maxX - minX) || 1;
    var worldH = (maxY - minY) || 1;
    var scale = maxDraw / Math.max(worldW, worldH);

    var margin = computeMargins(fontSize);
    var W = worldW * scale + margin * 2;
    var Hpx = worldH * scale + margin * 2;

    // Welt -> SVG. y wird gespiegelt (mm: y nach oben, SVG: y nach unten).
    function tx(p) {
      return {
        x: margin + (p.x - minX) * scale,
        y: margin + (maxY - p.y) * scale
      };
    }

    var g = glass.quad.map(tx);                 // A,B,C,D in SVG-Koords
    var bl = glass.blank.corners.map(tx);
    var centroid = {
      x: (g[0].x + g[1].x + g[2].x + g[3].x) / 4,
      y: (g[0].y + g[1].y + g[2].y + g[3].y) / 4
    };

    var parts = [];
    parts.push('<svg viewBox="0 0 ' + round(W, 1) + ' ' + round(Hpx, 1) + '" ' +
      'width="100%" preserveAspectRatio="xMidYMid meet" ' +
      'xmlns="http://www.w3.org/2000/svg" font-family="' + STYLE.font + '">');

    // Zuschnitt-Platte: grau gestrichelt
    parts.push('<polygon points="' + bl.map(function (p) { return round(p.x, 1) + ',' + round(p.y, 1); }).join(' ') + '" ' +
      'fill="none" stroke="' + STYLE.blankStroke + '" stroke-width="1.2" stroke-dasharray="6 4"/>');

    // Glas: blaue Füllung
    parts.push('<polygon points="' + g.map(function (p) { return round(p.x, 1) + ',' + round(p.y, 1); }).join(' ') + '" ' +
      'fill="' + STYLE.glassFill + '" stroke="' + STYLE.glassStroke + '" stroke-width="1.2"/>');

    // Schnitte (Resы) = Kanten des Glases, die NICHT auf dem Plattenrand liegen.
    // Heuristik: Kante ist ein Schnitt, wenn sie nicht (nahezu) achsen-/randparallel
    // auf der Platte liegt. Praktisch zeichnen wir die geneigten Kanten orange.
    var quad = glass.quad;
    for (var e = 0; e < 4; e++) {
      var a = quad[e], b = quad[(e + 1) % 4];
      var dx = Math.abs(b.x - a.x), dy = Math.abs(b.y - a.y);
      var isCut = !glass.blank.rotated ? (dx > 0.5 && dy > 0.5) : true; // geneigt
      if (isCut) {
        var sa = tx(a), sb = tx(b);
        parts.push('<line x1="' + round(sa.x, 1) + '" y1="' + round(sa.y, 1) + '" ' +
          'x2="' + round(sb.x, 1) + '" y2="' + round(sb.y, 1) + '" ' +
          'stroke="' + STYLE.cutStroke + '" stroke-width="2.4"/>');
      }
    }

    // Eckpunkt-Marker A/B/C/D
    var labels = ['A', 'B', 'C', 'D'];
    g.forEach(function (p, idx) {
      parts.push('<circle cx="' + round(p.x, 1) + '" cy="' + round(p.y, 1) + '" r="2.5" fill="' + STYLE.glassStroke + '"/>');
      var lx = p.x + (p.x - centroid.x) * 0.06;
      var ly = p.y + (p.y - centroid.y) * 0.06;
      parts.push('<text x="' + round(lx, 1) + '" y="' + round(ly, 1) + '" font-size="' + (fontSize - 2) +
        '" fill="' + STYLE.glassStroke + '" text-anchor="middle">' + labels[idx] + '</text>');
    });

    // Bemaßung der vier Glasseiten (außerhalb)
    parts.push(edgeLabel(g[0], g[1], centroid, round(glass.sides.bottom) + '', STYLE.dimColor, fontSize)); // A-B unten
    parts.push(edgeLabel(g[1], g[2], centroid, round(glass.sides.right) + '', STYLE.dimColor, fontSize));  // B-C rechts
    parts.push(edgeLabel(g[2], g[3], centroid, round(glass.sides.top) + '', STYLE.dimColor, fontSize));    // C-D oben
    parts.push(edgeLabel(g[3], g[0], centroid, round(glass.sides.left) + '', STYLE.dimColor, fontSize));   // D-A links

    // Zuschnittmaße (grau) an der Platte
    var blCentroid = {
      x: (bl[0].x + bl[1].x + bl[2].x + bl[3].x) / 4,
      y: (bl[0].y + bl[1].y + bl[2].y + bl[3].y) / 4
    };
    parts.push(edgeLabel(bl[0], bl[1], blCentroid, '▭ ' + round(glass.blank.width), STYLE.blankDimColor, fontSize - 1));
    parts.push(edgeLabel(bl[1], bl[2], blCentroid, '▭ ' + round(glass.blank.height), STYLE.blankDimColor, fontSize - 1));

    // Verschnitt-Beschriftung
    parts.push('<text x="' + round(W / 2, 1) + '" y="' + round(Hpx - fontSize, 1) + '" ' +
      'fill="' + STYLE.wasteColor + '" font-size="' + fontSize + '" text-anchor="middle">' +
      'Verschnitt: ' + round(glass.wastePercent, 1) + '%</text>');

    parts.push('</svg>');
    return parts.join('\n');
  }

  /* ---- Datenkarte je Glas (Zahlen redundant, garantiert lesbar) ---------- */
  function glassCard(glass) {
    var c = glass.control;
    var statusClass = c.status === 'OK' ? 'ok' : (c.status === 'OHNE KONTROLLMASS' ? 'neutral' : 'warn');
    var rows = [
      ['Unterkante (AB)', round(glass.sides.bottom) + ' mm'],
      ['Oberkante (CD)', round(glass.sides.top) + ' mm'],
      ['Linke Seite (DA)', round(glass.sides.left) + ' mm'],
      ['Rechte Seite (BC)', round(glass.sides.right) + ' mm'],
      ['Diagonale A–C', round(glass.diagonals.ac) + ' mm'],
      ['Diagonale B–D', round(glass.diagonals.bd) + ' mm'],
      ['Zuschnitt (Platte)', round(glass.blank.width) + ' × ' + round(glass.blank.height) + ' mm' +
        (glass.blank.rotated ? ' (gedreht ' + round(glass.blank.angle * 180 / Math.PI, 1) + '°)' : '')],
      ['Glasfläche', round(glass.areas.glass_m2, 3) + ' m²'],
      ['Plattenfläche', round(glass.areas.blank_m2, 3) + ' m²'],
      ['Verschnitt', round(glass.wastePercent, 1) + ' %']
    ];
    var rowsHtml = rows.map(function (r) {
      return '<tr><td>' + esc(r[0]) + '</td><td>' + esc(r[1]) + '</td></tr>';
    }).join('');

    var ctrlHtml = '';
    if (c.checks.length) {
      ctrlHtml = '<div class="ctrl"><b>Kontrollmaße:</b><ul>' + c.checks.map(function (ch) {
        return '<li class="' + (ch.ok ? 'ok' : 'warn') + '">' + esc(ch.name) +
          ': Soll ' + round(ch.computed) + ' / Mess ' + round(ch.measured) +
          ' (Δ ' + round(ch.deviation) + ' mm)' + (ch.ok ? '' : ' ⚠') + '</li>';
      }).join('') + '</ul></div>';
    }

    return '<div class="glass-card">' +
      '<div class="glass-head"><h3>Glas ' + glass.index +
      ' <span class="badge ' + (glass.blankType === 'MBR' ? 'mbr' : '') + '">' +
      (glass.blankType === 'MBR' ? 'gedrehter Zuschnitt (MBR)' : 'Standard-Zuschnitt') + '</span>' +
      '<span class="status ' + statusClass + '">' + esc(glass.control.status) + '</span></h3></div>' +
      '<div class="glass-body">' +
      '<div class="glass-svg">' + glassSVG(glass) + '</div>' +
      '<div class="glass-data"><table>' + rowsHtml + '</table>' + ctrlHtml + '</div>' +
      '</div></div>';
  }

  /* ---- Zusammenfassungstabelle ------------------------------------------- */
  function summaryTable(plan) {
    var head = '<tr><th>Nr.</th><th>Zuschnitt (mm)</th><th>Glasmaß B×O / L / R</th>' +
      '<th>Glasfläche</th><th>Plattenfläche</th><th>Verschnitt</th><th>Prüfung</th></tr>';
    var body = plan.glasses.map(function (g) {
      var sc = g.control.status === 'OK' ? 'ok' : (g.control.status === 'OHNE KONTROLLMASS' ? 'neutral' : 'warn');
      return '<tr>' +
        '<td>' + g.index + '</td>' +
        '<td>' + round(g.blank.width) + ' × ' + round(g.blank.height) +
        (g.blank.rotated ? ' ↻' : '') + '</td>' +
        '<td>' + round(g.sides.bottom) + ' / ' + round(g.sides.top) + ' / ' +
        round(g.sides.left) + ' / ' + round(g.sides.right) + '</td>' +
        '<td>' + round(g.areas.glass_m2, 3) + ' m²</td>' +
        '<td>' + round(g.areas.blank_m2, 3) + ' m²</td>' +
        '<td>' + round(g.wastePercent, 1) + ' %</td>' +
        '<td class="status ' + sc + '">' + esc(g.control.status) + '</td>' +
        '</tr>';
    }).join('');
    var foot = '<tr class="total"><td colspan="3">Summe</td>' +
      '<td>' + round(plan.summary.totalGlass_m2, 3) + ' m²</td>' +
      '<td>' + round(plan.summary.totalBlank_m2, 3) + ' m²</td>' +
      '<td>' + round(plan.summary.totalWastePercent, 1) + ' %</td>' +
      '<td>' + (plan.summary.needsRemeasure ? 'NACHMESSEN' : 'OK') + '</td></tr>';
    return '<table class="summary"><thead>' + head + '</thead><tbody>' + body + foot + '</tbody></table>';
  }

  /* ---- Komplettes druckfertiges HTML (A4) -------------------------------- */
  function buildHTML(plan, meta) {
    meta = meta || {};
    var dateStr = meta.date || new Date().toISOString().slice(0, 10);
    var cards = plan.glasses.map(glassCard).join('\n');

    return '<!DOCTYPE html>\n<html lang="de">\n<head>\n<meta charset="utf-8"/>\n' +
      '<meta name="viewport" content="width=device-width, initial-scale=1"/>\n' +
      '<title>Glaszuschnittplan</title>\n<style>\n' + CSS + '\n</style>\n</head>\n<body>\n' +
      '<header class="sheet-head">\n' +
      '  <div>\n' +
      '    <h1>Glaszuschnittplan</h1>\n' +
      '    <p class="subtitle">Alle Maße in mm<br/>Zeichnung nicht maßstabsgetreu<br/>Fertigung nach Maßangaben</p>\n' +
      '  </div>\n' +
      '  <div class="meta">\n' +
      (meta.project ? '    <div><span>Projekt:</span> ' + esc(meta.project) + '</div>\n' : '') +
      (meta.order ? '    <div><span>Auftrag:</span> ' + esc(meta.order) + '</div>\n' : '') +
      '    <div><span>Höhe H:</span> ' + round(plan.H) + ' mm</div>\n' +
      '    <div><span>Gläser:</span> ' + plan.summary.count + '</div>\n' +
      '    <div><span>Datum:</span> ' + esc(dateStr) + '</div>\n' +
      '  </div>\n' +
      '</header>\n' +
      '<section class="legend">\n' +
      '  <span><i class="sw blank"></i> Zuschnitt-Platte</span>\n' +
      '  <span><i class="sw glass"></i> Glas</span>\n' +
      '  <span><i class="sw cut"></i> Schnitte</span>\n' +
      '  <span><i class="sw warn"></i> Nachmessen nötig</span>\n' +
      '</section>\n' +
      '<section class="cards">\n' + cards + '\n</section>\n' +
      '<section class="summary-wrap"><h2>Schnittliste / Zusammenfassung</h2>\n' +
      summaryTable(plan) + '</section>\n' +
      '<footer class="sheet-foot">Glaszuschnittplan · Fertigung nach Maßangaben · Alle Maße in mm</footer>\n' +
      '</body>\n</html>\n';
  }

  var CSS = [
    '* { box-sizing: border-box; }',
    'body { margin: 0; font-family: Arial, Helvetica, sans-serif; color: #1a1a1a; background: #f3f4f6; }',
    '.sheet-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 20px; padding: 18px 24px; border-bottom: 3px solid #1a1a1a; background: #fff; }',
    'h1 { margin: 0 0 6px; font-size: 26px; letter-spacing: 0.5px; }',
    '.subtitle { margin: 0; color: #555; font-size: 12px; line-height: 1.5; }',
    '.meta { font-size: 12px; text-align: right; }',
    '.meta div { margin: 2px 0; }',
    '.meta span { color: #777; display: inline-block; min-width: 70px; text-align: left; }',
    '.legend { display: flex; gap: 18px; padding: 10px 24px; font-size: 12px; background: #fff; border-bottom: 1px solid #ddd; flex-wrap: wrap; }',
    '.legend .sw { display: inline-block; width: 16px; height: 12px; margin-right: 4px; vertical-align: middle; border: 1px solid #999; }',
    '.sw.blank { background: repeating-linear-gradient(90deg,#888 0 4px,transparent 4px 8px); border-color:#888; }',
    '.sw.glass { background: rgba(56,135,209,0.30); border-color: #2b6cb0; }',
    '.sw.cut { background: #e8730c; border-color: #e8730c; }',
    '.sw.warn { background: #f6c6c6; border-color: #b23b3b; }',
    '.cards { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; padding: 16px 24px; }',
    '.glass-card { background: #fff; border: 1px solid #ddd; border-radius: 6px; overflow: hidden; page-break-inside: avoid; break-inside: avoid; }',
    '.glass-head h3 { margin: 0; padding: 10px 12px; font-size: 15px; background: #1f2937; color: #fff; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }',
    '.badge { font-size: 10px; font-weight: normal; background: #374151; padding: 2px 6px; border-radius: 3px; }',
    '.badge.mbr { background: #8a4b0c; }',
    '.status { font-size: 10px; padding: 2px 6px; border-radius: 3px; margin-left: auto; }',
    '.status.ok { background: #1f7a3d; color: #fff; }',
    '.status.warn { background: #b23b3b; color: #fff; }',
    '.status.neutral { background: #6b7280; color: #fff; }',
    '.glass-body { display: flex; gap: 8px; padding: 10px; }',
    '.glass-svg { flex: 1 1 55%; min-width: 0; }',
    '.glass-svg svg { width: 100%; height: auto; }',
    '.glass-data { flex: 1 1 45%; font-size: 11px; }',
    '.glass-data table { width: 100%; border-collapse: collapse; }',
    '.glass-data td { padding: 2px 4px; border-bottom: 1px solid #eee; }',
    '.glass-data td:last-child { text-align: right; font-variant-numeric: tabular-nums; }',
    '.ctrl { margin-top: 6px; font-size: 10px; }',
    '.ctrl ul { margin: 4px 0 0; padding-left: 16px; }',
    '.ctrl li.warn { color: #b23b3b; font-weight: bold; }',
    '.ctrl li.ok { color: #1f7a3d; }',
    '.summary-wrap { padding: 8px 24px 24px; }',
    'h2 { font-size: 18px; }',
    'table.summary { width: 100%; border-collapse: collapse; font-size: 12px; background: #fff; }',
    'table.summary th, table.summary td { border: 1px solid #ccc; padding: 6px 8px; text-align: center; }',
    'table.summary th { background: #1f2937; color: #fff; }',
    'table.summary td.status.ok { background: #d6f0de; }',
    'table.summary td.status.warn { background: #f6d6d6; color: #b23b3b; font-weight: bold; }',
    'table.summary td.status.neutral { background: #eef0f2; }',
    'table.summary tr.total td { font-weight: bold; background: #eef2f7; }',
    '.sheet-foot { padding: 10px 24px; font-size: 11px; color: #777; text-align: center; border-top: 1px solid #ddd; }',
    '@page { size: A4; margin: 12mm; }',
    '@media print {',
    '  body { background: #fff; }',
    '  .sheet-head, .legend, .glass-card, table.summary { background: #fff; }',
    '  .cards { padding: 8px 0; gap: 10px; }',
    '  .summary-wrap, .sheet-head, .legend { padding-left: 0; padding-right: 0; }',
    '}'
  ].join('\n');

  return {
    glassSVG: glassSVG,
    glassCard: glassCard,
    summaryTable: summaryTable,
    buildHTML: buildHTML
  };
}));
