<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
	<title>SehatGeh - Home</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
	<link rel="stylesheet" href="<?= base_url(); ?>assets/css/style.css">
    <style>
        /* Container utama untuk daftar notifikasi */
        .notification-container {
            width: 400px;
            max-height: 450px;
            overflow-y: auto;
            padding: 10px;
            border-radius: 8px;
            background-color: #09AD74;
            color: white;
            font-family: Arial, sans-serif;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
            margin: 20px auto;
        }

        /* Gaya untuk setiap kartu notifikasi */
        .status-card {
            display: flex;
            align-items: center;
            background-color: white;
            color: #09AD74;
            margin-bottom: 10px;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            font-size: 14px;
        }

        /* Icon status */
        .status-icon {
            font-size: 24px;
            margin-right: 10px;
        }

        /* Pesan dalam status */
        .status-message {
            flex: 1;
        }

        /* Tombol untuk menghapus kartu */
        .status-close {
            cursor: pointer;
            font-weight: bold;
            font-size: 18px;
            color: #09AD74;
        }
    </style>
</head>
<body>

    <h2 style="text-align: center;">Daftar Notifikasi Kesehatan</h2>

    <!-- Container untuk daftar notifikasi -->
    <div class="notification-container" id="notificationContainer">
        <!-- Notifikasi pertama -->
        <div class="status-card">
            <span class="status-icon">🚑</span>
            <span class="status-message">Pahlawan 1 (Dokter/Nakes) sedang dalam perjalanan.</span>
            <span class="status-close" onclick="this.parentElement.style.display='none'">×</span>
        </div>

        <!-- Notifikasi kedua -->
        <div class="status-card">
            <span class="status-icon">✅</span>
            <span class="status-message">Pahlawan 1 menyelesaikan tindakan kesehatan.</span>
            <span class="status-close" onclick="this.parentElement.style.display='none'">×</span>
        </div>

        <!-- Notifikasi ketiga -->
        <div class="status-card">
            <span class="status-icon">🕒</span>
            <span class="status-message">Pahlawan 2 akan segera tiba di lokasi.</span>
            <span class="status-close" onclick="this.parentElement.style.display='none'">×</span>
        </div>

        <!-- Tambahkan notifikasi lainnya sesuai kebutuhan -->
    </div>

</body>
</html>
