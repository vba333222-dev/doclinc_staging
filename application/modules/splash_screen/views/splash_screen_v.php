<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Doc Linc (BETA)- Login</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <link rel="stylesheet" href="<?= base_url();?>assets/css/style.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

</head>

<body class="bg-success">

    <div class="position-relative">

        <div class="d-flex align-items-center justify-content-center vh-100">

            <center>

                <img class="animate__animated animate__bounceIn" src="<?= base_url();?>assets/images/doklincwhite.png" alt="" height="100px">

                <!-- <h2 class="mb-0 text-white animate__animated animate__fadeIn">SEHAT GEH (beta)</h2> -->

            </center>

        </div>

        <div class="d-flex align-items-center justify-content-center position-absolute bottom-0 start-50 translate-middle-x mb-4">

            <div class="spinner-border text-light" role="status">

              <span class="visually-hidden">Loading...</span>

            </div>

            <span class="ms-2 text-white">Memuat...</span>

        </div>

    </div>



    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>

	<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js" integrity="sha384-0pUGZvbkm6XF6gxjEnlmuGrJXVbNuzT9qBBavbLwCsOGabYfZo0T0to5eqruptLy" crossorigin="anonymous"></script>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script src="<?= base_url('assets/js/wow.min.js');?>"></script>



    <!-- Script untuk menangani login, alert sukses, dan redirect ke loading page -->

    <script>

        new WOW().init();

        setTimeout(function() {

            window.location.href = '<?= base_url('login');?>';

        }, 3000);

    </script>

</body>

</html>
