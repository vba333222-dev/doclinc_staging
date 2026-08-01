<?php
$google_maps_api_key = $this->config->item('google_maps_api_key') ?: '';
$map_provider = $this->config->item('map_provider') ?: 'none';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <?= doclinc_csrf_bootstrap_markup(); ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Real-Time Location Tracking</title>
    <!-- Load Google Maps API -->
    <?php if ($map_provider === 'google' && !empty($google_maps_api_key)) : ?>
        <script src="https://maps.googleapis.com/maps/api/js?key=<?= rawurlencode($google_maps_api_key); ?>&libraries=places"></script>
    <?php endif; ?>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <h2>Real-Time Location Tracking</h2>
    <div id="map" style="height: 500px; width: 100%;"></div>
INI TES LOKASI
<input type="text" >
    <script>
        let map, marker;
        const mapProvider = <?= json_encode($map_provider); ?>;
        const id_user = 1; // Example user ID
        const name = "User Name"; // Example user name
        function hasGoogleMaps() {
            return mapProvider === 'google' && window.google && window.google.maps;
        }
        function initMap() {
            if (!hasGoogleMaps()) return;

            map = new google.maps.Map(document.getElementById("map"), {
                center: { lat: -6.200000, lng: 106.816666 }, // Sementara posisi awal Jakarta
                zoom: 15
            });
            marker = new google.maps.Marker({
                map: map,
                position: map.getCenter(),
                title: "Your Location"
            });
            // Start watching the location in real-time
            if (navigator.geolocation) {
                navigator.geolocation.watchPosition(updateLocation, handleError, {
                    enableHighAccuracy: true,
                    maximumAge: 0,
                    timeout: 120000
                });
            } else {
                alert("Geolocation is not supported by this browser.");
            }
        }
        function updateLocation(position) {
            if (!hasGoogleMaps() || !marker || !map) return;

            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            // Update marker position
            const newPosition = new google.maps.LatLng(lat, lng);
            marker.setPosition(newPosition);
            map.setCenter(newPosition);
            // Send data to the server every 2 minutes
            $.ajax({
                url: "<?= base_url('home_nakes/save_location') ?>",
                type: "POST",
                data: {
                    id_user: id_user,
                    name: name,
                    latitude: lat,
                    longitude: lng
                },
                success: function(response) {
                },
                error: function(error) {
                }
            });
        }
        function handleError(error) {
        }
        // Initialize the map when the window loads
        if (hasGoogleMaps()) {
            window.addEventListener('load', initMap);
        }
    </script>
</body>
</html>
