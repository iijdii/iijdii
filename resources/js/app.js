import './roof-lightbox.js';
import { initLedPlan } from './led-plan.js';

// LEA CRM — Navigations-Shell.
// >1200px klappt der Burger die Sidebar ein/aus; ≤1200px (Sidebar
// ausgeblendet) öffnet er das Dropdown-Panel. Schließt bei Auswahl
// und Klick auf den Backdrop.
const app = document.getElementById('app');
const btn = document.getElementById('navBtn');
const drop = document.getElementById('navDrop');
const backdrop = document.getElementById('navBackdrop');

const wide = () => window.matchMedia('(min-width: 1201px)').matches;

function closeDrop() {
    if (!drop) return;
    drop.hidden = true;
    backdrop.hidden = true;
    btn.classList.remove('on');
    btn.setAttribute('aria-expanded', 'false');
}

if (btn && app && drop && backdrop) {
    btn.addEventListener('click', () => {
        if (wide()) {
            app.classList.toggle('collapsed');
            return;
        }
        const open = drop.hidden;
        drop.hidden = !open;
        backdrop.hidden = !open;
        btn.classList.toggle('on', open);
        btn.setAttribute('aria-expanded', String(open));
    });
    backdrop.addEventListener('click', closeDrop);
    drop.addEventListener('click', (e) => {
        if (e.target.closest('a')) closeDrop();
    });
    window.addEventListener('resize', () => { if (wide()) closeDrop(); });
}

// ---------- Toasts ----------
// Echte Aktionen flashen session('toast') (Element [data-autotoast]);
// Stub-Buttons tragen data-toast und zeigen denselben Toast clientseitig.
function showToast(text) {
    document.querySelectorAll('.toast').forEach((t) => t.remove());
    const el = document.createElement('div');
    el.className = 'toast';
    el.textContent = text;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 2600);
}

const flashed = document.querySelector('[data-autotoast]');
if (flashed) setTimeout(() => flashed.remove(), 2600);

document.addEventListener('click', (e) => {
    const t = e.target.closest('[data-toast]');
    if (t) {
        e.preventDefault();
        showToast(t.dataset.toast);
    }
});

// ---------- Artikel-Modal ----------
// Zeilen/Karten tragen data-modal-url; das Fragment wird per fetch()
// in die Modal-Hülle geladen (ein Template für Lager und Katalog).
const modal = document.getElementById('modal');
const modalContent = document.getElementById('modalContent');

function closeModal() {
    if (!modal) return;
    modal.hidden = true;
    modalContent.innerHTML = '';
}

document.addEventListener('click', async (e) => {
    const row = e.target.closest('[data-modal-url]');
    if (row && modal && !e.target.closest('a,button,form')) {
        const res = await fetch(row.dataset.modalUrl, { headers: { 'X-Requested-With': 'fetch' } });
        if (res.ok) {
            modalContent.innerHTML = await res.text();
            modal.hidden = false;
        }
        return;
    }

    // Statische Modals (Konfigurator-Fenster): Markup liegt im DOM,
    // [data-modal-target] öffnet, Backdrop/[data-modal-close] schließt.
    const opener = e.target.closest('[data-modal-target]');
    if (opener) {
        e.preventDefault();
        const ziel = document.getElementById(opener.dataset.modalTarget);
        if (ziel) ziel.hidden = false;
        return;
    }
    if (e.target.closest('[data-modal-close]')) {
        e.target.closest('.modal').hidden = true;
        if (e.target.closest('.modal') === modal) closeModal();
        return;
    }
    if (e.target.classList && e.target.classList.contains('modal')) {
        e.target.hidden = true;
        if (e.target === modal) closeModal();
    }
});
document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    document.querySelectorAll('.modal:not([hidden])').forEach((m) => { m.hidden = true; });
    closeModal();
});

// ---------- Projektkarte: Tabs als Anker mit Scrollspy ----------
// Alle Bereiche stehen untereinander; die fixierten Tabs springen zur
// Sektion und markieren beim Scrollen den sichtbaren Bereich.
const prjTabs = document.querySelector('[data-prj-tabs]');
if (prjTabs) {
    const links = [...prjTabs.querySelectorAll('a[data-sek]')];
    const scroller = document.querySelector('main.content');
    const sektionen = links
        .map((a) => document.getElementById(a.dataset.sek))
        .filter(Boolean);

    const springe = (id, weich = true) => {
        const ziel = document.getElementById(id);
        if (ziel) ziel.scrollIntoView({ behavior: weich ? 'smooth' : 'auto', block: 'start' });
    };
    links.forEach((a) => a.addEventListener('click', (e) => {
        e.preventDefault();
        history.replaceState(null, '', '#' + a.dataset.sek);
        springe(a.dataset.sek);
    }));

    const markiere = () => {
        if (!scroller) return;
        const oben = scroller.getBoundingClientRect().top;
        let aktiv = sektionen[0];
        sektionen.forEach((s) => {
            if (s.getBoundingClientRect().top - oben <= 110) aktiv = s;
        });
        links.forEach((a) => a.classList.toggle('on', a.dataset.sek === aktiv?.id));
    };
    scroller?.addEventListener('scroll', markiere, { passive: true });
    markiere();

    // Einstieg über #sek-… oder alte ?tab=…-Links
    const start = (location.hash || '').replace('#', '')
        || (prjTabs.dataset.start !== 'uebersicht' ? 'sek-' + prjTabs.dataset.start : '');
    if (start && start !== 'sek-uebersicht') setTimeout(() => springe(start, false), 60);
}

// ---------- PDF-Vorschau ----------
// Links mit [data-pdf] öffnen das Dokument zuerst im Fenster (iframe,
// Server liefert mit ?ansicht=1 inline); Herunterladen/Drucken aus der
// Kopfleiste. href bleibt der Download-Link (Fallback ohne JS),
// data-pdf-ansicht kann eine eigene Inline-URL vorgeben (Dokumente-Tab).
document.addEventListener('click', (e) => {
    const link = e.target.closest('a[data-pdf]');
    if (!link || !modal) return;
    e.preventDefault();
    const download = link.getAttribute('href');
    const ansicht = link.dataset.pdfAnsicht
        || download + (download.includes('?') ? '&' : '?') + 'ansicht=1';

    // Mobile Browser (v. a. Chrome auf Android) haben keinen Inline-
    // PDF-Viewer — ein iframe bliebe leer. Dann direkt in neuem Tab
    // öffnen, dort übernimmt der System-Viewer bzw. der Download.
    const ohneInlineViewer = navigator.pdfViewerEnabled === false
        || /Android|iPhone|iPad|Mobile/i.test(navigator.userAgent);
    if (ohneInlineViewer) {
        window.open(ansicht, '_blank');
        return;
    }

    modalContent.innerHTML = `
        <div class="jb" style="padding:10px 14px;border-bottom:1px solid var(--line,#e2e5ea);gap:10px;flex-wrap:wrap">
            <b class="pdfv-titel" style="min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"></b>
            <span class="fx ac gap8">
                <button class="btn btns" type="button" data-pdf-drucken>Drucken</button>
                <a class="btn btns" href="${download}" download>Herunterladen</a>
                <button class="btn btns" type="button" data-modal-close aria-label="Schließen">✕</button>
            </span>
        </div>
        <iframe title="PDF-Vorschau" style="display:block;width:100%;height:min(78vh,900px);border:0;background:#525659"></iframe>`;
    modalContent.querySelector('.pdfv-titel').textContent = link.dataset.pdfTitel || 'PDF-Vorschau';
    modalContent.querySelector('iframe').src = ansicht;
    modal.hidden = false;
});
document.addEventListener('click', (e) => {
    if (!e.target.closest('[data-pdf-drucken]') || !modal) return;
    const frame = modal.querySelector('iframe');
    if (!frame) return;
    try {
        frame.contentWindow.focus();
        frame.contentWindow.print();
    } catch {
        window.open(frame.src, '_blank');
    }
});

// ---------- Live-Dach-Kalkulation (Konfigurator / Anfrage-Formular) ----------
// Progressive enhancement: die Formeln des Rechenkerns spiegeln, damit
// die kbox ohne Server-Roundtrip aktualisiert. Der POST bleibt Quelle
// der Wahrheit.
function kalkUpdate(scope) {
    const w = parseInt(scope.querySelector('[data-kalk="width"]')?.value, 10) || 0;
    const d = parseInt(scope.querySelector('[data-kalk="depth"]')?.value, 10) || 0;
    const postN = parseInt(scope.querySelector('[data-kalk="postN"]')?.value, 10) || 0;
    const fieldN = parseInt(scope.querySelector('[data-kalk="fieldN"]')?.value, 10) || 0;
    const ledSel = scope.querySelector('[data-kalk="ledTotal"]');
    const rec = w > 0 ? Math.ceil(w / 4000) + 1 : 0;
    const pn = postN > 0 ? postN : rec;
    // KD-Regeln: Achsmaß = (B − 60) / Felder; Eindeckung = Achsmaß − 22
    // (Glas max. 750, Poly-Stegplatte 980), Länge = T − 50.
    const coveringSel = scope.querySelector('[data-kalk="covering"]');
    const poly = !!(coveringSel && coveringSel.value.indexOf('Polycarbonat') === 0);
    const maxPlatte = poly ? 980 : 750;
    const nutz = Math.max(0, w - 60);
    // Poly: volle 1000er-Felder + Restfeld; Glas: Minimum unter 750.
    const volle = Math.floor(nutz / 1000);
    const rest = nutz - volle * 1000;
    const autoFields = nutz > 0 ? (poly ? Math.max(1, volle + (rest > 0 ? 1 : 0)) : Math.ceil(nutz / 772)) : 0;
    const fields = fieldN > 0 ? fieldN : autoFields;
    const rafters = fields > 0 ? fields + 1 : 0;
    let spar = fields > 0 ? Math.round(nutz / fields) : 0;
    let glasB = Math.max(0, spar - 22);
    let restText = '';
    if (poly && !(fieldN > 0) && fields > 0) {
        spar = Math.min(1000, spar);
        glasB = fields === 1 ? Math.max(0, nutz - 20) : 980;
        if (rest > 0 && fields > 1) restText = ' · Rest ' + Math.max(0, rest - 20).toLocaleString('de-DE');
    }
    const blende = Math.max(0, spar - 60);
    const glasT = Math.max(0, d - 50);
    // 0 = «Keine Beleuchtung» — die kbox zeigt dann keinen Spot-Wert.
    const ledWahl = ledSel ? parseInt(ledSel.value, 10) : 12;
    const ledTot = [0, 6].includes(ledWahl) ? ledWahl : 12;
    const de = (n) => n.toLocaleString('de-DE');

    // Pfosten-Positionen: manuelle CSV gewinnt, sonst symmetrisch verteilt.
    let posten = '–';
    if (w > 0 && pn > 1) {
        const manuell = (scope.querySelector('[data-kalk="postManual"]')?.value ?? '').trim();
        const liste = manuell
            ? manuell.split(',').map((s) => parseInt(s, 10)).filter((n) => !Number.isNaN(n))
            : Array.from({ length: pn }, (_, i) => Math.round(i * w / (pn - 1)));
        posten = liste.map(de).join(' · ');
    }

    // Profilsegmente: manuelle CSV gewinnt, sonst Teilung bei max. 7.500 mm.
    let segmente = '–';
    if (w > 0) {
        const manuellProfil = (scope.querySelector('[data-kalk="profilListe"]')?.value ?? '').trim();
        const profilManuell = scope.querySelector('[data-dach-profilmanuell]')?.checked && manuellProfil !== '';
        let teile;
        if (profilManuell) {
            teile = manuellProfil.split(',').map((s) => parseInt(s, 10)).filter((n) => n > 0);
        } else {
            const anzahl = Math.max(1, Math.ceil(w / 7500));
            teile = Array.from({ length: anzahl }, (_, i) => (i < anzahl - 1 ? 7500 : w - (anzahl - 1) * 7500));
        }
        segmente = teile.length + ' × (' + teile.map(de).join(' + ') + ' mm)';
    }

    const out = {
        pn: pn || '–', rafters: rafters || '–', fields: fields || '–', rec: rec || '–',
        spar: spar ? de(spar) + ' mm' : '–',
        blende: de(blende) + ' mm', blende2: de(blende) + ' mm',
        glas: spar ? de(glasB) + ' × ' + de(glasT) + ' mm' + restText : '–',
        posten: posten, segmente: segmente,
        ledTot: ledTot === 0 ? 'Keine' : ledTot, ledTot2: ledTot === 0 ? 'Keine' : ledTot,
    };
    scope.querySelectorAll('[data-kalk-out]').forEach((el) => {
        const key = el.dataset.kalkOut;
        if (key in out) el.textContent = out[key];
    });
    const warn = scope.querySelector('[data-kalk-warn="glas"]');
    if (warn) warn.hidden = glasB <= maxPlatte;
}

// Jede Konfigurator-Instanz (Seite + Modals) rechnet für sich —
// [data-kalk-scope] kapselt Eingaben und Ausgaben zusammen.
const kalkScopes = document.querySelectorAll('[data-kalk-scope]').length
    ? [...document.querySelectorAll('[data-kalk-scope]')]
    : (document.querySelector('[data-kalk]') ? [document] : []);
kalkScopes.forEach((scope) => {
    scope.querySelectorAll('[data-kalk]').forEach((el) => {
        el.addEventListener('input', () => kalkUpdate(scope));
        el.addEventListener('change', () => kalkUpdate(scope));
    });
    if (scope !== document) kalkUpdate(scope);
});

// LED-Plan der Projekt-Übersicht: gleiches Pickermodal wie im
// Montage-Modus (der Verkäufer setzt die Lampen im Büro).
initLedPlan(showToast);

// Foto-Vorschau (Projekt-Tab «Fotos»): Klick auf eine Miniatur öffnet
// das Bild groß im Fenster.
document.addEventListener('click', (e) => {
    const thumb = e.target.closest('[data-foto-preview]');
    if (!thumb) return;
    const modal = document.getElementById('fotoModal');
    if (!modal) return;
    document.getElementById('fotoModalImg').src = thumb.dataset.fotoPreview;
    document.getElementById('fotoModalName').textContent = thumb.dataset.fotoName || 'Foto';
    modal.hidden = false;
});

// Anfrage-Formular: Kurzfassung der Konfiguration neben dem
// «Konfigurator öffnen»-Knopf pflegen (Fenster-Felder senden mit dem Formular).
const konfigModal = document.getElementById('konfigurator-modal');
if (konfigModal) {
    const feld = (key) => document.querySelector(`[data-konfig-summary="${key}"]`);
    const de = (n) => n.toLocaleString('de-DE');
    const update = () => {
        const sel = konfigModal.querySelector('[data-pos-produkt]');
        const opt = sel && sel.selectedOptions[0];
        const dach = !!(opt && opt.dataset.dach === '1');
        if (feld('produkt')) feld('produkt').textContent = opt ? opt.textContent.trim() : '–';
        const w = parseInt(konfigModal.querySelector('[data-kalk="width"]')?.value, 10) || 0;
        const d = parseInt(konfigModal.querySelector('[data-kalk="depth"]')?.value, 10) || 0;
        if (feld('masse')) feld('masse').textContent = dach ? (w && d ? `${de(w)} × ${de(d)} mm` : '–') : 'siehe Konfigurator';
        if (feld('glas')) feld('glas').textContent = dach ? (konfigModal.querySelector('[data-kalk-out="glas"]')?.textContent ?? '–') : '–';
    };
    konfigModal.addEventListener('input', update);
    konfigModal.addEventListener('change', update);
    update();
}

// Kunden-Formular: Felder folgen dem Typ (privat ↔ gewerbe), der
// Anzeigename füllt sich automatisch aus Anrede/Name bzw. Firma —
// bis der Benutzer ihn selbst überschreibt. Server rechnet dieselbe
// Regel nach, falls das Feld leer bleibt.
document.querySelectorAll('[data-kunde-form]').forEach((karte) => {
    const typ = karte.querySelector('[data-kunde-typ]');
    const anzeige = karte.querySelector('[data-kunde-anzeigename]');
    if (!typ || !anzeige) return;
    const feld = (name) => karte.querySelector(`[name="${name}"]`);
    const autoName = () => {
        if (typ.value === 'gewerbe') {
            return (feld('firma').value || feld('ansprechpartner').value).trim();
        }
        const nachname = feld('nachname').value.trim();
        if (feld('anrede').value === 'Familie') return nachname ? 'Familie ' + nachname : '';
        return (feld('vorname').value.trim() + ' ' + nachname).trim();
    };
    let manuell = anzeige.value !== '' && anzeige.value !== autoName();

    const update = () => {
        karte.querySelectorAll('[data-kunde-nur]').forEach((f) => {
            const aktiv = f.dataset.kundeNur === typ.value;
            f.hidden = !aktiv;
            f.querySelectorAll('input,select').forEach((el) => { el.disabled = !aktiv; });
        });
        if (!manuell) anzeige.value = autoName();
    };
    typ.addEventListener('change', update);
    karte.querySelectorAll('[data-kunde-name]').forEach((el) => {
        el.addEventListener('input', () => { if (!manuell) anzeige.value = autoName(); });
        el.addEventListener('change', () => { if (!manuell) anzeige.value = autoName(); });
    });
    anzeige.addEventListener('input', () => { manuell = anzeige.value !== ''; });
    update();
});

// Dach-Feinlogik im Konfigurator: Teilbereiche folgen den Steuer-Selects
// (Form, Montageart, Isolierung, Unterzug, Einzel-Befestigung), die
// Neigung errechnet sich aus den Höhen, der Dübel-Vorschlag folgt Belag
// und Isolierstärke, die Einzel-Befestigung rendert ein Select je Pfosten.
document.querySelectorAll('[data-produkt-felder="dach"]').forEach((dach) => {
    const feld = (sel) => dach.querySelector(sel);
    const shape = feld('[data-dach-shape]');
    const mounting = feld('[data-dach-mounting]');
    const isolierung = feld('[data-dach-isolierung]');
    const unterzug = feld('[data-dach-unterzug]');
    const montageJe = feld('[data-dach-montageje]');
    const postAdv = feld('[data-dach-postadv]');
    const profilManuell = feld('[data-dach-profilmanuell]');
    if (!shape || !mounting) return;

    const sichtbar = () => {
        const wand = mounting.value === 'an der Wand';
        const zustand = {
            trapez: shape.value === 'trapez',
            wandmontage: wand,
            isolierung: wand && isolierung && isolierung.value === 'ja',
            unterzug: unterzug && unterzug.value === 'ja',
            montageje: !!(montageJe && montageJe.checked),
            postadv: !!(postAdv && postAdv.checked),
            profil: !!(profilManuell && profilManuell.checked),
        };
        dach.querySelectorAll('[data-dach-nur]').forEach((block) => {
            const aktiv = !!zustand[block.dataset.dachNur];
            block.hidden = !aktiv;
            block.querySelectorAll('input,select').forEach((el) => { el.disabled = !aktiv; });
        });
    };

    // Neigung aus beiden Höhen und der Tiefe (atan, wie der Rechenkern).
    const slope = feld('[data-dach-slope]');
    const neigung = () => {
        const wallH = parseInt(feld('[data-dach-hoehe="wand"]')?.value, 10) || 0;
        const gutterH = parseInt(feld('[data-dach-hoehe="rinne"]')?.value, 10) || 0;
        const tiefe = parseInt(dach.querySelector('[data-kalk="depth"]')?.value, 10) || 0;
        if (slope && wallH > 0 && gutterH > 0 && tiefe > 0) {
            slope.value = Math.round(Math.atan(Math.abs(wallH - gutterH) / tiefe) * 180 / Math.PI * 10) / 10;
        }
    };

    // Dübel-Vorschlag: Isolierung → Abstandsmontage mit längerem Anker,
    // sonst nach Wandbelag. Der Vorschlag bleibt überschreibbar.
    const duebelVorschlag = () => {
        const typ = feld('[data-dach-duebeltyp]');
        const groesse = feld('[data-dach-duebelsize]');
        if (!typ || !groesse) return;
        if (isolierung && isolierung.value === 'ja') {
            const dicke = parseInt(feld('[data-dach-daemmung]')?.value, 10) || 0;
            typ.value = 'Injektionsanker';
            groesse.value = '12 × ' + (100 + dicke) + ' mm (Abstandsmontage)';
            return;
        }
        const je = { Putz: ['Schlagdübel', '10 × 100 mm'], Klinker: ['Injektionsanker', '10 × 100 mm'], Holz: ['Stockschrauben', '12 × 120 mm'] };
        const wahl = je[feld('[data-dach-belag]')?.value] ?? je.Putz;
        [typ.value, groesse.value] = wahl;
    };

    // Einzel-Befestigung: ein Select je Pfosten (Anzahl folgt der Kalkulation).
    const listeBox = feld('[data-dach-montageliste]');
    const montageliste = () => {
        if (!listeBox || listeBox.hidden) return;
        const w = parseInt(dach.querySelector('[data-kalk="width"]')?.value, 10) || 0;
        const postN = parseInt(dach.querySelector('[data-kalk="postN"]')?.value, 10) || 0;
        const pn = Math.min(12, postN > 0 ? postN : (w > 0 ? Math.ceil(w / 4000) + 1 : 0));
        const werte = [...listeBox.querySelectorAll('select')].map((s) => s.value);
        if (werte.length === 0) werte.push(...JSON.parse(listeBox.dataset.dachMontagelisteWerte || '[]'));
        listeBox.querySelectorAll('.pmrow').forEach((r) => r.remove());
        for (let i = 0; i < pn; i++) {
            const zeile = document.createElement('div');
            zeile.className = 'pmrow fx ac gap8';
            zeile.style.marginTop = '6px';
            const label = document.createElement('span');
            label.className = 'hint';
            label.style.width = '90px';
            label.textContent = 'Pfosten ' + (i + 1);
            const select = document.createElement('select');
            select.className = 'inp';
            select.name = listeBox.dataset.dachMontagelisteName + '[]';
            ['Beton', 'U-Profil', 'Pfostenhalter'].forEach((option) => {
                const o = document.createElement('option');
                o.value = option;
                o.textContent = option === 'Pfostenhalter' ? 'Pfostenhalter (Konsole)' : option;
                if ((werte[i] ?? 'Beton') === option) o.selected = true;
                select.append(o);
            });
            zeile.append(label, select);
            listeBox.append(zeile);
        }
    };

    [shape, mounting, isolierung, unterzug].forEach((el) => el && el.addEventListener('change', () => { sichtbar(); duebelVorschlag(); }));
    montageJe && montageJe.addEventListener('change', () => { sichtbar(); montageliste(); });
    [postAdv, profilManuell].forEach((el) => el && el.addEventListener('change', () => {
        sichtbar();
        kalkUpdate(dach.closest('[data-kalk-scope]') ?? dach);
    }));
    [feld('[data-dach-belag]'), feld('[data-dach-daemmung]')].forEach((el) => {
        el && el.addEventListener('change', duebelVorschlag);
        el && el.addEventListener('input', duebelVorschlag);
    });
    dach.querySelectorAll('[data-dach-hoehe], [data-kalk="depth"]').forEach((el) => el.addEventListener('input', neigung));
    dach.querySelectorAll('[data-kalk="width"], [data-kalk="postN"]').forEach((el) => el.addEventListener('input', montageliste));
    // Der Produkt-Umschalter aktiviert beim Wechsel ALLE Dach-Eingaben —
    // danach die Teilbereich-Sichtbarkeit erneut anwenden.
    dach.closest('[data-position-form]')?.querySelector('[data-pos-produkt]')
        ?.addEventListener('change', () => setTimeout(sichtbar, 0));
    sichtbar();
    montageliste();
});

// Produkt-Positions-Formular (Einheitssystem): Feldblöcke folgen dem
// Produkt-Select. Ohne JS bleiben alle Blöcke sichtbar.
document.querySelectorAll('[data-position-form]').forEach((form) => {
    const select = form.querySelector('[data-pos-produkt]');
    if (!select) return;
    const update = () => {
        const opt = select.selectedOptions[0];
        const key = opt && opt.dataset.dach === '1' ? 'dach' : select.value;
        form.querySelectorAll('[data-produkt-felder]').forEach((block) => {
            const aktiv = block.dataset.produktFelder === key;
            block.hidden = !aktiv;
            // Versteckte Blöcke dürfen nicht mitsenden — gemeinsame Feldnamen
            // (z. B. anzahl) würden sonst die gewählten Werte überschreiben.
            block.querySelectorAll('input,select').forEach((el) => { el.disabled = !aktiv; });
        });
    };
    select.addEventListener('change', update);
    update();
});
