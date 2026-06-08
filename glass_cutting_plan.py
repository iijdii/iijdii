#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Glaszuschnittplan — CRM Modul für Glasproduktion"""

import math
import datetime
import os

# ── Geometrie ──────────────────────────────────────────────────────────────

def dist(p1, p2):
    return math.sqrt((p2[0]-p1[0])**2 + (p2[1]-p1[1])**2)

def shoelace(pts):
    n = len(pts)
    a = sum(pts[i][0]*pts[(i+1)%n][1] - pts[(i+1)%n][0]*pts[i][1] for i in range(n))
    return abs(a) / 2

def find_mbr(pts):
    """Minimum Bounding Rectangle (0.5°-Schritte, 0–179°)"""
    best_area = float('inf')
    best_wh = (0, 0)
    best_ang = 0
    for step in range(360):
        deg = step * 0.5
        rad = math.radians(deg)
        c, s = math.cos(rad), math.sin(rad)
        rot = [(p[0]*c + p[1]*s, -p[0]*s + p[1]*c) for p in pts]
        xs = [p[0] for p in rot]
        ys = [p[1] for p in rot]
        w = max(xs) - min(xs)
        h = max(ys) - min(ys)
        a = w * h
        if a < best_area:
            best_area = a
            best_wh = (w, h)
            best_ang = deg
    return best_wh, best_ang, best_area

def build_nodes(sizes, y):
    nodes = [(0, y)]
    x = 0
    for s in sizes:
        x += s
        nodes.append((x, y))
    return nodes

# ── Glasberechnung ─────────────────────────────────────────────────────────

def calc_glasses(H, bottom_sizes, top_sizes):
    bn = build_nodes(bottom_sizes, 0)
    tn = build_nodes(top_sizes, H)
    result = []

    for i in range(len(bottom_sizes)):
        A, B = bn[i], bn[i+1]
        D, C = tn[i], tn[i+1]
        pts = [A, B, C, D]

        bot  = round(dist(A, B), 1)
        top  = round(dist(D, C), 1)
        left = round(dist(A, D), 1)
        right= round(dist(B, C), 1)
        area = shoelace(pts)

        xs = [p[0] for p in pts]
        ys = [p[1] for p in pts]
        raw_bw = max(xs) - min(xs)
        raw_bh = max(ys) - min(ys)

        mbr_wh, mbr_ang, mbr_area = find_mbr(pts)
        bbox_area = raw_bw * raw_bh

        if mbr_area < bbox_area * 0.97:
            blank_w = math.ceil(mbr_wh[0]) + 10
            blank_h = math.ceil(mbr_wh[1]) + 10
            use_mbr = True
            ang = mbr_ang
        else:
            blank_w = math.ceil(raw_bw) + 10
            blank_h = math.ceil(raw_bh) + 10
            use_mbr = False
            ang = 0

        blank_area = blank_w * blank_h
        waste = (1 - area / blank_area) * 100

        result.append({
            'n': i+1, 'pts': pts,
            'A': A, 'B': B, 'C': C, 'D': D,
            'bot': bot, 'top': top, 'left': left, 'right': right,
            'area': area,
            'bw': blank_w, 'bh': blank_h, 'ba': blank_area,
            'waste': waste,
            'use_mbr': use_mbr, 'ang': ang,
            'raw_bw': raw_bw, 'raw_bh': raw_bh,
        })

    return result, bn, tn

# ── SVG: Einzelscheibe ──────────────────────────────────────────────────────

def _perp_out(p1, p2, cx, cy, offset):
    """Außenwärtiger Normalvektor, skaliert um offset"""
    dx, dy = p2[0]-p1[0], p2[1]-p1[1]
    nx, ny = -dy, dx
    ln = math.sqrt(nx**2+ny**2) or 1
    nx /= ln; ny /= ln
    mx = (p1[0]+p2[0])/2 - cx
    my = (p1[1]+p2[1])/2 - cy
    if nx*mx + ny*my < 0:
        nx, ny = -nx, -ny
    return ((p1[0]+p2[0])/2 + nx*offset, (p1[1]+p2[1])/2 + ny*offset)

def _side_angle(p1, p2):
    ang = math.degrees(math.atan2(p2[1]-p1[1], p2[0]-p1[0]))
    if ang < -90: ang += 180
    if ang >  90: ang -= 180
    return ang

def svg_glass(g, max_draw=280, margin=68):
    pts = g['pts']

    xs = [p[0] for p in pts]
    ys = [p[1] for p in pts]
    x0, y0 = min(xs), min(ys)
    local = [(p[0]-x0, p[1]-y0) for p in pts]
    lw = max(p[0] for p in local) or 1
    lh = max(p[1] for p in local) or 1

    scale = min(max_draw / lw, max_draw / lh)

    svg_w = lw*scale + 2*margin
    svg_h = lh*scale + 2*margin

    def to_s(p):
        return (margin + p[0]*scale, margin + (lh - p[1])*scale)

    sp = [to_s(p) for p in local]
    A, B, C, D = sp[0], sp[1], sp[2], sp[3]

    pts_str    = " ".join(f"{p[0]:.1f},{p[1]:.1f}" for p in sp)
    blank_pts  = [to_s((0,0)), to_s((lw,0)), to_s((lw,lh)), to_s((0,lh))]
    blank_str  = " ".join(f"{p[0]:.1f},{p[1]:.1f}" for p in blank_pts)

    cx = sum(p[0] for p in sp)/4
    cy = sum(p[1] for p in sp)/4

    loff = 24
    lb_pos = _perp_out(A, B, cx, cy, loff)
    lt_pos = _perp_out(D, C, cx, cy, loff)
    ll_pos = _perp_out(A, D, cx, cy, loff)
    lr_pos = _perp_out(B, C, cx, cy, loff)

    def txt(pos, text, angle=0, fs=10, color="#222", bold=False):
        x, y = pos
        fw = "bold" if bold else "normal"
        rot = f' transform="rotate({angle:.1f},{x:.1f},{y:.1f})"' if angle else ""
        return (f'<text x="{x:.1f}" y="{y:.1f}" text-anchor="middle" dominant-baseline="middle" '
                f'font-size="{fs}" font-family="Arial" fill="{color}" font-weight="{fw}"{rot}>'
                f'{text}</text>\n')

    def leader(p1, p2, lpos):
        mx, my = (p1[0]+p2[0])/2, (p1[1]+p2[1])/2
        return (f'<line x1="{mx:.1f}" y1="{my:.1f}" x2="{lpos[0]:.1f}" y2="{lpos[1]:.1f}" '
                f'stroke="#bbb" stroke-width="0.8"/>\n')

    buf = [f'<svg width="{svg_w:.0f}" height="{svg_h:.0f}" xmlns="http://www.w3.org/2000/svg">']
    buf.append(f'<rect width="{svg_w:.0f}" height="{svg_h:.0f}" fill="white"/>')

    # Rohling (grauer Strichrahmen)
    buf.append(f'<polygon points="{blank_str}" fill="#f0f0f0" fill-opacity="0.6" '
               f'stroke="#aaa" stroke-width="1.5" stroke-dasharray="8,5"/>')

    # Glasscheibe (blaue Füllung)
    buf.append(f'<polygon points="{pts_str}" fill="#4a90d9" fill-opacity="0.28" '
               f'stroke="#1a5fa8" stroke-width="2"/>')

    # Schnittlinien (orange gestrichelt)
    buf.append(f'<polygon points="{pts_str}" fill="none" '
               f'stroke="#e87722" stroke-width="1.5" stroke-dasharray="5,3"/>')

    # Diagonalen (Kontrollmaße)
    diag1 = dist(g['A'], g['C'])
    diag2 = dist(g['B'], g['D'])
    buf.append(f'<line x1="{A[0]:.1f}" y1="{A[1]:.1f}" x2="{C[0]:.1f}" y2="{C[1]:.1f}" '
               f'stroke="#ccc" stroke-width="0.6" stroke-dasharray="3,4"/>')
    buf.append(f'<line x1="{B[0]:.1f}" y1="{B[1]:.1f}" x2="{D[0]:.1f}" y2="{D[1]:.1f}" '
               f'stroke="#ccc" stroke-width="0.6" stroke-dasharray="3,4"/>')

    # Führungslinien zu Maßbeschriftungen
    buf.append(leader(A, B, lb_pos))
    buf.append(leader(D, C, lt_pos))
    buf.append(leader(A, D, ll_pos))
    buf.append(leader(B, C, lr_pos))

    # Maßbeschriftungen
    buf.append(txt(lb_pos, f"{g['bot']:.0f}", _side_angle(A,B), color="#c00", bold=True))
    buf.append(txt(lt_pos, f"{g['top']:.0f}", _side_angle(D,C), color="#c00", bold=True))
    buf.append(txt(ll_pos, f"{g['left']:.0f}", _side_angle(A,D), color="#1a5fa8", bold=True))
    buf.append(txt(lr_pos, f"{g['right']:.0f}", _side_angle(B,C), color="#1a5fa8", bold=True))

    # Diagonalen-Label
    dc = to_s(((local[0][0]+local[2][0])/2, (local[0][1]+local[2][1])/2))
    buf.append(txt(dc, f"d₁={diag1:.0f}", color="#999", fs=8))

    # Mittelpunkt: Glasnummer + Abfall
    buf.append(txt((cx, cy-14), f"Glas {g['n']}", fs=13, color="#c00", bold=True))
    buf.append(txt((cx, cy+2),  f"Fläche: {g['area']/1e6:.4f} m²", fs=9, color="#333"))
    buf.append(txt((cx, cy+15), f"Abfall: {g['waste']:.1f}%", fs=10,
                   color="#e87722" if g['waste']>40 else "#2a7a2a", bold=True))

    # Rohlingsgröße oben links
    buf.append(f'<text x="{margin:.0f}" y="{margin-10:.0f}" font-size="9" '
               f'font-family="Arial" fill="#666">'
               f'Rohling: {g["bw"]}×{g["bh"]} mm'
               + (f' [MBR {g["ang"]:.0f}°]' if g['use_mbr'] else '') + '</text>')

    buf.append('</svg>')
    return "\n".join(buf)

# ── SVG: Gesamtansicht ─────────────────────────────────────────────────────

def svg_overview(glasses, bn, tn, H):
    total_w = bn[-1][0]
    scale = min(680/total_w, 200/H)
    mg = 55

    svg_w = total_w*scale + 2*mg
    svg_h = H*scale + 2*mg + 30

    def to_s(p):
        return (mg + p[0]*scale, mg + (H-p[1])*scale)

    colors = ['#4a90d9','#5ba85e','#d9844a','#a84a4a','#7b4ad9','#4ad9c4']

    buf = [f'<svg width="{svg_w:.0f}" height="{svg_h:.0f}" xmlns="http://www.w3.org/2000/svg">',
           f'<rect width="{svg_w:.0f}" height="{svg_h:.0f}" fill="#f8f9fa"/>']

    for g in glasses:
        spts = [to_s(p) for p in g['pts']]
        pts_str = " ".join(f"{p[0]:.1f},{p[1]:.1f}" for p in spts)
        col = colors[(g['n']-1) % len(colors)]
        buf.append(f'<polygon points="{pts_str}" fill="{col}" fill-opacity="0.35" '
                   f'stroke="{col}" stroke-width="1.5"/>')
        cx = sum(p[0] for p in spts)/4
        cy = sum(p[1] for p in spts)/4
        buf.append(f'<text x="{cx:.1f}" y="{cy-6:.1f}" text-anchor="middle" '
                   f'font-size="12" font-family="Arial" fill="#222" font-weight="bold">Glas {g["n"]}</text>')
        buf.append(f'<text x="{cx:.1f}" y="{cy+9:.1f}" text-anchor="middle" '
                   f'font-size="9" font-family="Arial" fill="#555">'
                   f'{g["bw"]}×{g["bh"]}</text>')

    # Bodenknoten
    for i, p in enumerate(bn):
        sp = to_s(p)
        buf.append(f'<circle cx="{sp[0]:.1f}" cy="{sp[1]:.1f}" r="3.5" fill="#333"/>')
        buf.append(f'<text x="{sp[0]:.1f}" y="{sp[1]+16:.1f}" text-anchor="middle" '
                   f'font-size="9" font-family="Arial" fill="#333">P{i}</text>')

    # Deckenknoten (eng beieinander — gestaffelte Y-Labels)
    for i, p in enumerate(tn):
        sp = to_s(p)
        offset_y = -10 - (i % 3)*10
        buf.append(f'<circle cx="{sp[0]:.1f}" cy="{sp[1]:.1f}" r="3" fill="#666"/>')
        buf.append(f'<line x1="{sp[0]:.1f}" y1="{sp[1]:.1f}" '
                   f'x2="{sp[0]:.1f}" y2="{sp[1]+offset_y:.1f}" '
                   f'stroke="#999" stroke-width="0.7"/>')
        buf.append(f'<text x="{sp[0]:.1f}" y="{sp[1]+offset_y-4:.1f}" text-anchor="middle" '
                   f'font-size="8" font-family="Arial" fill="#555">Q{i}({p[0]:.0f})</text>')

    # Höhenpfeil
    p_bot = to_s(bn[0])
    p_top = to_s(tn[0])
    ax = mg - 18
    buf.append(f'<defs><marker id="arr" markerWidth="6" markerHeight="6" '
               f'refX="3" refY="3" orient="auto">'
               f'<path d="M0,0 L6,3 L0,6 Z" fill="#555"/></marker></defs>')
    buf.append(f'<line x1="{ax}" y1="{p_bot[1]:.1f}" x2="{ax}" y2="{p_top[1]:.1f}" '
               f'stroke="#555" stroke-width="1" marker-end="url(#arr)" marker-start="url(#arr)"/>')
    mid_y = (p_bot[1]+p_top[1])/2
    buf.append(f'<text x="{ax-5:.0f}" y="{mid_y:.1f}" text-anchor="middle" '
               f'font-size="10" font-family="Arial" fill="#555" '
               f'transform="rotate(-90,{ax-5:.0f},{mid_y:.1f})">H={H} mm</text>')

    # Gesamtbreite
    p_right = to_s(bn[-1])
    bottom_y = p_bot[1] + 22
    buf.append(f'<line x1="{p_bot[0]:.1f}" y1="{bottom_y:.1f}" '
               f'x2="{p_right[0]:.1f}" y2="{bottom_y:.1f}" '
               f'stroke="#555" stroke-width="1" marker-end="url(#arr)" marker-start="url(#arr)"/>')
    buf.append(f'<text x="{(p_bot[0]+p_right[0])/2:.1f}" y="{bottom_y+14:.1f}" '
               f'text-anchor="middle" font-size="10" font-family="Arial" fill="#555">'
               f'Gesamtbreite: {bn[-1][0]} mm</text>')

    buf.append('</svg>')
    return "\n".join(buf)

# ── HTML-Generator ─────────────────────────────────────────────────────────

def generate_html(H, bottom_sizes, top_sizes, glasses, bn, tn):
    today = datetime.date.today().strftime("%d.%m.%Y")

    overview = svg_overview(glasses, bn, tn, H)

    glass_cards = ""
    for g in glasses:
        svg = svg_glass(g)
        diag1 = dist(g['A'], g['C'])
        diag2 = dist(g['B'], g['D'])
        mbr_note = f"<br><span class='mbr-tag'>MBR {g['ang']:.0f}°</span>" if g['use_mbr'] else ""
        glass_cards += f"""
<div class="glass-card">
  <div class="glass-card-head">
    <span class="glas-nr">Glas {g['n']}</span>
    <span class="blank-size">Rohling: {g['bw']} × {g['bh']} mm{mbr_note}</span>
  </div>
  <div class="svg-wrap">{svg}</div>
  <div class="dims-row">
    <span>↔ Unten: <b>{g['bot']:.0f}</b></span>
    <span>↔ Oben: <b>{g['top']:.0f}</b></span>
    <span>↕ Links: <b>{g['left']:.0f}</b></span>
    <span>↕ Rechts: <b>{g['right']:.0f}</b></span>
    <span>d₁: {diag1:.0f}</span>
    <span>d₂: {diag2:.0f}</span>
  </div>
</div>"""

    # Tabelle
    rows = ""
    for g in glasses:
        diag1 = dist(g['A'], g['C'])
        diag2 = dist(g['B'], g['D'])
        wc = "waste-hi" if g['waste'] > 45 else ("waste-mid" if g['waste'] > 30 else "")
        mbr_tag = " <small>(MBR)</small>" if g['use_mbr'] else ""
        rows += f"""
<tr>
  <td class="tc"><b>Glas {g['n']}</b></td>
  <td class="tc">{g['bw']} × {g['bh']} mm{mbr_tag}</td>
  <td>
    Unten: {g['bot']:.0f} mm<br>
    Oben: {g['top']:.0f} mm<br>
    Links: {g['left']:.0f} mm<br>
    Rechts: {g['right']:.0f} mm
  </td>
  <td class="tc">d₁={diag1:.0f}<br>d₂={diag2:.0f}</td>
  <td class="tr">{g['area']/1e6:.4f} m²<br><small>{g['area']:.0f} mm²</small></td>
  <td class="tr">{g['ba']/1e6:.4f} m²<br><small>{g['ba']:.0f} mm²</small></td>
  <td class="tc {wc}">{g['waste']:.1f}%</td>
  <td class="tc ok">✓ OK</td>
</tr>"""

    total_glass = sum(g['area'] for g in glasses)
    total_blank = sum(g['ba'] for g in glasses)
    avg_waste = (1 - total_glass/total_blank)*100

    html = f"""<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Glaszuschnittplan</title>
<style>
*{{box-sizing:border-box;margin:0;padding:0}}
body{{font-family:Arial,sans-serif;font-size:12px;color:#222;background:#dde2ea}}
.page{{width:297mm;margin:8mm auto;background:#fff;padding:14mm 16mm 14mm;
       box-shadow:0 3px 18px rgba(0,0,0,.25)}}
/* ── Header ── */
.hdr{{border-bottom:3px solid #1a5fa8;padding-bottom:10px;margin-bottom:14px;
      display:flex;justify-content:space-between;align-items:flex-end}}
.hdr-left h1{{font-size:26px;color:#1a5fa8;letter-spacing:2px}}
.hdr-left .sub{{font-size:9.5px;color:#666;margin-top:3px;line-height:1.6}}
.hdr-right{{text-align:right;font-size:10px;color:#444;line-height:1.7}}
/* ── Input params ── */
.params{{display:flex;gap:18px;font-size:11px;padding:6px 12px;margin-bottom:12px;
         background:#eef2ff;border:1px solid #c0cef8;border-radius:4px}}
.params b{{color:#1a5fa8}}
/* ── Section titles ── */
.sec{{font-size:13px;font-weight:bold;color:#1a5fa8;border-left:4px solid #1a5fa8;
      padding-left:8px;margin:14px 0 8px}}
/* ── Overview ── */
.overview{{border:1px solid #ddd;border-radius:5px;background:#f8f9fa;
           padding:8px;overflow:hidden}}
.overview svg{{display:block;max-width:100%}}
/* ── Glass cards grid ── */
.cards{{display:grid;grid-template-columns:repeat(2,1fr);gap:14px;margin-bottom:18px}}
.glass-card{{border:1px solid #ccd;border-radius:6px;background:#fafbfd;
             padding:8px 10px;page-break-inside:avoid}}
.glass-card-head{{display:flex;justify-content:space-between;align-items:baseline;
                  margin-bottom:6px;border-bottom:1px solid #eee;padding-bottom:4px}}
.glas-nr{{font-size:13px;font-weight:bold;color:#c00}}
.blank-size{{font-size:10px;color:#555}}
.mbr-tag{{background:#ffe0a0;color:#a06000;border-radius:3px;
          padding:1px 4px;font-size:9px;font-weight:bold}}
.svg-wrap svg{{display:block;max-width:100%;height:auto}}
.dims-row{{display:flex;flex-wrap:wrap;gap:6px 14px;font-size:9.5px;
           color:#555;margin-top:5px;padding-top:4px;border-top:1px solid #eee}}
.dims-row b{{color:#c00}}
/* ── Table ── */
table.ct{{width:100%;border-collapse:collapse;font-size:11px;margin-top:8px}}
table.ct th{{background:#1a5fa8;color:#fff;padding:6px 8px;text-align:left;font-size:11px}}
table.ct td{{padding:5px 8px;border-bottom:1px solid #eee;vertical-align:top}}
table.ct tr:nth-child(even) td{{background:#f4f7ff}}
.tc{{text-align:center}}.tr{{text-align:right}}
.ok{{color:#2a7a2a;font-weight:bold}}
.warn{{color:#c06000;font-weight:bold}}
.waste-hi{{color:#c00;font-weight:bold}}
.waste-mid{{color:#b07000;font-weight:bold}}
.total-row td{{background:#e6ecfa!important;font-weight:bold;border-top:2px solid #1a5fa8}}
/* ── Legend ── */
.legend{{display:flex;gap:18px;font-size:10px;padding:5px 12px;
         background:#f7f7f7;border:1px solid #eee;border-radius:4px;margin-top:10px}}
.li{{display:flex;align-items:center;gap:6px}}
.lc{{width:26px;height:13px;border-radius:2px;display:inline-block}}
/* ── Footer ── */
.foot{{margin-top:14px;padding-top:8px;border-top:1px solid #ccc;
       font-size:9px;color:#888;display:flex;justify-content:space-between}}
/* ── Print ── */
@media print{{
  body{{background:#fff}}
  .page{{margin:0;padding:10mm;box-shadow:none;width:297mm}}
  @page{{size:A4 landscape;margin:10mm}}
  .glass-card{{page-break-inside:avoid}}
}}
</style>
</head>
<body>
<div class="page">

<!-- ─── Header ─── -->
<div class="hdr">
  <div class="hdr-left">
    <h1>Glaszuschnittplan</h1>
    <div class="sub">
      Alle Maße in mm &nbsp;|&nbsp;
      Zeichnung nicht maßstabsgetreu &nbsp;|&nbsp;
      Fertigung nach Maßangaben
    </div>
  </div>
  <div class="hdr-right">
    Datum: {today}<br>
    Höhe H: {H} mm<br>
    Anzahl Scheiben: {len(glasses)}
  </div>
</div>

<!-- ─── Eingabeparameter ─── -->
<div class="params">
  <div><b>Höhe H:</b> {H} mm</div>
  <div><b>Maße Unten:</b> {' + '.join(str(b) for b in bottom_sizes)} = {sum(bottom_sizes)} mm</div>
  <div><b>Maße Oben:</b> {' + '.join(str(t) for t in top_sizes)} = {sum(top_sizes)} mm</div>
</div>

<!-- ─── Gesamtansicht ─── -->
<div class="sec">Gesamtansicht der Konstruktion</div>
<div class="overview">{overview}</div>

<!-- ─── Einzelscheiben ─── -->
<div class="sec">Einzelne Glasscheiben — Zuschnittdetails</div>
<div class="cards">{glass_cards}</div>

<!-- ─── Zuschnittliste ─── -->
<div class="sec">Zuschnittliste</div>
<table class="ct">
  <thead>
    <tr>
      <th>Nr.</th>
      <th>Rohling (Zuschnitt)</th>
      <th>Maße der Seiten (mm)</th>
      <th>Diagonalen (mm)</th>
      <th>Glasfläche</th>
      <th>Rohlingfläche</th>
      <th>Abfall&nbsp;%</th>
      <th>Status</th>
    </tr>
  </thead>
  <tbody>
    {rows}
    <tr class="total-row">
      <td class="tc" colspan="4">GESAMT</td>
      <td class="tr">{total_glass/1e6:.4f} m²</td>
      <td class="tr">{total_blank/1e6:.4f} m²</td>
      <td class="tc">{avg_waste:.1f}%</td>
      <td class="tc">—</td>
    </tr>
  </tbody>
</table>

<!-- ─── Legende ─── -->
<div class="legend">
  <div class="li">
    <div class="lc" style="background:#4a90d9;opacity:.5;border:2px solid #1a5fa8"></div>
    <span>Glasscheibe</span>
  </div>
  <div class="li">
    <div class="lc" style="background:#f0f0f0;border:2px dashed #aaa"></div>
    <span>Rohling (Zuschnittrahmen)</span>
  </div>
  <div class="li">
    <div class="lc" style="background:#e87722;opacity:.8"></div>
    <span>Schnittlinien</span>
  </div>
  <div class="li">
    <div class="lc" style="background:#ccc;border:1px dashed #999"></div>
    <span>Kontrolldiagonalen</span>
  </div>
</div>

<!-- ─── Footer ─── -->
<div class="foot">
  <div>Glaszuschnittplan — Automatisch generiert vom CRM-Modul</div>
  <div>Alle Angaben in Millimeter (mm)</div>
  <div>Alle Maße vor der Fertigung prüfen!</div>
</div>

</div><!-- .page -->
</body>
</html>"""
    return html

# ── main ───────────────────────────────────────────────────────────────────

def main():
    H            = 2170
    bottom_sizes = [1370, 1280, 1080, 1070]
    top_sizes    = [55,   55,   215,  50  ]

    print("Berechne Glasscheiben …")
    glasses, bn, tn = calc_glasses(H, bottom_sizes, top_sizes)

    print(f"\n{'='*60}")
    print(f"  Konstruktionshöhe H = {H} mm")
    print(f"  Unten: {bottom_sizes}  Summe = {sum(bottom_sizes)} mm")
    print(f"  Oben:  {top_sizes}  Summe = {sum(top_sizes)} mm")
    print(f"{'='*60}")

    print(f"\nBodenknoten: {bn}")
    print(f"Deckenknoten: {tn}")

    for g in glasses:
        diag1 = dist(g['A'], g['C'])
        diag2 = dist(g['B'], g['D'])
        print(f"\n── Glas {g['n']} ──────────────────────────────")
        print(f"  A={g['A']}  B={g['B']}  C={g['C']}  D={g['D']}")
        print(f"  Seiten:  Unten={g['bot']:.1f}  Oben={g['top']:.1f}  "
              f"Links={g['left']:.1f}  Rechts={g['right']:.1f}  mm")
        print(f"  Diagonalen:  d1={diag1:.1f}  d2={diag2:.1f}  mm")
        print(f"  Fläche:     {g['area']:.0f} mm²  ({g['area']/1e6:.4f} m²)")
        print(f"  Rohling:    {g['bw']} × {g['bh']} mm  "
              f"{'[MBR ' + str(g['ang']) + '°]' if g['use_mbr'] else '[BBox]'}")
        print(f"  Rohlingfl.: {g['ba']:.0f} mm²  ({g['ba']/1e6:.4f} m²)")
        print(f"  Abfall:     {g['waste']:.1f}%")

    total_glass = sum(g['area'] for g in glasses)
    total_blank = sum(g['ba']   for g in glasses)
    print(f"\n{'='*60}")
    print(f"  Gesamt-Glasfläche:    {total_glass/1e6:.4f} m²")
    print(f"  Gesamt-Rohlingfläche: {total_blank/1e6:.4f} m²")
    print(f"  Ø Abfall:             {(1-total_glass/total_blank)*100:.1f}%")
    print(f"{'='*60}\n")

    html = generate_html(H, bottom_sizes, top_sizes, glasses, bn, tn)

    out = os.path.join(os.path.dirname(os.path.abspath(__file__)), "glaszuschnittplan.html")
    with open(out, "w", encoding="utf-8") as f:
        f.write(html)
    print(f"✓ HTML gespeichert: {out}")

if __name__ == "__main__":
    main()
