<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super App Cilegon - Pilih layanan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url();?>assets/css/style.css">
    <link rel="stylesheet" href="<?= base_url('assets/css/doclinc-design-system.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
</head>
<body class="bg-success">
    <div class="position-relative">
        <!-- flex-wrap + gap menggantikan <center>: dua pilihan layanan tetap
             berdampingan pada layar lebar dan turun ke bawah pada layar sempit,
             bukan saling menghimpit seperti sebelumnya. -->
        <div class="d-flex flex-wrap align-items-center justify-content-center gap-4 vh-100 px-3">
            <a href="<?= base_url('login');?>" class="animate__animated animate__fadeInLeft text-center text-decoration-none text-white fw-bold">
                <!-- logo_no_margin.png berukuran 880x880: 100x100 adalah rasio aslinya. -->
                <img class="p-2" src="<?= base_url();?>assets/images/logo_no_margin.png" alt="" width="100" height="100" decoding="async" fetchpriority="high">
                <span class="d-block">NGAJI GEH</span>
            </a>
            <a href="../../sehat_geh" class="animate__animated animate__fadeInRight text-center text-decoration-none text-white fw-bold" target="_blank" rel="noopener noreferrer">
                <!-- sehat_geh.png berukuran 458x454: 100x99 adalah rasio aslinya. -->
                <img class="p-2" src="<?= base_url();?>assets/images/sehat_geh.png" alt="" width="100" height="99" decoding="async">
                <span class="d-block">SEHAT GEH</span>
            </a>
        </div>
        <div class="d-flex align-items-center position-absolute bottom-0 start-50 translate-middle-x mb-3">
            <span class="text-white small text-center animate__animated animate__fadeIn"><b>Copyright © Robinsar-Fajar</b><br>All rights reserved</span>
        </div>
    </div>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js" integrity="sha384-0pUGZvbkm6XF6gxjEnlmuGrJXVbNuzT9qBBavbLwCsOGabYfZo0T0to5eqruptLy" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="<?= base_url('assets/js/wow.min.js');?>"></script>

    <!-- Script untuk menangani login, alert sukses, dan redirect ke loading page -->
    <script>
        // new WOW().init();
        // setTimeout(function() {
        //     window.location.href = '<?= base_url('super_app/b');?>';
        // }, 3000);
    </script>
</body>
</html>