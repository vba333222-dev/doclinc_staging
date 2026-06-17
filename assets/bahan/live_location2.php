<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lokasi dan Alamat</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 20px;
        }
        .location-info {
            background-color: #09AD74;
            color: white;
            padding: 20px;
            border-radius: 8px;
            display: inline-block;
            margin-top: 20px;
            box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.2);
        }
    </style>
</head>
<body>

    <h1>Koordinat dan Alamat Lokasi Anda</h1>

    <div class="location-info" id="locationInfo" style="display: none;">
        <p><strong>Latitude:</strong> <span id="latitude"></span></p>
        <p><strong>Longitude:</strong> <span id="longitude"></span></p>
        <p><strong>Alamat:</strong> <span id="address"></span></p>
    </div>

    <script>
        // Memanggil fungsi untuk mendapatkan lokasi saat halaman dimuat
        window.onload = function() {
            getLocation();
        };

        // Fungsi untuk mendapatkan lokasi
        function getLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(showPosition, showError);
            } else {
                alert("Geolocation tidak didukung oleh browser ini.");
            }
        }

        // Fungsi untuk menampilkan koordinat dan alamat berdasarkan lokasi
        function showPosition(position) {
            const latitude = position.coords.latitude;
            const longitude = position.coords.longitude;

            document.getElementById("latitude").textContent = latitude;
            document.getElementById("longitude").textContent = longitude;
            document.getElementById("locationInfo").style.display = "block";

            // Panggil API untuk mendapatkan alamat berdasarkan koordinat
            getAddress(latitude, longitude);
        }

        // Fungsi untuk mendapatkan alamat dari koordinat
        function getAddress(latitude, longitude) {
            // Masukkan API Key Anda di sini
            const apiKey = 'AIzaSyC71j570q3iQGfOnd_YVHpsAl808HlV6j4';
            const url = `https://api.opencagedata.com/geocode/v1/json?q=${latitude}+${longitude}&key=${apiKey}`;

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (data.results && data.results.length > 0) {
                        const address = data.results[0].formatted;
                        document.getElementById("address").textContent = address;
                    } else {
                        document.getElementById("address").textContent = "Alamat tidak ditemukan.";
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        // Fungsi untuk menangani error geolocation
        function showError(error) {
            switch(error.code) {
                case error.PERMISSION_DENIED:
                    alert("Pengguna menolak permintaan lokasi.");
                    break;
                case error.POSITION_UNAVAILABLE:
                    alert("Informasi lokasi tidak tersedia.");
                    break;
                case error.TIMEOUT:
                    alert("Permintaan lokasi melebihi batas waktu.");
                    break;
                case error.UNKNOWN_ERROR:
                    alert("Error tidak diketahui.");
                    break;
            }
        }
    </script>

</body>
</html>
