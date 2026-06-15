<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Location Map with Address, Latitude, and Longitude</title>
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyC71j570q3iQGfOnd_YVHpsAl808HlV6j4"></script>
    <style>
        #map {
            height: 70vh;
            width: 100%;
        }
        #address, #latitude, #longitude {
            width: 100%;
            padding: 10px;
            font-size: 18px;
            margin: 5px 0;
        }
    </style>
</head>
<body>

<textarea id="address" placeholder="Address: Loading..." readonly></textarea>
<input type="text" id="latitude" placeholder="Latitude" readonly>
<input type="text" id="longitude" placeholder="Longitude" readonly>
<div id="map"></div>

<script>
    let map;
    let marker;
    let geocoder;

    function initMap() {
        // Inisialisasi peta
        const initialLocation = { lat: -6.1751, lng: 106.8650 }; // Lokasi awal (Jakarta)
        map = new google.maps.Map(document.getElementById("map"), {
            zoom: 15,
            center: initialLocation,
        });

        marker = new google.maps.Marker({
            position: initialLocation,
            map: map,
        });

        geocoder = new google.maps.Geocoder();

        // Mendapatkan lokasi pengguna
        if (navigator.geolocation) {
            navigator.geolocation.watchPosition(updateLocation, showError);
        } else {
            alert("Geolocation is not supported by this browser.");
        }
    }

    function updateLocation(position) {
        const newLocation = {
            lat: position.coords.latitude,
            lng: position.coords.longitude,
        };

        // Update posisi marker dan pusat peta
        marker.setPosition(newLocation);
        map.setCenter(newLocation);

        // Tampilkan latitude dan longitude
        document.getElementById("latitude").value = newLocation.lat;
        document.getElementById("longitude").value = newLocation.lng;

        // Mendapatkan alamat dengan Geocoder
        getAddress(newLocation);
    }

    function getAddress(location) {
        geocoder.geocode({ location: location }, (results, status) => {
            if (status === "OK") {
                if (results[0]) {
                    document.getElementById("address").value = results[0].formatted_address;
                } else {
                    document.getElementById("address").value = "No results found";
                }
            } else {
                document.getElementById("address").value = "Geocoder failed due to: " + status;
            }
        });
    }

    function showError(error) {
        switch(error.code) {
            case error.PERMISSION_DENIED:
                alert("User denied the request for Geolocation.");
                break;
            case error.POSITION_UNAVAILABLE:
                alert("Location information is unavailable.");
                break;
            case error.TIMEOUT:
                alert("The request to get user location timed out.");
                break;
            case error.UNKNOWN_ERROR:
                alert("An unknown error occurred.");
                break;
        }
    }

    window.onload = initMap;
</script>

</body>
</html>
