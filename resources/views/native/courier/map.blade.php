<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Peta Pengantaran {{ $resi }}</title>
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
            bottom: 12px;
            z-index: 500;
            padding: 6px 10px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.92);
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.2);
            font: 500 12px/1.4 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            color: #44474a;
        }
        .dot {
            width: 16px;
            height: 16px;
            border-radius: 999px;
            border: 2px solid #ffffff;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.35);
        }
        .dot-courier { background: #2e7d32; }
        .dot-destination { background: #1f6feb; }
    </style>
</head>
<body>
    <div id="map"></div>
    <div class="map-note" id="note">Menunggu sinyal GPS…</div>

    <script
        src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
        crossorigin=""
    ></script>
    <script>
        (function () {
            var RESI = @json($resi);
            var POLL_MS = 2000;
            var JAKARTA = [-6.2, 106.8];

            var note = document.getElementById('note');

            if (typeof L === 'undefined') {
                note.textContent = 'Peta butuh koneksi internet untuk memuat.';
                return;
            }

            var map = L.map('map', { zoomControl: false, attributionControl: true })
                .setView(JAKARTA, 13);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap'
            }).addTo(map);

            function dot(className) {
                return L.divIcon({
                    className: '',
                    html: '<div class="dot ' + className + '"></div>',
                    iconSize: [16, 16],
                    iconAnchor: [8, 8]
                });
            }

            var destinationMarker = null;
            var courierMarker = null;
            var fence = null;
            var framed = false;

            function ensureFence(lat, lng, radius) {
                if (fence || !radius) return;
                fence = L.circle([lat, lng], {
                    radius: radius,
                    color: '#1f6feb',
                    weight: 1,
                    fillColor: '#1f6feb',
                    fillOpacity: 0.08,
                    dashArray: '4 4'
                }).addTo(map);
            }

            function draw(data) {
                var destination = data && data.destination;
                var fix = data && data.fix;

                if (destination && destination.latitude !== null && destination.longitude !== null) {
                    var dPoint = [destination.latitude, destination.longitude];
                    if (!destinationMarker) {
                        destinationMarker = L.marker(dPoint, { icon: dot('dot-destination') })
                            .addTo(map)
                            .bindPopup('Titik penerima');
                    } else {
                        destinationMarker.setLatLng(dPoint);
                    }
                    ensureFence(destination.latitude, destination.longitude, data.radius_m);
                }

                if (fix && fix.latitude !== null && fix.longitude !== null) {
                    var cPoint = [fix.latitude, fix.longitude];
                    if (!courierMarker) {
                        courierMarker = L.marker(cPoint, { icon: dot('dot-courier') })
                            .addTo(map)
                            .bindPopup('Posisi kurir');
                    } else {
                        courierMarker.setLatLng(cPoint);
                    }
                    note.textContent = 'Akurasi ±' + Math.round(fix.accuracy_m || 0) + ' m';
                }

                // Frame the first data we see; after that leave the view alone so
                // a moving courier does not yank the map on every poll.
                if (!framed) {
                    var points = [];
                    if (destinationMarker) points.push(destinationMarker.getLatLng());
                    if (courierMarker) points.push(courierMarker.getLatLng());
                    if (points.length === 1) {
                        map.setView(points[0], 16);
                        framed = true;
                    } else if (points.length > 1) {
                        map.fitBounds(L.latLngBounds(points).pad(0.3), { maxZoom: 16 });
                        framed = true;
                    }
                }
            }

            function poll() {
                fetch('/courier/map/' + encodeURIComponent(RESI) + '/fix', {
                    headers: { Accept: 'application/json' }
                })
                    .then(function (response) { return response.ok ? response.json() : null; })
                    .then(function (data) { if (data) draw(data); })
                    .catch(function () { /* keep the last known position */ });
            }

            poll();
            setInterval(poll, POLL_MS);
        })();
    </script>
</body>
</html>
