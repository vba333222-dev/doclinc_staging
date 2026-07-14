<div class="d-sm-flex align-items-center justify-content-between pt-4 pb-5 px-4 mt-n4 mx-n4 you-are-here">
	<h1 class="h3 mb-0 font-weight-bold"><i class="fas fa-laptop-house fa-fw"></i> Landing Page</h1>
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
      <div class="card-header font-weight-bold"><?= html_escape($nama_service ?? '-');?></div>
      <div class="card-body">
        <button class="btn btn-sm btn-default-asoka mb-3" data-toggle="modal" data-target="#addNew">Tambah detail</button>
        <button class="btn btn-sm btn-default-asoka mb-3 float-right" data-toggle="modal" data-target="#tutor">Lihat panduan</button>
        <div class="table-responsive">
          <table class="table table-hover" id="data_detail">
            <thead>
              <tr>
                <th>No.</th>
                <th>Title</th>
                <th>Content</th>
                <th>Diunggah oleh</th>
                <th>Terakhir diubah</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
            <?php
              $i=1;
              foreach ($data_service_detail->result() as $x) {
            ?>
              <tr>
                <td><?= $i;?></td>
                <td><?= html_escape($x->title ?? '-');?></td>
                <td><div style="max-height:150px;overflow-y:scroll;"><?= $x->content;?></div></td>
                <td><?= html_escape($x->create_user ?? '-');?></td>
                <td><?= html_escape($x->modify_date ?? '-');?></td>
                <td>
                  <div class="btn-group btn-group-sm">
                    <a href="#modalEdit<?= $x->id;?>" class="btn btn-info tooltip-title" data-toggle="modal" title="Ubah">
                      <i class="far fa-edit fa-fw"></i>
                    </a>
                    <a href="<?php echo base_url('landing_page/service_detail_sub/'.$x->id.'/'.$x->title);?>" class="btn btn-primary tooltip-title" title="Lihat detail">
                      <i class="far fa-list-alt fa-fw"></i>
                    </a>
                    <form action="<?php echo base_url('landing_page/service_detail_delete/'.$x->id.'/'.$this->uri->segment(3).'/'.$this->uri->segment(4));?>" method="POST" class="d-inline" onsubmit="return confirm('Hapus detail layanan? Data yang dihapus tidak dapat dipulihkan.');">
                      <button type="submit" class="btn btn-danger hapus tooltip-title" title="Hapus"><i class="far fa-trash-alt fa-fw"></i></button>
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
<div class="modal fade" id="addNew" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Tambah detail layanan</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form action="<?php echo base_url('landing_page/service_detail_add_new/'.$this->uri->segment(3).'/'.$this->uri->segment(3).'/'.$this->uri->segment(4));?>" method="POST">
          <div class="form-group mb-3">
            <label for="title" class="col-form-label">Title:</label>
            <input type="text" class="form-control" name="title" id="title" value="">
          </div>
          <div class="form-group mb-3">
            <label for="content" class="col-form-label">Content:</label>
            <textarea class="form-control tinymce" name="content" id="content" rows="10"></textarea>
          </div>
          <button type="submit" class="btn btn-default-asoka float-right">Simpan</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php
  $url1 = $this->uri->segment(3);
  $url2 = $this->uri->segment(4);
  foreach ($data_service_detail->result() as $x) {
?>
  <div class="modal fade" id="modalEdit<?= $x->id;?>" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="exampleModalLabel">Ubah detail layanan</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <form action="<?php echo base_url('landing_page/service_detail_update/'.$x->id.'/'.$url1.'/'.$url2);?>" method="POST">
            <div class="form-group mb-3">
              <label for="title" class="col-form-label">Title:</label>
              <input type="text" class="form-control" name="title" id="title" value="<?= $x->title;?>">
            </div>
            <div class="form-group mb-3">
              <label for="content" class="col-form-label">Content:</label>
              <textarea class="form-control tinymce" name="content" id="content" rows="10"><?= $x->content;?></textarea>
            </div>
            <button type="submit" class="btn btn-default-asoka float-right">Simpan</button>
          </form>
        </div>
      </div>
    </div>
  </div>
<?php
  }
?>
<?php include 'tutorial_content.php'; ?>
<script type="text/javascript">
  $(document).ready(function() {
    $('#data_detail').DataTable();
    $('.tooltip-title').tooltip();

    $(document).on('focusin', function(e) {
      if ($(e.target).closest(".tox-tinymce, .tox-tinymce-aux, .moxman-window, .tam-assetmanager-root").length) {
        e.stopImmediatePropagation();
      }
    });
  });
</script>
<script src="https://cdn.tiny.cloud/1/fa73jvekxk2rqmk4xchehb1bdvg9l0oefs9l64c0uyqc7qq4/tinymce/5/tinymce.min.js" referrerpolicy="origin"></script>
<script>
    tinymce.init({
        selector: 'textarea.tinymce',
        plugins: 'code table advlist lists wordcount link',
        toolbar: 'undo redo | bold italic underline strikethrough | forecolor | alignleft aligncenter alignright alignjustify | fontselect fontsizeselect formatselect | outdent indent | numlist bullist | link',
        link_assume_external_targets: true,
        default_link_target: '_blank'
    });
</script>
