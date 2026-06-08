#!/usr/bin/env node
/* Schnelltest des Rechenkerns / Быстрый тест движка. node test.js */
'use strict';
var E = require('./glass-engine.js');
var pass = 0, fail = 0;
function ok(name, cond) { (cond ? pass++ : fail++); console.log((cond ? '  ok  ' : 'FAIL  ') + name); }
function near(a, b, eps) { return Math.abs(a - b) <= (eps == null ? 0.5 : eps); }

/* Geometrie-Grundlagen */
ok('dist 3-4-5', near(E.dist({ x: 0, y: 0 }, { x: 3, y: 4 }), 5));
ok('polygonArea Einheitsquadrat',
  near(E.polygonArea([{ x: 0, y: 0 }, { x: 10, y: 0 }, { x: 10, y: 10 }, { x: 0, y: 10 }]), 100));

/* MBR: gedrehtes Quadrat (45°) -> Fläche bleibt minimal = Originalfläche */
var diamond = [{ x: 0, y: 1 }, { x: 1, y: 0 }, { x: 0, y: -1 }, { x: -1, y: 0 }];
var mbr = E.minBoundingRect(diamond);
ok('MBR Fläche eines gedrehten Quadrats ist minimal (~2)', near(mbr.area, 2, 0.05));
ok('MBR ist als rotiert markiert', mbr.rotated === true);
var bb = E.boundingBox(diamond);
ok('Bounding Box ist größer als MBR für gedrehte Figur', bb.width * bb.height > mbr.area - 1e-9);

/* Vollständiger Plan auf Beispieldaten */
var plan = E.computePlan({
  H: 2170,
  bottom: [1370, 1280, 1080, 1070],
  top: [55, 55, 215, 50],
  controls: [{ diagAC: 2540 }, null, null, null]
});
ok('4 Gläser berechnet', plan.glasses.length === 4);
ok('Glas 1 Unterkante = 1370', near(plan.glasses[0].sides.bottom, 1370));
ok('Glas 1 linke Seite = 2170', near(plan.glasses[0].sides.left, 2170));
ok('Glas 1 ist Standard-Zuschnitt (longSide < 1.3H)', plan.glasses[0].blankType === 'NORMAL');
ok('Glas 4 braucht MBR (longSide > 1.3H)', plan.glasses[3].blankType === 'MBR');
ok('Glas 1 löst NACHMESSEN aus (Δ diagAC > 30)', plan.glasses[0].control.status === 'NACHMESSEN ERFORDERLICH');
ok('Glas 2 ohne Kontrollmaß', plan.glasses[1].control.status === 'OHNE KONTROLLMASS');
ok('Verschnitt zwischen 0 und 100 %', plan.glasses.every(function (g) {
  return g.wastePercent >= 0 && g.wastePercent < 100;
}));
ok('Plattenfläche >= Glasfläche je Glas', plan.glasses.every(function (g) {
  return g.areas.blank_m2 >= g.areas.glass_m2 - 1e-6;
}));

/* Fehlerbehandlung */
var threw = false;
try { E.computePlan({ H: 2000, bottom: [100, 200], top: [50] }); } catch (e) { threw = true; }
ok('ungleiche Längen bottom/top werfen Fehler', threw);

console.log('\n' + pass + ' bestanden, ' + fail + ' fehlgeschlagen');
process.exit(fail ? 1 : 0);
