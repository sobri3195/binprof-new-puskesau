'use strict';

const panel = document.querySelector('#map');
const state = document.querySelector('#map-status');
const source = document.querySelector('#map-data');

if (panel && state && source && typeof L !== 'undefined') {
    const map = L.map(panel, {
        scrollWheelZoom: false,
        zoomAnimation: !matchMedia('(prefers-reduced-motion: reduce)').matches,
        maxZoom: 14
    }).setView([-2.5, 118], 4);

    fetch(panel.dataset.outline).then(response => {
        if (!response.ok) throw new Error('Outline unavailable');
        return response.json();
    }).then(outline => {
        L.geoJSON(outline, { interactive: false, style: {
            color: '#9bb8ad', fillColor: '#adc9bb', fillOpacity: .74, weight: 1
        }}).addTo(map);
        map.attributionControl.addAttribution('Natural Earth (domain publik)');
    }).catch(() => {
        state.textContent = 'Latar peta lokal gagal dimuat. Gunakan marker atau tabel satuan.';
    });

    if (panel.dataset.tiles) {
        const attribution = document.createElement('span');
        attribution.textContent = panel.dataset.attribution;
        L.tileLayer(panel.dataset.tiles, {attribution: attribution.innerHTML, maxZoom: 14, keepBuffer: 0})
            .on('tileerror', () => {
                state.textContent = 'Basemap gagal dimuat. Marker dan tabel satuan tetap tersedia.';
            }).addTo(map);
    }

    const markers = [];
    const units = JSON.parse(source.textContent);
    for (const unit of units) {
        if (unit.latitude === null) continue;
        const summary = document.createElement('div');
        const title = document.createElement('strong');
        title.textContent = unit.name;
        const totals = document.createElement('p');
        totals.textContent = unit.total + ' personel · ' + unit.military + ' Militer · ' + unit.pns + ' PNS';
        const detail = document.createElement('a');
        detail.href = unit.detail;
        detail.textContent = 'Lihat personel satuan';
        summary.append(title, totals, detail);
        const marker = L.marker([unit.latitude, unit.longitude], {title: unit.name, alt: unit.name});
        marker.bindPopup(summary).addTo(map);
        markers.push(marker.getLatLng());
    }
    if (markers.length) {
        map.fitBounds(L.latLngBounds(markers), {padding: [35, 35], maxZoom: 8, animate: false});
        state.textContent = 'Marker menunjukkan satuan dengan koordinat tercatat.';
    } else {
        state.textContent = 'Tidak ada koordinat satuan yang sesuai filter.';
    }
    const fullscreen = document.querySelector('#map-fullscreen');
    if (document.fullscreenEnabled) {
        fullscreen.hidden = false;
        fullscreen.addEventListener('click', () => panel.requestFullscreen().catch(() => {
            state.textContent = 'Layar penuh tidak tersedia. Peta tetap dapat digunakan.';
        }));
        document.addEventListener('fullscreenchange', () => map.invalidateSize());
    }
}
