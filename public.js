(() => {
    'use strict';
    const BASE = document.body.dataset.base || '';
    const CSRF = document.querySelector('meta[name="csrf"]').content;
    const COLORS = { belum: '#f2b01e', proses: '#2563eb', selesai: '#1f9d55' };
    const JATIM = [-7.75, 112.6];
    const TILE = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
    const ATTR = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>';
    const BOUNDS = [[-9.6, 110.0], [-5.0, 117.0]];
    const $ = (s, r = document) => r.querySelector(s);
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    /* ---------- Notifikasi ---------- */
    function toast(msg, type = 'success') {
        const el = document.createElement('div');
        el.className = `toast align-items-center text-bg-${type} border-0`;
        el.setAttribute('role', 'alert');
        el.innerHTML = `<div class="d-flex"><div class="toast-body">${esc(msg)}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Tutup"></button></div>`;
        $('#toasts').appendChild(el);
        el.addEventListener('hidden.bs.toast', () => el.remove());
        new bootstrap.Toast(el, { delay: 6000 }).show();
    }

    async function getJSON(url) {
        const r = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
    }

    /* ---------- Filter ---------- */
    const filterIds = ['q', 'category', 'regency', 'status'];
    function filters() {
        const p = new URLSearchParams();
        filterIds.forEach((k) => { const v = $('#f-' + k).value.trim(); if (v) p.set(k, v); });
        return p;
    }
    const filtersActive = () => [...filters()].length > 0;

    /* ---------- Peta utama ---------- */
    const map = L.map('map', { center: JATIM, zoom: 8, minZoom: 7, maxBounds: BOUNDS, maxBoundsViscosity: 0.8 });
    L.tileLayer(TILE, { maxZoom: 19, attribution: ATTR }).addTo(map);
    const layer = L.featureGroup().addTo(map);
    const markers = {};
    let popupOpen = false;
    map.on('popupopen', () => { popupOpen = true; });
    map.on('popupclose', () => { popupOpen = false; });

    function popupHtml(r) {
        const photo = r.photo_url
            ? `<a href="${esc(r.photo_url)}" target="_blank" rel="noopener"><img class="popup-photo" src="${esc(r.photo_url)}" alt="Foto bukti: ${esc(r.title)}" loading="lazy"></a>` : '';
        const desc = r.description.length > 200 ? r.description.slice(0, 200) + '…' : r.description;
        return `${photo}<div class="popup-body">
            <span class="badge status-badge badge-${esc(r.status)}">${esc(r.status_label)}</span>
            <h4>${esc(r.title)}</h4><p>${esc(desc)}</p>
            <dl class="popup-meta">
                <dt>Kategori</dt><dd>${esc(r.category)}</dd>
                <dt>Lokasi</dt><dd>${esc(r.address)}, ${esc(r.regency)}</dd>
                <dt>Dilaporkan</dt><dd>${esc(r.created_label)}</dd>
            </dl></div>`;
    }

    async function loadMarkers(fit = false) {
        const p = filters(); p.set('action', 'markers');
        $('#map-loading').classList.remove('d-none');
        try {
            const d = await getJSON(`${BASE}/api/reports.php?${p}`);
            layer.clearLayers();
            Object.keys(markers).forEach((k) => delete markers[k]);
            d.items.forEach((r) => {
                const m = L.circleMarker([r.latitude, r.longitude], { radius: 10, weight: 3, color: '#fff', fillColor: COLORS[r.status], fillOpacity: 1, className: 'pin' })
                    .bindPopup(popupHtml(r), { maxWidth: 260, minWidth: 250 });
                m.addTo(layer);
                markers[r.id] = m;
            });
            if (fit && d.items.length) map.fitBounds(layer.getBounds().pad(0.25), { maxZoom: 13 });
        } catch (e) {
            toast('Titik laporan gagal dimuat. Periksa koneksi lalu muat ulang halaman.', 'danger');
        } finally {
            $('#map-loading').classList.add('d-none');
        }
    }

    /* ---------- Statistik ---------- */
    async function loadStats() {
        try {
            const d = await getJSON(`${BASE}/api/reports.php?action=stats`);
            ['total', 'belum', 'proses', 'selesai'].forEach((k) => { $('#stat-' + k).textContent = Number(d[k]).toLocaleString('id-ID'); });
        } catch (e) { /* abaikan, coba lagi pada refresh berikutnya */ }
    }

    /* ---------- Daftar laporan ---------- */
    let page = 1, pages = 1;
    const list = $('#report-list');

    function card(r) {
        const thumb = r.photo_url
            ? `<img class="thumb" src="${esc(r.photo_url)}" alt="" loading="lazy">`
            : '<div class="thumb"><i class="fa-regular fa-image"></i></div>';
        return `<article class="report-item" data-id="${r.id}" tabindex="0" role="button" aria-label="Lihat ${esc(r.title)} di peta">
            ${thumb}
            <div class="flex-grow-1 min-w-0">
                <h3>${esc(r.title)}</h3>
                <div class="meta mb-1"><i class="fa-solid fa-tag me-1"></i>${esc(r.category)}</div>
                <div class="meta mb-1"><i class="fa-solid fa-location-dot me-1"></i>${esc(r.regency)}</div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="badge status-badge badge-${esc(r.status)}">${esc(r.status_label)}</span>
                    <span class="meta">${esc(r.created_label)}</span>
                </div>
            </div></article>`;
    }

    function emptyState() {
        return filtersActive()
            ? '<div class="empty-state"><i class="fa-solid fa-magnifying-glass d-block"></i><p class="mb-0">Tidak ada laporan yang cocok. Ubah kata kunci atau atur ulang filter.</p></div>'
            : '<div class="empty-state"><i class="fa-solid fa-inbox d-block"></i><p class="mb-2">Belum ada laporan masuk.</p><a href="#lapor" class="btn btn-sm btn-primary">Buat laporan pertama</a></div>';
    }

    async function loadList(reset = true, silent = false) {
        if (reset) {
            page = 1;
            if (!silent) list.innerHTML = '<div class="empty-state"><div class="spinner-border spinner-border-sm text-primary"></div></div>';
        }
        const p = filters(); p.set('action', 'list'); p.set('page', page);
        try {
            const d = await getJSON(`${BASE}/api/reports.php?${p}`);
            pages = d.pages;
            const html = d.items.map(card).join('');
            if (reset) {
                const sig = html + d.total;
                if (!(silent && list.dataset.sig === sig)) list.innerHTML = html || emptyState();
                list.dataset.sig = sig;
            }
            else { list.insertAdjacentHTML('beforeend', html); list.dataset.sig = ''; }
            $('#list-count').textContent = `${d.total.toLocaleString('id-ID')} laporan`;
            $('#more-wrap').classList.toggle('d-none', page >= pages);
        } catch (e) {
            if (reset) list.innerHTML = '<div class="empty-state"><i class="fa-solid fa-triangle-exclamation d-block"></i><p class="mb-2">Daftar gagal dimuat.</p><button class="btn btn-sm btn-outline-primary" id="retry">Coba lagi</button></div>';
            else toast('Gagal memuat laporan berikutnya.', 'danger');
        }
    }

    function focusReport(id) {
        const m = markers[id];
        if (!m) return;
        $('#map').scrollIntoView({ behavior: 'smooth', block: 'center' });
        map.flyTo(m.getLatLng(), 15, { duration: 0.8 });
        map.once('moveend', () => m.openPopup());
        setTimeout(() => { if (!m.isPopupOpen()) m.openPopup(); }, 1200);
    }

    list.addEventListener('click', (e) => {
        if (e.target.closest('#retry')) return loadList(true);
        const it = e.target.closest('.report-item');
        if (it) focusReport(it.dataset.id);
    });
    list.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        const it = e.target.closest('.report-item');
        if (it) { e.preventDefault(); focusReport(it.dataset.id); }
    });
    $('#btn-more').addEventListener('click', () => { page += 1; loadList(false); });

    function applyFilters() { loadList(true); loadMarkers(true); }
    let timer;
    $('#f-q').addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(applyFilters, 350); });
    ['category', 'regency', 'status'].forEach((k) => $('#f-' + k).addEventListener('change', applyFilters));
    $('#f-reset').addEventListener('click', () => {
        filterIds.forEach((k) => { $('#f-' + k).value = ''; });
        applyFilters();
        map.flyTo(JATIM, 8);
    });

    /* ---------- Peta pemilih lokasi ---------- */
    const pick = L.map('pick-map', { center: JATIM, zoom: 8, minZoom: 7, maxBounds: BOUNDS });
    L.tileLayer(TILE, { maxZoom: 19, attribution: ATTR }).addTo(pick);
    let pin = null;
    const latEl = $('#latitude'), lngEl = $('#longitude'), coordErr = $('[data-err="coords"]');

    function setPin(ll) {
        if (!pin) {
            pin = L.marker(ll, { draggable: true }).addTo(pick);
            pin.on('dragend', () => setPin(pin.getLatLng()));
        } else {
            pin.setLatLng(ll);
        }
        latEl.value = ll.lat.toFixed(7);
        lngEl.value = ll.lng.toFixed(7);
        coordErr.classList.add('d-none');
    }
    pick.on('click', (e) => setPin(e.latlng));

    $('#regency').addEventListener('change', (e) => {
        const o = e.target.selectedOptions[0];
        if (o && o.dataset.lat && !pin) pick.flyTo([+o.dataset.lat, +o.dataset.lng], 11);
    });

    $('#btn-locate').addEventListener('click', () => {
        if (!navigator.geolocation) return toast('Peramban tidak mendukung geolokasi. Klik peta secara manual.', 'warning');
        navigator.geolocation.getCurrentPosition(
            (pos) => { const ll = L.latLng(pos.coords.latitude, pos.coords.longitude); setPin(ll); pick.flyTo(ll, 16); },
            () => toast('Lokasi tidak dapat diakses. Klik peta untuk menandai lokasi.', 'warning'),
            { enableHighAccuracy: true, timeout: 10000 }
        );
    });

    /* ---------- Formulir ---------- */
    const form = $('#report-form');
    const photoEl = $('#photo'), preview = $('#photo-preview');

    function clearServerErrors() {
        form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
        coordErr.classList.add('d-none');
    }

    photoEl.addEventListener('change', () => {
        const f = photoEl.files[0];
        photoEl.setCustomValidity('');
        preview.classList.add('d-none');
        if (!f) return;
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(f.type)) photoEl.setCustomValidity('Format tidak didukung');
        else if (f.size > 3 * 1024 * 1024) photoEl.setCustomValidity('Terlalu besar');
        if (photoEl.validity.valid) { preview.src = URL.createObjectURL(f); preview.classList.remove('d-none'); }
        else { photoEl.value = ''; photoEl.classList.add('is-invalid'); toast('Foto harus JPG, PNG, atau WebP dengan ukuran maksimal 3 MB.', 'warning'); }
    });
    form.addEventListener('input', (e) => e.target.classList.remove('is-invalid'));

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearServerErrors();
        form.classList.add('was-validated');
        const coordsOk = !!latEl.value;
        coordErr.classList.toggle('d-none', coordsOk);
        if (!form.checkValidity() || !coordsOk) {
            toast('Lengkapi isian yang ditandai merah.', 'warning');
            const bad = form.querySelector(':invalid');
            if (bad) bad.focus();
            return;
        }
        const btn = $('#btn-submit');
        btn.disabled = true;
        $('#submit-spin').classList.remove('d-none');
        $('#submit-text').textContent = 'Mengirim…';
        try {
            const res = await fetch(`${BASE}/api/submit.php`, { method: 'POST', body: new FormData(form), headers: { 'X-CSRF-Token': CSRF, Accept: 'application/json' } });
            let d;
            try { d = await res.json(); } catch (_) { d = { ok: false, message: 'Respons server tidak valid.' }; }
            if (d.ok) {
                toast(d.message, 'success');
                form.reset();
                form.classList.remove('was-validated');
                preview.classList.add('d-none');
                if (pin) { pick.removeLayer(pin); pin = null; }
                pick.flyTo(JATIM, 8);
                loadStats(); loadList(true); loadMarkers(false);
            } else {
                form.classList.remove('was-validated');
                Object.entries(d.errors || {}).forEach(([k, msg]) => {
                    if (k === 'coords') { coordErr.textContent = msg; coordErr.classList.remove('d-none'); return; }
                    const input = form.elements[k];
                    const fb = form.querySelector(`.invalid-feedback[data-err="${k}"]`);
                    if (input) input.classList.add('is-invalid');
                    if (fb) fb.textContent = msg;
                });
                toast(d.message || 'Laporan gagal dikirim.', 'danger');
            }
        } catch (err) {
            toast('Tidak dapat terhubung ke server. Periksa koneksi lalu coba lagi.', 'danger');
        } finally {
            btn.disabled = false;
            $('#submit-spin').classList.add('d-none');
            $('#submit-text').textContent = 'Kirim laporan';
        }
    });

    /* ---------- Navbar scroll effect ---------- */
    const mainNav = document.getElementById('main-nav');
    if (mainNav) {
        let lastScroll = 0;
        window.addEventListener('scroll', () => {
            const y = window.scrollY;
            mainNav.classList.toggle('scrolled', y > 10);
            lastScroll = y;
        }, { passive: true });
    }

    /* ---------- Mulai + penyegaran otomatis (status dari admin tampil tanpa muat ulang) ---------- */
    loadStats(); loadList(true); loadMarkers(false);
    setInterval(() => {
        if (document.hidden) return;
        loadStats();
        if (!popupOpen) loadMarkers(false);
        if (page === 1) loadList(true, true);
    }, 30000);
})();
