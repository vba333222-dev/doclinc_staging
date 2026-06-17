<?php
// Koneksi ke database
$servername = "localhost";
$username = "idbcsnet_railway";
$password = "t4ny4p4k0f4"; // Sesuaikan dengan password database Anda
$dbname = "idbcsnet_railway";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Fungsi untuk mendapatkan IP user
function getUserIpAddr() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

// Proses simpan data
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $latitude = $_POST['latitude'];
    $longitude = $_POST['longitude'];
    $ip_address = getUserIpAddr();

    $stmt = $conn->prepare("INSERT INTO locations (name, ip_address, latitude, longitude) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssdd", $name, $ip_address, $latitude, $longitude);

    if ($stmt->execute()) {
        echo "Data berhasil disimpan!";
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input Data Lokasi</title>
    <script>
        // JavaScript untuk mendapatkan lokasi pengguna
        function getLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(showPosition, showError);
            } else {
                alert("Geolocation tidak didukung oleh browser ini.");
            }
        }

        function showPosition(position) {
            // Isi nilai latitude dan longitude ke input tersembunyi
            document.getElementById("latitude").value = position.coords.latitude;
            document.getElementById("longitude").value = position.coords.longitude;
        }

        function showError(error) {
            switch(error.code) {
                case error.PERMISSION_DENIED:
                    alert("Pengguna menolak permintaan Geolocation.");
                    break;
                case error.POSITION_UNAVAILABLE:
                    alert("Informasi lokasi tidak tersedia.");
                    break;
                case error.TIMEOUT:
                    alert("Permintaan lokasi mengalami timeout.");
                    break;
                case error.UNKNOWN_ERROR:
                    alert("Terjadi kesalahan yang tidak diketahui.");
                    break;
            }
        }
    </script>
</head>
<body onload="getLocation()">
    <h2>Input Data Lokasi</h2>
    <form method="post" action="">
        <label for="name">Nama:</label><br>
        <input type="text" id="name" name="name" required><br><br>

        <!-- Latitude dan Longitude akan otomatis terisi -->
        <input type="hidden" id="latitude" name="latitude">
        <input type="hidden" id="longitude" name="longitude">

        <input type="submit" value="Simpan">
    </form>
</body>
</html>
