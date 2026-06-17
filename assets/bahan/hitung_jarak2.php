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

// Ambil data lokasi dari tabel
$sql = "SELECT name, latitude, longitude FROM locations LIMIT 0, 2"; // Ambil 2 lokasi pertama sebagai contoh
$result = $conn->query($sql);

$locations = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $locations[] = $row;
    }
}
$conn->close();

if (count($locations) >= 2) {
    $origin = $locations[0];
    $destination = $locations[1];
}

function getDistanceAndDuration($latitudeA, $longitudeA, $latitudeB, $longitudeB, $mode) {
    $apiKey = 'AIzaSyC71j570q3iQGfOnd_YVHpsAl808HlV6j4'; // Ganti dengan API Key Google Maps Anda
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

if ($origin && $destination) {
    $result = getDistanceAndDuration(
        $origin['latitude'],
        $origin['longitude'],
        $destination['latitude'],
        $destination['longitude'],
        $mode
    );

    echo "<table border='1'>";
    echo "<tr><th>Origin</th><th>Destination</th><th>Distance</th><th>Duration</th></tr>";
    if ($result['distance'] && $result['duration']) {
        echo "<tr>";
        echo "<td>Dokter</td>";
        echo "<td>Warga</td>";
        echo "<td>" . $result['distance'] . "</td>";
        echo "<td>" . $result['duration'] . "</td>";
        echo "</tr>";
    } else {
        echo "<tr><td colspan='4'>Error: " . $result['error'] . "</td></tr>";
    }
    echo "</table>";
}
?>

<!-- Tambahkan Font Awesome CDN -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />

<!-- Div untuk peta -->
<div id="map" style="height: 500px; width: 100%;"></div>

<!-- Script Google Maps API untuk menampilkan rute -->
<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyAjZ7MfX-oPww9e7pkX-YoVQd9jn1dFSNY&callback=initMap" async defer></script>
<script>
function initMap() {
    // Lokasi "Dokter" dan "Warga" dari hasil query
    var dokter = { lat: <?php echo $origin['latitude']; ?>, lng: <?php echo $origin['longitude']; ?> };
    var warga = { lat: <?php echo $destination['latitude']; ?>, lng: <?php echo $destination['longitude']; ?> };
    
    // Membuat peta
    var map = new google.maps.Map(document.getElementById('map'), {
        center: dokter,
        zoom: 12
    });

    // Mengganti marker "Dokter" dengan ikon Font Awesome untuk "medical" dan "Warga" dengan "user"
    var markerDokter = new google.maps.Marker({
        position: dokter,
        map: map,
        icon: {
            url: "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/svgs/solid/user-md.svg", // Ikon "Dokter" dari Font Awesome
            scaledSize: new google.maps.Size(40, 40)
        },
        title: "Dokter"
    });

    var markerWarga = new google.maps.Marker({
        position: warga,
        map: map,
        icon: {
            url: "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/svgs/solid/user.svg", // Ikon "Warga" dari Font Awesome
            scaledSize: new google.maps.Size(40, 40)
        },
        title: "Warga"
    });

    // Membuat Directions Service dan Renderer
    var directionsService = new google.maps.DirectionsService();
    var directionsRenderer = new google.maps.DirectionsRenderer();
    directionsRenderer.setMap(map);

    // Membuat request rute
    var request = {
        origin: dokter,
        destination: warga,
        travelMode: 'DRIVING'
    };

    // Menggambarkan rute di peta
    directionsService.route(request, function(result, status) {
        if (status === 'OK') {
            directionsRenderer.setDirections(result);
        } else {
            alert('Gagal menampilkan rute: ' + status);
        }
    });
}
</script>
