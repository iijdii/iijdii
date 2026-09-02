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

if (modal) {
    document.addEventListener('click', async (e) => {
        const row = e.target.closest('[data-modal-url]');
        if (row && !e.target.closest('a,button,form')) {
            const res = await fetch(row.dataset.modalUrl, { headers: { 'X-Requested-With': 'fetch' } });
            if (res.ok) {
                modalContent.innerHTML = await res.text();
                modal.hidden = false;
            }
            return;
        }
        if (e.target === modal || e.target.closest('[data-modal-close]')) closeModal();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeModal();
    });
}

// ---------- Live-Dach-Kalkulation (Konfigurator / Anfrage-Formular) ----------
// Progressive enhancement: die Formeln des Rechenkerns spiegeln, damit
// die kbox ohne Server-Roundtrip aktualisiert. Der POST bleibt Quelle
// der Wahrheit.
function kalkUpdate() {
    const w = parseInt(document.querySelector('[data-kalk="width"]')?.value, 10) || 0;
    const postN = parseInt(document.querySelector('[data-kalk="postN"]')?.value, 10) || 0;
    const ledSel = document.querySelector('[data-kalk="ledTotal"]');
    const rec = w > 0 ? Math.ceil(w / 4000) + 1 : 0;
    const pn = postN > 0 ? postN : rec;
    const rafters = w > 0 ? Math.round(w / 1080) + 1 : 0;
    const fields = Math.max(0, rafters - 1);
    const spar = fields > 0 ? Math.round(w / fields) : 0;
    const blende = Math.max(0, spar - 60);
    const ledTot = ledSel && parseInt(ledSel.value, 10) === 6 ? 6 : 12;
    const de = (n) => n.toLocaleString('de-DE');
    const out = {
        pn: pn || '–', rafters: rafters || '–', fields: fields || '–', rec: rec || '–',
        spar: spar ? de(spar) + ' mm' : '–',
        blende: de(blende) + ' mm', blende2: de(blende) + ' mm',
        ledTot: ledTot, ledTot2: ledTot,
    };
    document.querySelectorAll('[data-kalk-out]').forEach((el) => {
        const key = el.dataset.kalkOut;
        if (key in out) el.textContent = out[key];
    });
}

if (document.querySelector('[data-kalk]')) {
    document.querySelectorAll('[data-kalk]').forEach((el) => {
        el.addEventListener('input', kalkUpdate);
        el.addEventListener('change', kalkUpdate);
    });
}
