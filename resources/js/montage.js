// Montage-Modus + Abnahmeprotokoll — eigener Bundle-Eintrag (das
// Montage-Layout lädt app.js nicht). Progressive enhancement: alle
// Aktionen funktionieren auch ohne JS über POST/Redirect.

// ---------- Toasts ----------
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

// ---------- LED-Modal ----------
const ledModal = document.getElementById('ledModal');
if (ledModal) {
    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-led-modal-open]')) ledModal.hidden = false;
        if (e.target.closest('[data-led-modal-close]') || e.target === ledModal) ledModal.hidden = true;
    });
}

// ---------- Radiopills (Notiz-Typ) ----------
document.querySelectorAll('[data-radiopill] input').forEach((input) => {
    input.addEventListener('change', () => {
        input.closest('.radiorow').querySelectorAll('[data-radiopill]').forEach((p) => p.classList.remove('on'));
        input.closest('[data-radiopill]').classList.add('on');
    });
});

// ---------- Live-Δ der Endmaße ----------
// Server bleibt Quelle der Wahrheit; hier nur Sofort-Feedback beim Tippen.
const aufmassForm = document.getElementById('aufmass-form');
if (aufmassForm) {
    const gruen = parseInt(aufmassForm.dataset.tolGruen, 10) || 5;
    const gelb = parseInt(aufmassForm.dataset.tolGelb, 10) || 15;
    aufmassForm.querySelectorAll('.ex-f').forEach((row) => {
        const input = row.querySelector('.ex-in');
        const badge = row.querySelector('.ex-d');
        const soll = parseFloat(row.dataset.soll);
        if (!input || !badge || !isFinite(soll)) return;
        input.addEventListener('input', () => {
            const ist = parseFloat(String(input.value).replace(',', '.'));
            row.classList.remove('ok', 'warn', 'bad');
            badge.classList.remove('b-green', 'b-yellow', 'b-red');
            if (input.value === '' || !isFinite(ist)) {
                badge.textContent = '—';
                return;
            }
            const d = Math.round(ist - soll);
            const ad = Math.abs(d);
            const stufe = ad <= gruen ? 'ok' : ad <= gelb ? 'warn' : 'bad';
            row.classList.add(stufe);
            badge.classList.add(stufe === 'ok' ? 'b-green' : stufe === 'warn' ? 'b-yellow' : 'b-red');
            badge.textContent = (d > 0 ? '+' : '') + d + ' mm';
        });
    });
}

// ---------- Unterschriften-Pads (Abnahmeprotokoll) ----------
// devicePixelRatio-skaliert, 2.4px runde Kappe #1B1F23, Pointer-Capture,
// Ergebnis als PNG-dataURL in hidden inputs.
document.querySelectorAll('[data-sigpad]').forEach((wrap) => {
    const canvas = wrap.querySelector('canvas');
    const hidden = document.querySelector(wrap.dataset.sigpadInput);
    const placeholder = wrap.querySelector('.sigph');
    if (!canvas || !hidden) return;

    const rect = wrap.getBoundingClientRect();
    const dpr = window.devicePixelRatio || 1;
    canvas.width = Math.round(rect.width * dpr);
    canvas.height = Math.round(rect.height * dpr);
    const ctx = canvas.getContext('2d');
    ctx.scale(dpr, dpr);
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.lineWidth = 2.4;
    ctx.strokeStyle = '#1B1F23';

    let drawing = false;
    const pos = (e) => {
        const r = canvas.getBoundingClientRect();
        return [e.clientX - r.left, e.clientY - r.top];
    };
    canvas.addEventListener('pointerdown', (e) => {
        e.preventDefault();
        try {
            canvas.setPointerCapture(e.pointerId);
        } catch { /* optional */ }
        drawing = true;
        const [x, y] = pos(e);
        ctx.beginPath();
        ctx.moveTo(x, y);
        if (placeholder) placeholder.hidden = true;
    });
    canvas.addEventListener('pointermove', (e) => {
        if (!drawing) return;
        const [x, y] = pos(e);
        ctx.lineTo(x, y);
        ctx.stroke();
        hidden.value = canvas.toDataURL('image/png');
    });
    ['pointerup', 'pointerleave'].forEach((ev) => canvas.addEventListener(ev, () => {
        drawing = false;
    }));

    const clearBtn = document.querySelector(wrap.dataset.sigpadClear);
    if (clearBtn) {
        clearBtn.addEventListener('click', () => {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            hidden.value = '';
            if (placeholder) placeholder.hidden = false;
        });
    }
});

// ---------- Mängel-Zeilen + Erklärungsoptionen (Abnahmeprotokoll) ----------
const aprForm = document.getElementById('abnahme-form');
if (aprForm) {
    const maengelWrap = document.getElementById('maengelRows');
    const maengelSection = document.getElementById('maengelSection');
    let idx = maengelWrap ? maengelWrap.children.length : 0;

    const addRow = (text = '', frist = '') => {
        const row = document.createElement('div');
        row.className = 'apr-mrow';
        row.innerHTML = '<span class="apr-mn">' + (maengelWrap.children.length + 1) + '</span>'
            + '<input class="inp" name="maengel[' + idx + '][text]" placeholder="Beschreibung der Beanstandung" value="' + text + '">'
            + '<input class="inp" name="maengel[' + idx + '][frist]" placeholder="Frist" value="' + (frist || aprForm.dataset.defaultFrist || '') + '">'
            + '<button class="apr-del" type="button" aria-label="Entfernen">✕</button>';
        row.querySelector('.apr-del').addEventListener('click', () => {
            row.remove();
            [...maengelWrap.children].forEach((r, i) => {
                r.querySelector('.apr-mn').textContent = i + 1;
            });
        });
        maengelWrap.appendChild(row);
        idx += 1;
    };

    const addBtn = document.getElementById('maengelAdd');
    if (addBtn) addBtn.addEventListener('click', () => addRow());

    aprForm.querySelectorAll('[data-apr-opt]').forEach((opt) => {
        opt.addEventListener('click', () => {
            aprForm.querySelectorAll('[data-apr-opt]').forEach((o) => o.classList.remove('on'));
            opt.classList.add('on');
            opt.querySelector('input').checked = true;
            const art = opt.querySelector('input').value;
            const zeigen = art !== 'ohne';
            if (maengelSection) maengelSection.hidden = !zeigen;
            if (zeigen && maengelWrap && maengelWrap.children.length === 0) addRow();
        });
    });
}
