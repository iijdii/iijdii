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
