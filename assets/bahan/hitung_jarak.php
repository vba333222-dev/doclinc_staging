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

// Ambil semua data lokasi dari tabel
$sql = "SELECT name, latitude, longitude FROM locations";
$sql2= "SELECT request_id,user_id,dokter_id,request_description,request_status,location,lattitude,longitude,created_at,updated_at FROM requests WHERE request_status='Pending'";
$result = $conn->query($sql);
$result2 = $conn->query($sql2);

$locations = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $locations[] = $row;
    }
}
$conn->close();

function getDistanceAndDuration($latitudeA, $longitudeA, $latitudeB, $longitudeB, $mode) {
    $apiKey = 'AIzaSyBBAlyuqqIRtJj68YxHyj8lpVRtiDcMjAc'; // Ganti dengan API Key Google Maps Anda
    $url = "https://maps.googleapis.com/maps/api/distancematrix/json?origins=$latitudeA,$longitudeA&destinations=$latitudeB,$longitudeB&mode=$mode&key=$apiKey";

    // Menggunakan cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);

    if ($data && $data['status'] === 'OK') {
        $distance = $data['rows'][0]['elements'][0]['distance']['text'];
        $duration = $data['rows'][0]['elements'][0]['duration']['text'];
        
        return [
            'distance' => $distance,
            'duration' => $duration
        ];
    } else {
        return [
            'distance' => null,
            'duration' => null,
            'error' => isset($data['status']) ? $data['status'] : 'Tidak dapat terhubung ke API'
        ];
    }
}

// Mode transportasi hanya "driving"
$mode = 'driving';

// Menampilkan jarak dan estimasi waktu tempuh untuk setiap pasangan lokasi
echo "<table border='1'>";
echo "<tr><th>Origin</th><th>Destination</th><th>Distance</th><th>Duration</th></tr>";

foreach ($locations as $origin) {
    foreach ($locations as $destination) {
        if ($origin['name'] !== $destination['name']) {
            $result = getDistanceAndDuration(
                $origin['latitude'],
                $origin['longitude'],
                $destination['latitude'],
                $destination['longitude'],
                $mode
            );

            if ($result['distance'] && $result['duration']) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($origin['name']) . "</td>";
                echo "<td>" . htmlspecialchars($destination['name']) . "</td>";
                echo "<td>" . $result['distance'] . "</td>";
                echo "<td>" . $result['duration'] . "</td>";
                echo "</tr>";
            } else {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($origin['name']) . "</td>";
                echo "<td>" . htmlspecialchars($destination['name']) . "</td>";
                echo "<td colspan='2'>Error: " . $result['error'] . "</td>";
                echo "</tr>";
            }
        }
    }
}

echo "</table>";
?>
