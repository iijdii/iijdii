// Zeichnungs-Lightbox (Projekt-Tabs + Montage-Modus). Gemeinsames Modul,
// importiert in app.js UND montage.js — das Montage-Layout lädt app.js
// nicht. Alle 5 Ansichten sind server-gerendert im DOM; hier nur
// Sichtbarkeit, Prev/Next (modulo 5) und der „Maße"-Schalter (.nodim).
const lb = document.getElementById('roofLightbox');

if (lb) {
    const stages = [...lb.querySelectorAll('[data-roof-view]')];
    const thumbs = [...lb.querySelectorAll('[data-roof-thumb]')];
    const titel = lb.querySelector('[data-roof-title]');
    const dimsBtn = lb.querySelector('[data-roof-dims]');
    let idx = 0;
    let dims = true;

    const wendeDimsAn = () => {
        stages.forEach((s) => s.querySelector('.rd').classList.toggle('nodim', !dims));
        if (dimsBtn) dimsBtn.classList.toggle('on', dims);
    };
    const zeige = (i) => {
        idx = (i + stages.length) % stages.length;
        stages.forEach((s, j) => { s.hidden = j !== idx; });
        thumbs.forEach((t, j) => t.classList.toggle('on', j === idx));
        if (titel) titel.textContent = stages[idx].dataset.roofTitle;
    };

    document.addEventListener('click', (e) => {
        const opener = e.target.closest('[data-roof-open]');
        if (opener) {
            e.preventDefault();
            dims = true;
            wendeDimsAn();
            zeige(parseInt(opener.dataset.roofOpen, 10) || 0);
            lb.hidden = false;
            return;
        }
        if (e.target === lb || e.target.closest('[data-roof-close]')) {
            lb.hidden = true;
            return;
        }
        if (e.target.closest('[data-roof-prev]')) zeige(idx - 1);
        if (e.target.closest('[data-roof-next]')) zeige(idx + 1);
        const thumb = e.target.closest('[data-roof-thumb]');
        if (thumb) zeige(thumbs.indexOf(thumb));
        if (e.target.closest('[data-roof-dims]')) {
            dims = !dims;
            wendeDimsAn();
        }
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !lb.hidden) lb.hidden = true;
    });
}
