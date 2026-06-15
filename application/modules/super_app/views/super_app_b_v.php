<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cilegon Bersatu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="<?= base_url();?>assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
</head>
<body class="bg-success">
    <div class="position-relative">
        <div class="d-flex align-items-center justify-content-center vh-100">
            <a href="<?= base_url('login');?>" class="animate__animated animate__fadeInLeft text-center text-decoration-none text-white fw-bold">
                <img class="p-2" src="<?= base_url();?>assets/images/logo_no_margin.png" alt="" height="100px" height="100px"><br>NGAJI GEH
            </a>
            <a href="../../sehat_geh" class="animate__animated animate__fadeInRight text-center text-decoration-none text-white fw-bold" target="_blank">
                <img class="p-2" src="<?= base_url();?>assets/images/sehat_geh.png" alt="" height="100px" height="100px"><br>SEHAT GEH
            </a>
        </div>
        <div class="d-flex align-items-center position-absolute bottom-0 start-50 translate-middle-x mb-3">
            <span class="text-white small text-light text-center animate__animated animate__fadeIn"><b>Copyright © Robinsar-Fajar</b><br>All rights reserved</span>
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