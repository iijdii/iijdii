// LED-Pickermodal — geteilt zwischen Projekt-Übersicht (app.js) und
// Montage-Modus (montage.js). Lampen setzen OHNE Neuladen: der Klick
// sendet das Formular per fetch im Hintergrund, das Fenster bleibt
// sicher offen. Erst beim Schließen lädt die Seite einmal neu und zieht
// Zeichnung/Kennzahlen nach. Ohne JS greift POST/Redirect (?led=offen).
export function initLedPlan(showToast) {
    const ledModal = document.getElementById('ledModal');
    if (!ledModal) return;

    let ledGeaendert = false;
    const ledTotal = parseInt(ledModal.dataset.ledTotal, 10) || 0;

    const zaehlerSync = () => {
        const an = ledModal.querySelectorAll('.lampb.on').length;
        ledModal.querySelectorAll('[data-led-count]').forEach((el) => { el.textContent = an; });
        const voll = ledTotal > 0 && an >= ledTotal;
        ledModal.querySelectorAll('.lampb:not(.on)').forEach((b) => b.classList.toggle('lock', voll));
    };

    // URL ohne den led-Parameter (übrige Parameter wie ?tab= bleiben).
    const urlOhneLed = (hash) => {
        const params = new URLSearchParams(window.location.search);
        params.delete('led');
        const q = params.toString();
        return window.location.pathname + (q ? '?' + q : '') + hash;
    };

    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-led-modal-open]')) ledModal.hidden = false;
        if (e.target.closest('[data-led-modal-close]') || e.target === ledModal) {
            if (ledGeaendert) {
                // Zeichnung und Kennzahlen mit dem neuen Stand rendern —
                // ohne ?led=offen, damit das Fenster danach zu bleibt.
                window.history.replaceState(null, '', urlOhneLed(''));
                window.location.reload();
                return;
            }
            ledModal.hidden = true;
            if (new URLSearchParams(window.location.search).has('led')) {
                window.history.replaceState(null, '', urlOhneLed(''));
            }
        }
    });

    ledModal.addEventListener('submit', async (e) => {
        const form = e.target;
        const lampe = form.querySelector('.lampb');
        if (!lampe) return; // Reset-Formular klassisch absenden
        e.preventDefault();
        if (!lampe.classList.contains('on') && ledTotal > 0
            && ledModal.querySelectorAll('.lampb.on').length >= ledTotal) {
            showToast('Laut Konfiguration sind nur ' + ledTotal + ' Spots vorgesehen');
            return;
        }
        try {
            const res = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { 'X-Requested-With': 'fetch' },
            });
            if (!res.ok) throw new Error(String(res.status));
            lampe.classList.toggle('on');
            ledGeaendert = true;
            zaehlerSync();
        } catch {
            form.submit(); // Fallback: klassischer POST mit Neuladen
        }
    });

    // Fallback-Pfad (kein JS beim Klick / fetch fehlgeschlagen): nach dem
    // Redirect mit ?led=offen das Fenster direkt wieder öffnen.
    if (new URLSearchParams(window.location.search).get('led') === 'offen') {
        ledModal.hidden = false;
    }
}
