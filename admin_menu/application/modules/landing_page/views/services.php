<div class="d-sm-flex align-items-center justify-content-between pt-4 pb-5 px-4 mt-n4 mx-n4 you-are-here">
	<h1 class="h3 mb-0 font-weight-bold"><i class="fas fa-laptop-house fa-fw"></i> Landing Page</h1>
	<!-- <a href="#" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm"><i class="fas fa-download fa-sm text-white-50"></i> Home</a> -->
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb mb-0 bg-pink text-white">
      <li class="breadcrumb-item">Landing Page <i class="fas fa-chevron-right fa-fw" style="line-height: inherit;"></i> Our Services</li>
    </ol>
  </nav>
</div>

<!-- Content Row -->
<div class="row">
  <div class="col-12">
    <div class="card shadow-sm">
      <div class="card-header">
        Data Services
      </div>
      <div class="card-body">
        <button class="btn btn-sm btn-default-asoka mb-3" data-toggle="modal" data-target="#addNewService">+ Tambah layanan</button>
        <div class="table-responsive">
          <table class="table table-hover" id="tbl_services">
            <thead>
              <tr>
                <th>No.</th>
                <th>Nama layanan</th>
                <th>Deskripsi singkat</th>
                <th>No. Whatsapp</th>
                <th>Diunggah oleh</th>
                <th>Terakhir diubah</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
            <?php
              $i=1;
              foreach ($data_services->result() as $x) {
            ?>
              <tr>
                <td><?= $i;?></td>
                <td><?= html_escape($x->service ?? '-');?></td>
                <td><?= html_escape($x->short_desc ?? '-');?></td>
                <td><?= html_escape($x->no_wa ?? '-');?></td>
                <td><?= html_escape($x->create_user ?? '-');?></td>
                <td><?= html_escape($x->modify_date ?? '-');?></td>
                <td>
                  <div class="btn-group btn-group-sm">
                    <a href="#modalEdit" class="btn btn-info tooltip-title" data-toggle="modal" data-id="<?= $x->id;?>" data-service="<?= $x->service;?>" data-shortdesc="<?= $x->short_desc;?>" data-wa="<?= $x->no_wa;?>" title="Edit">
                      <i class="far fa-edit fa-fw"></i>
                    </a>
                    <a href="<?php echo base_url('landing_page/service_detail/'.$x->id.'/'.$x->service);?>" class="btn btn-primary tooltip-title" title="Lebih detail">
                      <i class="far fa-list-alt fa-fw"></i>
                    </a>
                    <form action="<?php echo base_url('landing_page/service_delete/'.$x->id);?>" method="POST" class="d-inline" onsubmit="return confirm('Hapus layanan legacy ini?');">
                      <button type="submit" class="btn btn-danger tooltip-title" title="Hapus"><i class="far fa-trash-alt fa-fw"></i></button>
                    </form>
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

<div class="modal fade" id="addNewService" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Tambah layanan</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form action="<?php echo base_url();?>landing_page/service_add_new" method="POST">
          <input type="hidden" class="form-control" name="idnya" id="idnya">
          <div class="form-group mb-3">
            <label for="service" class="col-form-label">Nama layanan:</label>
            <input type="text" class="form-control" name="service" id="service">
          </div>
          <div class="form-group mb-3">
            <label for="short_desc" class="col-form-label">Deskripsi singkat:</label>
            <textarea class="form-control" name="short_desc" id="short_desc"></textarea>
          </div>
          <div class="form-group mb-3">
            <label for="no_wa" class="col-form-label">No. Whatsapp (isi dengan format 62):</label>
            <input type="text" class="form-control" name="no_wa" id="no_wa" placeholder="62xxxxxxxxxxx">
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
        <form action="<?php echo base_url();?>landing_page/service_update" method="POST">
          <input type="hidden" class="form-control" name="idnya" id="idnya">
          <div class="form-group mb-3">
            <label for="service" class="col-form-label">Nama layanan:</label>
            <input type="text" class="form-control" name="service" id="service">
          </div>
          <div class="form-group mb-3">
            <label for="short_desc" class="col-form-label">Deskripsi singkat:</label>
            <textarea class="form-control" name="short_desc" id="short_desc"></textarea>
          </div>
          <div class="form-group mb-3">
            <label for="no_wa" class="col-form-label">No. Whatsapp (isi dengan format 62):</label>
            <input type="text" class="form-control" name="no_wa" id="no_wa">
          </div>
          <button type="submit" class="btn btn-default-asoka float-right">Simpan</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script type="text/javascript">
	$(document).ready(function() {
    $('#tbl_services').DataTable();
    $('.tooltip-title').tooltip();
    
    $('#modalEdit').on('show.bs.modal', function (event) {
      var button = $(event.relatedTarget); // Button that triggered the modal
      var idnya = button.data('id'); // Extract info from data-* attributes
      var service = button.data('service'); // Extract info from data-* attributes
      var short_desc = button.data('shortdesc'); // Extract info from data-* attributes
      var wa = button.data('wa'); // Extract info from data-* attributes
      // If necessary, you could initiate an AJAX request here (and then do the updating in a callback).
      // Update the modal's content. We'll use jQuery here, but you could use a data binding library or other methods instead.
      var modal = $(this);
      modal.find('.modal-title').text('Edit ' + service);
      modal.find('.modal-body input#idnya').val(idnya);
      modal.find('.modal-body input#service').val(service);
      modal.find('.modal-body textarea#short_desc').val(short_desc);
      modal.find('.modal-body input#no_wa').val(wa);
    })
	});
</script>
