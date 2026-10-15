<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Pilih Titik Penerima</title>
    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
        crossorigin=""
    >
    <style>
        html, body { margin: 0; padding: 0; height: 100%; background: #f6f7f9; }
        #map { position: absolute; inset: 0; }
        .map-note {
            position: absolute;
            left: 12px;
            right: 12px;
            bottom: 12px;
            z-index: 500;
            padding: 8px 12px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.94);
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.2);
            font: 500 12px/1.4 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            color: #44474a;
        }
        .pin {
            width: 30px;
            height: 30px;
        }
    </style>
</head>
<body>
    <div id="map"></div>
    <div class="map-note" id="note">Ketuk peta atau geser pin ke alamat penerima.</div>

    <script
        src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
        crossorigin=""
    ></script>
    <script>
        (function () {
            var JAKARTA = [-6.2, 106.8];
            var SAVE_URL = '/client/map/pick';
            var POINT_URL = '/client/map/pick/point';
            var PIN_HTML =
                '<svg class="pin" viewBox="0 0 24 24" fill="#1f6feb" xmlns="http://www.w3.org/2000/svg">' +
                '<path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5z"/></svg>';

            var note = document.getElementById('note');

            if (typeof L === 'undefined') {
                note.textContent = 'Peta butuh koneksi internet untuk memuat.';
                return;
            }

            var map = L.map('map', { zoomControl: false, attributionControl: true })
                .setView(JAKARTA, 12);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap'
            }).addTo(map);

            var marker = null;

            function pinIcon() {
                return L.divIcon({ className: '', html: PIN_HTML, iconSize: [30, 30], iconAnchor: [15, 30] });
            }

            function save(lat, lng) {
                note.textContent = 'Menyimpan titik…';

                fetch(SAVE_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                    body: JSON.stringify({ latitude: lat, longitude: lng })
                })
                    .then(function (response) {
                        note.textContent = response.ok
                            ? 'Titik tersimpan: ' + lat.toFixed(5) + ', ' + lng.toFixed(5)
                            : 'Gagal menyimpan titik. Coba lagi.';
                    })
                    .catch(function () {
                        note.textContent = 'Gagal menyimpan titik. Coba lagi.';
                    });
            }

            function place(lat, lng, persist) {
                if (marker === null) {
                    marker = L.marker([lat, lng], { icon: pinIcon(), draggable: true }).addTo(map);
                    marker.on('dragend', function () {
                        var at = marker.getLatLng();
                        save(at.lat, at.lng);
                    });
                } else {
                    marker.setLatLng([lat, lng]);
                }

                if (persist) save(lat, lng);
            }

            map.on('click', function (event) {
                place(event.latlng.lat, event.latlng.lng, true);
            });

            fetch(POINT_URL, { headers: { Accept: 'application/json' } })
                .then(function (response) { return response.ok ? response.json() : null; })
                .then(function (data) {
                    var point = data && data.point;
                    if (point && point.latitude !== null && point.longitude !== null) {
                        place(point.latitude, point.longitude, false);
                        map.setView([point.latitude, point.longitude], 16);
                        note.textContent = 'Titik tersimpan: ' +
                            Number(point.latitude).toFixed(5) + ', ' + Number(point.longitude).toFixed(5);
                    }
                })
                .catch(function () { /* best effort: start from the default view */ });

            // Keep the map full-bleed once its box is measured.
            requestAnimationFrame(function () { map.invalidateSize(); });
        })();
    </script>
</body>
</html>
