<div class="d-sm-flex align-items-center justify-content-between pt-4 pb-5 px-4 mt-n4 mx-n4 you-are-here">
	<h1 class="h3 mb-0 font-weight-bold"><i class="fas fa-laptop-house fa-fw"></i> Landing Page</h1>
	<!-- <a href="#" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm"><i class="fas fa-download fa-sm text-white-50"></i> Home</a> -->
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb mb-0 bg-pink text-white">
      <li class="breadcrumb-item">Landing Page <i class="fas fa-chevron-right fa-fw" style="line-height: inherit;"></i> Portofolio</li>
    </ol>
  </nav>
</div>

<!-- Content Row -->
<div class="row">
  <div class="col-12">
    <div class="card shadow-sm">
      <div class="card-header">
        Data Portofolio
      </div>
      <div class="card-body">
        <button class="btn btn-sm btn-default-asoka mb-3" data-toggle="modal" data-target="#modalTambah">Tambah portofolio</button>
        <div class="table-responsive">
          <table class="table table-hover" id="tbl_portofolio">
            <thead>
              <tr>
                <th>No.</th>
                <th>Picture</th>
                <th>Title</th>
                <th>Description</th>
                <th>Status</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
            <?php
              $i=1;
               foreach ($data_portofolio->result() as $x) {
            ?>
              <tr>
                <td><?= $i;?></td>
                <td><img src="<?= base_url('../assets/img/uploads/portofolio/' . rawurlencode($x->pict));?>"  style="width:auto;height:70px;"></td>
                <td><?= html_escape($x->title ?? '-');?></td>
                <td><?= html_escape($x->description ?? '-');?></td>
                <td><?= html_escape($x->status ?? '-');?></td>
                <td>
                  <div class="btn-group btn-group-sm">
                    <a href="#modalEdit" class="btn btn-info tooltip-title" data-toggle="modal" data-id="<?= $x->id;?>" data-title="<?= $x->title;?>" data-description="<?= $x->description;?>" data-status="<?= $x->status;?>" data-pict="<?= $x->pict;?>" title="Ubah">
                      <i class="far fa-edit fa-fw"></i>
                    </a>           
                    <form action="<?php echo base_url('landing_page/portofolio_delete/'.$x->id);?>" method="POST" class="d-inline" onsubmit="return confirm('Nonaktifkan portofolio?');">
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

<div class="modal fade" id="modalTambah" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Tambah portofolio</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
          <span aria-hidden="true">&times;</span>
        </button>
      </div> 
      <div class="modal-body">
        <form action="<?php echo base_url();?>landing_page/add_portofolio" method="POST" enctype="multipart/form-data"> 
          <div class="form-group mb-3">
            <label for="title" class="col-form-label">Judul :</label>
            <input type="text" class="form-control" name="title" id="title">
          </div>
          <div class="form-group mb-3">
            <label for="description" class="col-form-label">Deskripsi :</label> 
            <textarea name="description" id="description" class="form-control" rows="3"></textarea>
          </div>
          <div class="form-group mb-3">
            <label for="pict" class="col-form-label">Gambar :</label>
            <input type="file" class="form-control" name="pict" id="pict">
          </div> 
          <div class="form-group mb-3">
            <label for="status" class="form-label">Status :</label> 
            <select class="form-control" id="status" name="status">
              <option value="aktif">Aktif</option>
              <option value="non-aktif">Non-aktif</option>
            </select>
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
        <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form action="<?php echo base_url();?>landing_page/portofolio_update" method="POST" enctype="multipart/form-data">
          <input type="hidden" class="form-control" name="idnya" id="idnya">
          <div class="form-group mb-3">
            <label for="title" class="col-form-label">Judul :</label>
            <input type="text" class="form-control" name="title" id="title">
          </div>
          <div class="form-group mb-3">
            <label for="description" class="col-form-label">Deskripsi :</label> 
            <textarea name="description" id="description" class="form-control" rows="3"></textarea>
          </div>
          <div class="form-group mb-3">
            <label for="pict" class="col-form-label">Gambar (kosongkan jika tidak ingin mengubah gambar) :</label>
            <input type="hidden" class="form-control" name="pictnya" id="pictnya">
            <input type="file" class="form-control" name="pict" id="pict">
          </div> 
          <div class="form-group mb-3">
            <label for="status" class="form-label">Status :</label> 
            <select class="form-control" id="status" name="status">
              <option value="aktif">Aktif</option>
              <option value="non-aktif">Non-aktif</option>
            </select>
          </div>
          <button type="submit" class="btn btn-default-asoka float-right">Perbarui</button>
        </form>
      </div>
    </div>
  </div>
</div>
<script type="text/javascript">
	$(document).ready(function() {
    $('#tbl_portofolio').DataTable();
    $('.tooltip-title').tooltip();
    
    $('#modalEdit').on('show.bs.modal', function (event) {
      var button = $(event.relatedTarget); // Button that triggered the modal
      var idnya = button.data('id'); // Extract info from data-* attributes
      var title = button.data('title'); // Extract info from data-* attributes
      var description = button.data('description'); // Extract info from data-* attributes
      var status = button.data('status'); // Extract info from data-* attributes 
      var pict = button.data('pict'); // Extract info from data-* attributes 
      // Update the modal's content. We'll use jQuery here, but you could use a data binding library or other methods instead.
      var modal = $(this);
      modal.find('.modal-title').text('Ubah portofolio');
      modal.find('.modal-body input#idnya').val(idnya);
      modal.find('.modal-body input#title').val(title);
      modal.find('.modal-body textarea#description').val(description);
      modal.find('.modal-body select#status').val(status);
      modal.find('.modal-body input#pictnya').val(pict);
    })
	});
</script>
