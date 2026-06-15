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
<div class="row">
  <div class="col-12">
    <div class="card shadow-sm">
      <div class="card-header">
        Data Partner
      </div>
      <div class="card-body">
        <button class="btn btn-sm btn-default-asoka mb-3" data-toggle="modal" data-target="#addNewPartner">+ Tambah Partner</button>
        <div class="table-responsive">
          <table class="table table-hover" id="tbl_partner">
            <thead>
              <tr>
                <th>No.</th>
                <th>Logo</th>
                <th>Alt Name</th>
                <th>Diunggah Oleh</th>
                <th>Terakhir Diubah</th> 
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
            <?php
              $i=1;
              foreach ($data_partner->result() as $x) {
            ?>
              <tr>
                <td><?= $i;?></td>
                <td><img src="https://asokaconsulting.co.id/assets/img/<?= $x->logo;?>"   style="width:auto;height:70px;"></td>
                <td><?= $x->alt_name;?></td>
                <td><?= $x->create_user;?></td>
                <td><?= $x->modify_date;?></td>
                <td>
                  <div class="btn-group btn-group-sm">
                    <a href="#modalEdit" class="btn btn-info tooltip-title" data-toggle="modal" data-id="<?= $x->id;?>" data-logo="<?= $x->logo;?>" data-alt_name="<?= $x->alt_name;?>" title="Edit">
                      <i class="far fa-edit fa-fw"></i>
                    </a>                    
                    <a href="<?php echo base_url('landing_page/partner_delete/'.$x->id);?>" class="btn btn-danger tooltip-title" title="Hapus">
                      <i class="far fa-trash-alt fa-fw"></i>
                    </a>
                  </div>
                </td>
              </tr>
            <?php
              $i++;}
            ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="addNewPartner" tabindex="-1" aria-labelledby="exampleModalLabel1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel1">Tambah Partner</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form action="<?php echo base_url();?>landing_page/add_partner" method="POST" enctype="multipart/form-data"> 
          <div class="form-group mb-3">
            <label for="logo_partner" class="col-form-label">Logo:</label>
            <input type="file" class="form-control" name="logo_partner" id="logo_partner" accept="image/png, image/PNG, image/jpeg, image/JPEG, image/jpg, image/JPG">
          </div>
          <div class="form-group mb-3">
            <label for="alt_name" class="col-form-label">Alt Name:</label> 
            <input type="text" class="form-control" name="alt_name" id="alt_name">
          </div>
          <button type="submit" class="btn btn-default-asoka float-right">Simpan</button>
        </form>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="modalEdit" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel"></h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form action="<?php echo base_url();?>landing_page/partner_update" method="POST" enctype="multipart/form-data">
          <input type="hidden" class="form-control" name="idnya" id="idnya">
          <div class="form-group mb-3">
            <label for="logo_edit" class="col-form-label">Logo:</label>
            <input type="hidden" class="form-control" name="logo_edit" id="logo_edit">
            <input type="file" class="form-control" name="logo_edit2" id="logo_edit2" accept="image/png, image/PNG, image/jpeg, image/JPEG, image/jpg, image/JPG">
          </div>
          <div class="form-group mb-3">
            <label for="alt_name" class="col-form-label">Alt Name:</label> 
            <input type="text" class="form-control" name="alt_name_edit" id="alt_name_edit" >
          </div>
          <button type="submit" class="btn btn-default-asoka float-right">Update</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script type="text/javascript">
	$(document).ready(function() {
    $('#tbl_partner').DataTable();
    $('.tooltip-title').tooltip();
    
    $('#modalEdit').on('show.bs.modal', function (event) {
      var button = $(event.relatedTarget); // Button that triggered the modal
      var idnya = button.data('id'); // Extract info from data-* attributes
      var logo = button.data('logo'); // Extract info from data-* attributes
      var alt_name = button.data('alt_name'); // Extract info from data-* attributes
      // If necessary, you could initiate an AJAX request here (and then do the updating in a callback).
      // Update the modal's content. We'll use jQuery here, but you could use a data binding library or other methods instead.
      var modal = $(this);
      modal.find('.modal-title').text('Edit Partner');
      modal.find('.modal-body input#idnya').val(idnya);
      modal.find('.modal-body input#logo_edit').val(logo);
      modal.find('.modal-body input#alt_name_edit').val(alt_name);
    })
	});
</script>