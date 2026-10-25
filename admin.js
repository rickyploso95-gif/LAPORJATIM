(() => {
    'use strict';
    const $ = (s, r = document) => r.querySelector(s);
    const COLORS = { belum: '#f2b01e', proses: '#2563eb', selesai: '#1f9d55' };
    const LABELS = { belum: 'Belum Ditangani', proses: 'Proses', selesai: 'Selesai' };
    const TILE = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
    const ATTR = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>';
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    // Sidebar mobile
    const sb = $('#sidebar');
    $('#sb-toggle')?.addEventListener('click', () => sb.classList.toggle('open'));
    document.addEventListener('click', (e) => {
        if (sb && sb.classList.contains('open') && !sb.contains(e.target) && !e.target.closest('#sb-toggle')) sb.classList.remove('open');
    });

    // Konfirmasi untuk form berisiko (data-confirm)
    document.addEventListener('submit', (e) => {
        const msg = e.target.dataset && e.target.dataset.confirm;
        if (msg && !window.confirm(msg)) e.preventDefault();
    });

    if (!window.L) return;
    const pinStyle = (status) => ({ radius: 10, weight: 3, color: '#fff', fillColor: COLORS[status], fillOpacity: 1, className: 'pin' });

    // Peta mini di halaman detail laporan
    const dm = $('#detail-map');
    if (dm) {
        const ll = [parseFloat(dm.dataset.lat), parseFloat(dm.dataset.lng)];
        const m = L.map(dm).setView(ll, 15);
        L.tileLayer(TILE, { maxZoom: 19, attribution: ATTR }).addTo(m);
        L.circleMarker(ll, pinStyle(dm.dataset.status)).addTo(m);
    }

    // Peta seluruh laporan (admin)
    const am = $('#admin-map');
    if (am) {
        const data = JSON.parse($('#map-data').textContent || '[]');
        const map = L.map(am, { center: [-7.75, 112.6], zoom: 8, minZoom: 7 });
        L.tileLayer(TILE, { maxZoom: 19, attribution: ATTR }).addTo(map);
        const layer = L.featureGroup().addTo(map);
        const ids = ['status', 'category', 'regency'];
        let first = true;

        function render() {
            const f = Object.fromEntries(ids.map((k) => [k, $('#m-' + k).value]));
            layer.clearLayers();
            const shown = data.filter((r) => (!f.status || r.status === f.status) && (!f.category || String(r.category_id) === f.category) && (!f.regency || r.regency === f.regency));
            shown.forEach((r) => {
                L.circleMarker([r.lat, r.lng], pinStyle(r.status)).addTo(layer).bindPopup(
                    `<div class="popup-body"><span class="badge status-badge badge-${esc(r.status)}">${esc(LABELS[r.status])}</span>
                    <h4>${esc(r.title)}</h4>
                    <dl class="popup-meta"><dt>Kategori</dt><dd>${esc(r.category)}</dd><dt>Lokasi</dt><dd>${esc(r.address)}, ${esc(r.regency)}</dd><dt>Tanggal</dt><dd>${esc(r.date)}</dd></dl>
                    <a class="btn btn-sm btn-primary mt-2" href="report.php?id=${r.id}">Buka detail</a></div>`);
            });
            $('#m-count').textContent = `${shown.length} dari ${data.length} titik`;
            if (shown.length && (!first || ids.some((k) => f[k]))) map.fitBounds(layer.getBounds().pad(0.2), { maxZoom: 13 });
            first = false;
        }
        ids.forEach((k) => $('#m-' + k).addEventListener('change', render));
        render();
    }
})();
