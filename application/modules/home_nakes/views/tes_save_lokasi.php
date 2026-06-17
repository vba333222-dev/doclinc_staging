<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Real-Time Location Tracking</title>
    <!-- Load Google Maps API -->
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBBAlyuqqIRtJj68YxHyj8lpVRtiDcMjAc&libraries=places"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <h2>Real-Time Location Tracking</h2>
    <div id="map" style="height: 500px; width: 100%;"></div>
INI TES LOKASI
<input type="text" >
    <script>
        let map, marker;
        const id_user = 1; // Example user ID
        const name = "User Name"; // Example user name
        function initMap() {
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
                    console.log("Location saved:", response);
                },
                error: function(error) {
                    console.error("Error saving location:", error);
                }
            });
        }
        function handleError(error) {
            console.error("Geolocation error:", error);
        }
        // Initialize the map when the window loads
        window.onload = initMap;
    </script>
</body>
</html>
