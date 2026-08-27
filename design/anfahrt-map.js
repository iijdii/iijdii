(function () {
  const CSS = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
  const JS = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
  let loading = null;

  function loadLeaflet() {
    if (window.L) return Promise.resolve(window.L);
    if (loading) return loading;
    loading = new Promise((resolve, reject) => {
      if (!document.querySelector('link[data-leaflet]')) {
        const l = document.createElement('link');
        l.rel = 'stylesheet';
        l.href = CSS;
        l.integrity = 'sha384-sHL9NAb7lN7rfvG5lfHpm643Xkcjzp4jFvuavGOndn6pjVqS6ny56CAt3nsEVT4H';
        l.crossOrigin = 'anonymous';
        l.setAttribute('data-leaflet', '1');
        document.head.appendChild(l);
      }
      const s = document.createElement('script');
      s.src = JS;
      s.integrity = 'sha384-cxOPjt7s7Iz04uaHJceBmS+qpjv2JkIHNVcuOrM+YHwZOmJGBXI00mdUXEq65HTH';
      s.crossOrigin = 'anonymous';
      s.onload = () => resolve(window.L);
      s.onerror = reject;
      document.head.appendChild(s);
    });
    return loading;
  }

  class AnfahrtMap extends HTMLElement {
    connectedCallback() {
      if (this._built) return;
      this._built = true;
      this.style.display = 'block';
      this.style.width = '100%';
      this.style.height = '100%';
      this.style.position = 'relative';
      const box = document.createElement('div');
      box.style.cssText = 'position:absolute;inset:0;background:#eceef1';
      this.appendChild(box);

      const lat = parseFloat(this.getAttribute('lat')) || 52.5322;
      const lon = parseFloat(this.getAttribute('lon')) || 13.3846;
      const zoom = parseInt(this.getAttribute('zoom'), 10) || 15;
      const label = this.getAttribute('label') || '';

      loadLeaflet().then((L) => {
        if (!this.isConnected) return;
        const map = L.map(box, {
          center: [lat, lon],
          zoom: zoom,
          zoomControl: true,
          attributionControl: true,
          scrollWheelZoom: false
        });
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
          maxZoom: 19,
          attribution: '© OpenStreetMap contributors'
        }).addTo(map);
        const icon = L.divIcon({
          className: '',
          html: '<div style="width:26px;height:26px;border-radius:50% 50% 50% 2px;transform:rotate(-45deg);background:#1B1F23;border:2.5px solid #fff;box-shadow:0 3px 10px rgba(0,0,0,.35)"></div>',
          iconSize: [26, 26],
          iconAnchor: [13, 26]
        });
        const m = L.marker([lat, lon], { icon: icon }).addTo(map);
        if (label) m.bindPopup(label);
        this._map = map;
        const ro = new ResizeObserver(() => map.invalidateSize());
        ro.observe(this);
        this._ro = ro;
        setTimeout(() => map.invalidateSize(), 120);
      }).catch(() => {
        box.style.cssText += ';display:flex;align-items:center;justify-content:center;font:600 13px system-ui;color:#7a828c';
        box.textContent = 'Karte nicht verfügbar';
      });
    }
    disconnectedCallback() {
      if (this._ro) this._ro.disconnect();
      if (this._map) { this._map.remove(); this._map = null; }
      this._built = false;
    }
  }

  if (!customElements.get('anfahrt-map')) customElements.define('anfahrt-map', AnfahrtMap);
})();
