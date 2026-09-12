import './roof-lightbox.js';

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
    const out = {
        pn: pn || '–', rafters: rafters || '–', fields: fields || '–', rec: rec || '–',
        spar: spar ? de(spar) + ' mm' : '–',
        blende: de(blende) + ' mm', blende2: de(blende) + ' mm',
        glas: spar ? de(glasB) + ' × ' + de(glasT) + ' mm' + restText : '–',
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
