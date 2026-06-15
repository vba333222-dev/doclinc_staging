<div class="d-sm-flex align-items-center justify-content-between pt-4 pb-5 px-4 mt-n4 mx-n4 you-are-here">
	<h1 class="h3 mb-0 font-weight-bold"><i class="fas fa-laptop-house fa-fw"></i> Landing Page</h1>
	<!-- <a href="#" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm"><i class="fas fa-download fa-sm text-white-50"></i> Home</a> -->
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb mb-0 bg-pink text-white">
      <li class="breadcrumb-item">Landing Page <i class="fas fa-chevron-right fa-fw" style="line-height: inherit;"></i> About Us</li>
    </ol>
  </nav>
</div>

<!-- Content Row -->
<?php
  foreach ($data_about->result() as $row) {
    $desc = $row->desc;
    $visi = $row->visi;
    $misi = $row->misi;
    $pict = $row->pict;
  }
?>
<div class="row">
  <div class="col-12 col-lg-6 mb-3 position-relative">
    <!-- <img src="../../assets/img/<?= $pict;?>" class="img-fluid img-thumbnail shadow-sm"> -->
    <img src="https://asokaconsulting.co.id/assets/img/<?= $pict;?>" class="img-fluid img-thumbnail shadow-sm">
    <button class="btn btn-default-asoka float-right" style="position:absolute;top:20px;right:34px;" data-toggle="modal" data-target="#modalPict">Edit</button>
  </div>
  <div class="col-12 col-lg-6 mb-3">
    <div class="card shadow-sm">
      <div class="card-body text-center">
        <h4 class="fw-bold mb-3">Deskripsi tentang kami</h4>
        <textarea class="form-control mb-3 bg-white" rows="5" readonly><?= $desc;?></textarea>
        <button class="btn btn-default-asoka float-right" data-toggle="modal" data-target="#modalDesc">Edit</button>
      </div>
    </div>
  </div>
  <div class="col-12 col-lg-6 mb-3">
    <div class="card shadow-sm">
      <div class="card-body text-center">
        <h4 class="fw-bold mb-3">Visi</h4>
        <textarea class="form-control mb-3 bg-white" rows="5" readonly><?= $visi;?></textarea>
        <button class="btn btn-default-asoka float-right" data-toggle="modal" data-target="#modalVisi">Edit</button>
      </div>
    </div>
  </div>
  <div class="col-12 col-lg-6 mb-3">
    <div class="card shadow-sm">
      <div class="card-body">
        <h4 class="fw-bold mb-3 text-center">Misi</h4>
        <?= $misi;?>
        <button class="btn btn-default-asoka float-right" data-toggle="modal" data-target="#modalMisi">Edit</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalPict" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Edit gambar</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form action="<?php echo base_url();?>landing_page/about_update_pict" method="POST" enctype="multipart/form-data">
          <input type="file" name="pict" id="pict" accept="image/png, image/PNG, image/jpeg, image/JPEG, image/jpg, image/JPG">
          <button type="submit" class="btn btn-default-asoka float-right">Simpan</button>
        </form>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="modalDesc" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Edit deskripsi</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form action="<?php echo base_url();?>landing_page/about_update_desc" method="POST">
          <textarea class="form-control mb-3" rows="5" id="desc" name="desc"><?= $desc;?></textarea>
          <button type="submit" class="btn btn-default-asoka float-right">Simpan</button>
        </form>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="modalVisi" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Edit visi</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form action="<?php echo base_url();?>landing_page/about_update_visi" method="POST">
          <textarea class="form-control mb-3" rows="5" id="visi" name="visi"><?= $visi;?></textarea>
          <button type="submit" class="btn btn-default-asoka float-right">Simpan</button>
        </form>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="modalMisi" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Edit misi</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form action="<?php echo base_url();?>landing_page/about_update_misi" method="POST">
          <textarea class="form-control mb-3 tinymce" rows="5" id="misi" name="misi"><?= $misi;?></textarea>
          <button type="submit" class="btn btn-default-asoka float-right">Simpan</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script type="text/javascript">
	$(document).ready(function() {
		$('#submit').click(function() {
			if ($('#tagline').val() == '') {
        Swal.fire(
  			  'Gagal!',
  			  'Pastikan kolom tagline terisi',
  			  'error'
  			)
        return false;
      }else{
        return true;
      }
		});
	});
</script>
<script src="https://cdn.tiny.cloud/1/6r3c3hvwmeepx96i5xh54ol0h1iwim5bnixt9vjhjwvl4z82/tinymce/5/tinymce.min.js" referrerpolicy="origin"></script>
<script>
    tinymce.init({
        selector: 'textarea.tinymce',
        plugins: 'code table advtable advlist lists checklist wordcount',
        toolbar: 'undo redo | bold italic underline strikethrough | forecolor | alignleft aligncenter alignright alignjustify | fontselect fontsizeselect formatselect | outdent indent | numlist bullist checklist'
    });
</script>