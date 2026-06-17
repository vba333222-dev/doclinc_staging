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

// Ambil data lokasi dari database
// $sql = "SELECT name, latitude, longitude FROM locations";
$sql = "SELECT user_id as name, lattitude as latitude, longitude  FROM requests WHERE request_status='pending' ORDER BY request_id DESC LIMIT 1";
$result = $conn->query($sql);

$locations = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $locations[] = $row;
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Lokasi Pengguna</title>
    <style>
        /* Style untuk peta dan tabel */
        #map_lokasi_pasien {
            height: 500px;
            width: 100%;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        table, th, td {
            border: 1px solid black;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
    </style>
</head>
<body>
    <h2>Data Lokasi Pengguna</h2>


        <?php foreach ($locations as $location): ?>
          <?php echo htmlspecialchars($location['name']); ?>
               <?php echo htmlspecialchars($location['latitude']); ?>
             <?php echo htmlspecialchars($location['longitude']); ?>
        <?php endforeach; ?>
    <h3>Peta Lokasi Pengguna</h3>
    <!-- Peta Google Maps -->
    <div id="map_lokasi_pasien"></div>

    <!-- Google Maps API dan JavaScript untuk menampilkan pin lokasi -->
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyC71j570q3iQGfOnd_YVHpsAl808HlV6j4"></script>
    <script>
        function initMapPasien() {
            // Set lokasi awal (koordinat Indonesia sebagai contoh)
            const centerLatLng = { lat: <?php echo htmlspecialchars($location['latitude']); ?>, lng: <?php echo htmlspecialchars($location['longitude']); ?> };
            const map = new google.maps.Map(document.getElementById("map_lokasi_pasien"), {
                zoom: 14,
                center: centerLatLng,
            });

            // Lokasi pengguna dari PHP
            const locations = <?php echo json_encode($locations); ?>;

            // Tambahkan marker untuk setiap lokasi
            locations.forEach(location => {
                const marker = new google.maps.Marker({
                    position: {
                        lat: parseFloat(location.latitude),
                        lng: parseFloat(location.longitude)
                    },
                    map: map,
                    title: location.name
                });

                // Info window untuk setiap marker
                const infowindow = new google.maps.InfoWindow({
                    content: `<strong>${location.name}</strong>`
                });

                marker.addListener("click", () => {
                    infowindow.open(map, marker);
                });
            });
        }

        // Panggil fungsi initMap saat halaman selesai dimuat
        window.onload = initMapPasien;
    </script>
</body>
</html>
