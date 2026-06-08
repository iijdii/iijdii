#!/usr/bin/env node
/* ============================================================================
 * generate.js — CLI: erzeugt aus Maß-Daten einen druckfertigen
 *               Glaszuschnittplan (HTML).
 *
 * Verwendung / Использование:
 *   node generate.js [eingabe.json] [ausgabe.html]
 *
 * Ohne Argumente wird example.json gelesen und plan.html geschrieben.
 *
 * Eingabeformat (JSON):
 * {
 *   "meta":    { "project": "...", "order": "...", "date": "2026-06-08" },
 *   "H":       2170,
 *   "bottom":  [1370, 1280, 1080, 1070],
 *   "top":     [55, 55, 215, 50],
 *   "controls":[ { "diagAC": 2540 }, null, null, null ],
 *   "options": { "measureTolerance": 30, "longSideFactor": 1.3 }
 * }
 * ========================================================================== */
'use strict';
var fs = require('fs');
var path = require('path');
var Engine = require('./glass-engine.js');
var Render = require('./glass-render.js');

var inFile = process.argv[2] || path.join(__dirname, 'example.json');
var outFile = process.argv[3] || path.join(process.cwd(), 'plan.html');

var data = JSON.parse(fs.readFileSync(inFile, 'utf8'));
var plan = Engine.computePlan(data);
var html = Render.buildHTML(plan, data.meta || {});

fs.writeFileSync(outFile, html, 'utf8');

/* Kurzer Konsolen-Report */
console.log('Glaszuschnittplan erzeugt: ' + outFile);
console.log('Höhe H = ' + plan.H + ' mm, ' + plan.summary.count + ' Gläser\n');
plan.glasses.forEach(function (g) {
  console.log('Glas ' + g.index + ': Zuschnitt ' +
    Math.round(g.blank.width) + ' × ' + Math.round(g.blank.height) + ' mm' +
    (g.blank.rotated ? ' (MBR ' + (g.blank.angle * 180 / Math.PI).toFixed(1) + '°)' : '') +
    ' | Seiten U/O/L/R ' +
    Math.round(g.sides.bottom) + '/' + Math.round(g.sides.top) + '/' +
    Math.round(g.sides.left) + '/' + Math.round(g.sides.right) +
    ' | Verschnitt ' + g.wastePercent.toFixed(1) + '% | ' + g.control.status);
});
console.log('\nSumme Glas ' + plan.summary.totalGlass_m2.toFixed(3) +
  ' m² / Platte ' + plan.summary.totalBlank_m2.toFixed(3) +
  ' m² / Verschnitt ' + plan.summary.totalWastePercent.toFixed(1) + '%' +
  (plan.summary.needsRemeasure ? ' | ACHTUNG: NACHMESSEN ERFORDERLICH' : ''));
