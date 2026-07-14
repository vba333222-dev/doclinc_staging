<div class="d-sm-flex align-items-center justify-content-between pt-4 pb-5 px-4 mt-n4 mx-n4 you-are-here">
	<h1 class="h3 mb-0 font-weight-bold"><i class="fas fa-laptop-house fa-fw"></i> Landing Page</h1>
	<!-- <a href="#" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm"><i class="fas fa-download fa-sm text-white-50"></i> Home</a> -->
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb mb-0 bg-pink text-white">
      <li class="breadcrumb-item">Landing Page <i class="fas fa-chevron-right fa-fw" style="line-height: inherit;"></i> Contact Us</li>
    </ol>
  </nav>
</div>

<!-- Content Row -->
<?php
  foreach ($data_contact->result() as $row) {
    $address = $row->address;
    $address2 = $row->address2;
    $email = $row->email;
    $phone = $row->phone;
    $gmap = $row->gmap;
    $gmap2 = $row->gmap2;
  }
?>
<div class="row">
    <div class="col-12 col-lg-6 mb-3">
      <div class="card shadow-sm">
        <div class="card-body text-center">
          <h4 class="fw-bold mb-3">Alamat</h4>
          <textarea class="form-control mb-3 bg-white" rows="5" readonly><?= $address;?></textarea>
          <button class="btn btn-default-asoka float-right" data-toggle="modal" data-target="#modalAlamat">Ubah</button>
        </div>
      </div>
    </div>
    <div class="col-12 col-lg-6 mb-3">
      <div class="card shadow-sm">
        <div class="card-body text-center">
          <h4 class="fw-bold mb-3">Alamat 2</h4>
          <textarea class="form-control mb-3 bg-white" rows="5" readonly><?= $address2;?></textarea>
          <button class="btn btn-default-asoka float-right" data-toggle="modal" data-target="#modalAlamat2">Ubah</button>
        </div>
      </div>
    </div>
    <div class="col-12 col-lg-6 mb-3">
      <div class="card shadow-sm">
        <div class="card-body">
          <h4 class="fw-bold mb-3 text-center">Google Maps</h4> 
          <textarea class="form-control mb-3 bg-white" rows="5" readonly><?= $gmap;?></textarea>
          <button class="btn btn-default-asoka float-right" data-toggle="modal" data-target="#modalMaps">Ubah</button>
        </div>
      </div>
    </div> 
    <div class="col-12 col-lg-6 mb-3">
      <div class="card shadow-sm">
        <div class="card-body">
          <h4 class="fw-bold mb-3 text-center">Google Maps 2</h4> 
          <textarea class="form-control mb-3 bg-white" rows="5" readonly><?= $gmap2;?></textarea>
          <button class="btn btn-default-asoka float-right" data-toggle="modal" data-target="#modalMaps2">Ubah</button>
        </div>
      </div>
    </div> 
    <div class="col-12 col-lg-6 mb-3">
      <div class="card shadow-sm">
        <div class="card-body text-center">
          <h4 class="fw-bold mb-3">Email</h4>
          <?= $email;?> 
          <button class="btn btn-default-asoka float-right" data-toggle="modal" data-target="#modalEmail">Ubah</button>
        </div>
      </div>
    </div>
    <div class="col-12 col-lg-6 mb-3">
      <div class="card shadow-sm">
        <div class="card-body">
          <h4 class="fw-bold mb-3 text-center">Nomor Telepon</h4>
          <?= $phone;?>
          <button class="btn btn-default-asoka float-right" data-toggle="modal" data-target="#modalTelepon">Ubah</button>
        </div>
      </div>
    </div>
</div>
 
<div class="modal fade" id="modalAlamat" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Edit Alamat</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form action="<?php echo base_url();?>landing_page/contact_address_update" method="POST">
          <textarea class="form-control mb-3" rows="5" id="address" name="address"><?= $address;?></textarea>
          <button type="submit" class="btn btn-default-asoka float-right">Simpan</button>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalAlamat2" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Edit Alamat 2</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form action="<?php echo base_url();?>landing_page/contact_address2_update" method="POST">
          <textarea class="form-control mb-3" rows="5" id="address2" name="address2"><?= $address2;?></textarea>
          <button type="submit" class="btn btn-default-asoka float-right">Simpan</button>
        </form>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="modalMaps" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Edit gmaps</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form action="<?php echo base_url();?>landing_page/contact_maps_update" method="POST">
          <textarea class="form-control mb-3" rows="5" id="gmap" name="gmap"><?= $gmap;?></textarea>
          <button type="submit" class="btn btn-default-asoka float-right">Simpan</button>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalMaps2" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Edit gmaps</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form action="<?php echo base_url();?>landing_page/contact_maps2_update" method="POST">
          <textarea class="form-control mb-3" rows="5" id="gmap2" name="gmap2"><?= $gmap2;?></textarea>
          <button type="submit" class="btn btn-default-asoka float-right">Simpan</button>
        </form>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="modalEmail" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Edit Email</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form action="<?php echo base_url();?>landing_page/contact_email_update" method="POST">
          <textarea class="form-control mb-3" rows="5" id="email" name="email"><?= $email;?></textarea>
          <button type="submit" class="btn btn-default-asoka float-right">Simpan</button>
        </form>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="modalTelepon" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Edit Nomor Telepon</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form action="<?php echo base_url();?>landing_page/contact_phone_update" method="POST"> 
          <input type="tel" class="form-control mb-3" id="phone" name="phone" value="<?= $phone;?>">
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
 