/* ============================================================================
 * GLASZUSCHNITT-ENGINE  (Engine for the glass cutting plan)
 * ----------------------------------------------------------------------------
 * Reiner Rechenkern ohne Abhängigkeiten. Läuft im Browser (window.GlassEngine)
 * und in Node (module.exports).
 *
 * Чистый расчётный модуль без зависимостей. Работает в браузере
 * (window.GlassEngine) и в Node (module.exports).
 *
 * Aufgabe / Задача:
 *   Aus den Koordinaten einer Konstruktion automatisch:
 *     1. reale Glasmaße berechnen
 *     2. Kontrolldiagonalen / Kontrollmaße prüfen
 *     3. optimale Zuschnitt-Platte (Zuschnitt) bestimmen
 *     4. Flächen und Verschnitt (Abfall) berechnen
 * ========================================================================== */
(function (root, factory) {
  if (typeof module === 'object' && module.exports) module.exports = factory();
  else root.GlassEngine = factory();
}(typeof self !== 'undefined' ? self : this, function () {
  'use strict';

  /* ---- Konfiguration / Конфигурация -------------------------------------- */
  var DEFAULTS = {
    measureTolerance: 30,   // mm — Abweichung, ab der nachgemessen werden muss
    longSideFactor: 1.3     // Faktor für lange Seite vs. Höhe (1.3 * H)
  };

  /* ---- Geometrie-Helfer / Геометрические помощники ----------------------- */

  function dist(a, b) {
    return Math.hypot(b.x - a.x, b.y - a.y);
  }

  // Polygonfläche per Gauß'scher Trapezformel (Shoelace). Vorzeichenfrei.
  function polygonArea(pts) {
    var s = 0;
    for (var i = 0; i < pts.length; i++) {
      var p = pts[i];
      var q = pts[(i + 1) % pts.length];
      s += p.x * q.y - q.x * p.y;
    }
    return Math.abs(s) / 2;
  }

  // Konvexe Hülle (Andrew's monotone chain), gegen den Uhrzeigersinn.
  function convexHull(points) {
    var pts = points.slice().sort(function (a, b) {
      return a.x === b.x ? a.y - b.y : a.x - b.x;
    });
    if (pts.length <= 2) return pts;
    var cross = function (o, a, b) {
      return (a.x - o.x) * (b.y - o.y) - (a.y - o.y) * (b.x - o.x);
    };
    var lower = [];
    for (var i = 0; i < pts.length; i++) {
      while (lower.length >= 2 && cross(lower[lower.length - 2], lower[lower.length - 1], pts[i]) <= 0) lower.pop();
      lower.push(pts[i]);
    }
    var upper = [];
    for (var j = pts.length - 1; j >= 0; j--) {
      while (upper.length >= 2 && cross(upper[upper.length - 2], upper[upper.length - 1], pts[j]) <= 0) upper.pop();
      upper.push(pts[j]);
    }
    lower.pop(); upper.pop();
    return lower.concat(upper);
  }

  // Achsenparalleles umschließendes Rechteck (Bounding Box).
  function boundingBox(pts) {
    var minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
    pts.forEach(function (p) {
      if (p.x < minX) minX = p.x; if (p.x > maxX) maxX = p.x;
      if (p.y < minY) minY = p.y; if (p.y > maxY) maxY = p.y;
    });
    return {
      width: maxX - minX,
      height: maxY - minY,
      angle: 0,
      rotated: false,
      corners: [
        { x: minX, y: minY }, { x: maxX, y: minY },
        { x: maxX, y: maxY }, { x: minX, y: maxY }
      ]
    };
  }

  // Minimum Bounding Rectangle (rotierendes Rechteck minimaler Fläche).
  // Rotating-Calipers-Prinzip: das flächenminimale Rechteck einer konvexen
  // Hülle liegt mit einer Kante auf einer Hüllenkante.
  function minBoundingRect(points) {
    var hull = convexHull(points);
    if (hull.length < 3) return boundingBox(points);
    var best = null;
    for (var i = 0; i < hull.length; i++) {
      var a = hull[i], b = hull[(i + 1) % hull.length];
      var dx = b.x - a.x, dy = b.y - a.y;
      var len = Math.hypot(dx, dy) || 1;
      var ux = dx / len, uy = dy / len;   // Kantenrichtung
      var vx = -uy, vy = ux;              // Normale
      var minU = Infinity, maxU = -Infinity, minV = Infinity, maxV = -Infinity;
      for (var k = 0; k < hull.length; k++) {
        var p = hull[k];
        var pu = p.x * ux + p.y * uy;
        var pv = p.x * vx + p.y * vy;
        if (pu < minU) minU = pu; if (pu > maxU) maxU = pu;
        if (pv < minV) minV = pv; if (pv > maxV) maxV = pv;
      }
      var w = maxU - minU, h = maxV - minV, area = w * h;
      if (!best || area < best.area - 1e-6) {
        var box = [
          { u: minU, v: minV }, { u: maxU, v: minV },
          { u: maxU, v: maxV }, { u: minU, v: maxV }
        ].map(function (c) {
          return { x: c.u * ux + c.v * vx, y: c.u * uy + c.v * vy };
        });
        best = {
          area: area, width: w, height: h,
          angle: Math.atan2(uy, ux), rotated: true, corners: box
        };
      }
    }
    // Konvention: width >= height
    if (best.height > best.width) {
      var t = best.width; best.width = best.height; best.height = t;
    }
    return best;
  }

  /* ---- Knotenketten / Цепочки узлов -------------------------------------- */

  // Kumulierte Knoten entlang einer Kante (y = const).
  function buildNodes(widths, y) {
    var nodes = [{ x: 0, y: y }];
    var acc = 0;
    for (var i = 0; i < widths.length; i++) {
      acc += Number(widths[i]) || 0;
      nodes.push({ x: acc, y: y });
    }
    return nodes;
  }

  /* ---- Kontrollmaße / Контрольные замеры --------------------------------- */
  // controls[i] kann enthalten: { bottom, top, left, right, diagAC, diagBD }
  function checkControls(glass, control, tol) {
    var checks = [];
    if (control) {
      var map = {
        bottom: glass.sides.bottom, top: glass.sides.top,
        left: glass.sides.left, right: glass.sides.right,
        diagAC: glass.diagonals.ac, diagBD: glass.diagonals.bd
      };
      Object.keys(map).forEach(function (key) {
        if (control[key] != null && control[key] !== '') {
          var measured = Number(control[key]);
          var computed = map[key];
          var dev = Math.abs(computed - measured);
          checks.push({
            name: key, computed: computed, measured: measured,
            deviation: dev, ok: dev <= tol
          });
        }
      });
    }
    var failed = checks.filter(function (c) { return !c.ok; });
    return {
      checks: checks,
      ok: failed.length === 0,
      status: checks.length === 0
        ? 'OHNE KONTROLLMASS'
        : (failed.length === 0 ? 'OK' : 'NACHMESSEN ERFORDERLICH'),
      statusRu: checks.length === 0
        ? 'БЕЗ КОНТРОЛЯ'
        : (failed.length === 0 ? 'ОК' : 'ТРЕБУЕТСЯ ПЕРЕМЕР')
    };
  }

  /* ---- Hauptberechnung / Основной расчёт --------------------------------- */
  //
  //  input = {
  //    H:        Number              — Höhe der Konstruktion (mm)
  //    bottom:   [Number, ...]       — Maße entlang der Unterkante
  //    top:      [Number, ...]       — Maße entlang der Oberkante
  //    controls: [{...}, ...]        — optionale Kontrollmaße je Glas
  //    options:  { measureTolerance, longSideFactor }
  //  }
  //
  function computePlan(input) {
    var H = Number(input.H);
    var bottom = (input.bottom || []).map(Number);
    var top = (input.top || []).map(Number);
    var controls = input.controls || [];
    var opt = Object.assign({}, DEFAULTS, input.options || {});

    if (!(H > 0)) throw new Error('Höhe H muss > 0 sein.');
    if (bottom.length === 0) throw new Error('Unterkante (bottom) ist leer.');
    if (bottom.length !== top.length) {
      throw new Error('bottom und top müssen gleich viele Felder haben (gleiche Anzahl Gläser).');
    }

    // 1) + 2) Knoten unten (y=0) und oben (y=H)
    var P = buildNodes(bottom, 0);
    var Q = buildNodes(top, H);

    var glasses = [];
    var n = bottom.length;

    for (var i = 0; i < n; i++) {
      // 3) Viereck A=Pi, B=Pi+1, C=Qi+1, D=Qi
      var A = P[i], B = P[i + 1], C = Q[i + 1], D = Q[i];
      var quad = [A, B, C, D];

      // 4) Seitenlängen per Abstandsformel
      var sides = {
        bottom: dist(A, B), // Unterkante
        right:  dist(B, C), // rechte Seite
        top:    dist(C, D), // Oberkante
        left:   dist(D, A)  // linke Seite
      };
      var diagonals = { ac: dist(A, C), bd: dist(B, D) };

      var sideValues = [sides.bottom, sides.top, sides.left, sides.right];
      var longSide = Math.max.apply(null, sideValues);

      // 6) Zuschnitt-Typ: normal vs. rotiertes MBR
      var threshold = opt.longSideFactor * H;
      var rotated = longSide > threshold;
      var blank = rotated ? minBoundingRect(quad) : boundingBox(quad);

      // 7) Flächen / Verschnitt
      var glassArea = polygonArea(quad);                 // mm²
      var blankArea = blank.width * blank.height;        // mm²
      var waste = blankArea > 0 ? (blankArea - glassArea) / blankArea * 100 : 0;

      var glass = {
        index: i + 1,
        quad: quad,
        nodes: { A: A, B: B, C: C, D: D },
        sides: sides,
        diagonals: diagonals,
        longSide: longSide,
        blankType: rotated ? 'MBR' : 'NORMAL',
        blank: blank,
        areas: {
          glass_mm2: glassArea,
          blank_mm2: blankArea,
          glass_m2: glassArea / 1e6,
          blank_m2: blankArea / 1e6
        },
        wastePercent: waste
      };

      // 5) Kontrollmaße prüfen
      glass.control = checkControls(glass, controls[i], opt.measureTolerance);

      glasses.push(glass);
    }

    // Summen
    var totalGlass = glasses.reduce(function (s, g) { return s + g.areas.glass_m2; }, 0);
    var totalBlank = glasses.reduce(function (s, g) { return s + g.areas.blank_m2; }, 0);

    return {
      H: H,
      options: opt,
      nodes: { bottom: P, top: Q },
      glasses: glasses,
      summary: {
        count: glasses.length,
        totalGlass_m2: totalGlass,
        totalBlank_m2: totalBlank,
        totalWastePercent: totalBlank > 0 ? (totalBlank - totalGlass) / totalBlank * 100 : 0,
        needsRemeasure: glasses.some(function (g) { return !g.control.ok; })
      }
    };
  }

  return {
    DEFAULTS: DEFAULTS,
    dist: dist,
    polygonArea: polygonArea,
    convexHull: convexHull,
    boundingBox: boundingBox,
    minBoundingRect: minBoundingRect,
    buildNodes: buildNodes,
    checkControls: checkControls,
    computePlan: computePlan
  };
}));
