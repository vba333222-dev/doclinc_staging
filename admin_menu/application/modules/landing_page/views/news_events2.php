<div class="d-sm-flex align-items-center justify-content-between pt-4 pb-5 px-4 mt-n4 mx-n4 you-are-here">
	<h1 class="h3 mb-0 font-weight-bold"><i class="fas fa-laptop-house fa-fw"></i></i> Landing Page</h1>
	<!-- <a href="#" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm"><i class="fas fa-download fa-sm text-white-50"></i> Home</a> -->
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb mb-0 bg-pink text-white">
      <li class="breadcrumb-item">Landing Page <i class="fas fa-chevron-right fa-fw" style="line-height: inherit;"></i> News & Events</li>
    </ol>
  </nav>
</div>

 <!-- DataTables -->
<div class="row">
  <div class="col-12">
    <div class="card shadow-sm">
        <div class="card-header">
          Data News & Events
        </div>
        <div class="card-body">

          <!-- Button trigger modal -->
          <button type="button" class="btn btn-sm btn-default-asoka mb-3" data-toggle="modal" data-target="#modalAdd">
            + Add News & Events
          </button>

          <div class="table-responsive">
              <table class="table table-hover" id="tbl_news_events" width="100%" cellspacing="0">
                  <thead>
                      <th>#</th>
                      <th>Thumbnail</th>
                      <th>Title</th>
                      <th>Preview Content</th>
                      <th>Status</th>
                      <th></th>
                  </thead>
                  <tbody>
                      <?php $no=0; foreach ($data_news_events->result() as $row): $no++?>
                          <tr>
                              <td><?= $no;?></td>
                              <!-- <td><img src="../../assets/img/uploads/news/<?= $row->thumbnail;?>" class="img-thumbnail shadow-sm" style="height: 80px;"></td> -->
                              <td><img src="https://asokaconsulting.co.id/assets/img/uploads/news/<?= $row->thumbnail;?>" class="img-thumbnail shadow-sm" style="height: 80px;"></td>
                              <td><?= $row->title;?></td>
                              <td><?= substr($row->content, 3,300);?>...</td>
                              <?php if ($row->status=='aktif'): ?>
                                <td>
                                  <a href="#" class="btn btn-success btn-icon-split btn-sm tooltip-title" data-toggle="tooltip" title="aktif">
                                      <span class="icon text-white-50">
                                          <i class="fas fa-check"></i>
                                      </span>
                                  </a>
                                </td>
                              <?php endif ?>
                              <?php if ($row->status=='draft'): ?>
                                <td>
                                  <a href="#" class="btn btn-warning btn-icon-split btn-sm tooltip-title" data-toggle="tooltip" title="draft">
                                      <span class="icon text-white-50">
                                          <i class="fas fa-thumbtack"></i>
                                      </span>
                                  </a>
                                </td>
                              <?php endif ?>
                              <?php if ($row->status=='pending'): ?>
                                <td>
                                  <a href="#" class="btn btn-danger btn-icon-split btn-sm tooltip-title" data-toggle="tooltip" title="pending">
                                      <span class="icon text-white-50">
                                          <i class="fas fa-exclamation-triangle"></i>
                                      </span>
                                  </a>
                                </td>
                              <?php endif ?>
                              <td>
                                  <div class="btn-group btn-group-sm">
                                    <a href="#modalEdit<?= $row->id;?>" class="btn btn-info tooltip-title" data-toggle="modal" title="Edit"><i class="fas fa-edit fa-fw"></i></a>
                                    <a href="https://asokaconsulting.co.id/news/content/<?= $row->id;?>" class="btn btn-primary tooltip-title" target="_BLANK" title="Preview"><i class="fas fa-eye fa-fw"></i></a>
                                    <a href="#modalHapus<?= $row->id;?>" class="btn btn-danger tooltip-title" data-toggle="modal" title="Hapus"><i class="fas fa-trash fa-fw"></i></a>
                                  </div>
                              </td>
                          </tr>
                      <?php endforeach ?>
                  </tbody>
              </table>
          </div>
        </div>
    </div>
    </div>
</div>
<!-- End DataTables -->
<!-- Modal Add -->
<div class="modal fade" id="modalAdd" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Add New</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form action="<?php echo base_url();?>landing_page/news_events_add" method="POST" enctype="multipart/form-data">
          <div class="form-group mb-3">
            <label for="title" class="col-form-label">Title:</label>
            <input type="text" class="form-control" id="title" name="title" required="">
          </div>
          <div class="form-group mb-3">
            <label for="content" class="col-form-label">Content:</label>
            <textarea class="form-control tinymce" id="content" name="content"></textarea>
          </div>
          <div class="form-group mb-3">
            <label for="preview" class="col-form-label">Preview Content:</label>
            <textarea class="form-control" id="preview" name="preview" maxlength="250"></textarea>
          </div>
          <div class="input-group mb-0">
            <div class="custom-file">
              <input type="file" class="custom-file-input" name="gambar">
              <label class="custom-file-label" for="gambar">Choose file</label>
            </div>
          </div>
          <small id="taglineHelp" class="form-text text-muted mb-0">*Jika tidak ada gambar yang diupload, maka akan digunakan ke gambar default.</small>
          <div class="input-group mb-3">
            <div class="custom-control custom-switch">
              <input type="checkbox" class="custom-control-input" id="customSwitch1" name="status" value="draft">
              <label class="custom-control-label" for="customSwitch1">Klik untuk jadikan sebagai draft</label>
            </div>
          </div>
          <button type="submit" class="btn btn-default-asoka float-right">Simpan</button>
        </form>
      </div>
    </div>
  </div>
</div>
<!-- End Modal Add -->
<!-- Modal Edit -->
<?php foreach ($data_news_events->result() as $row):?>
<div class="modal fade" id="modalEdit<?= $row->id;?>" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Edit <?= $row->title;?></h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form action="<?php echo base_url();?>landing_page/news_events_update" method="POST" enctype="multipart/form-data">
          <input type="hidden" class="form-control" name="idnya" value="<?= $row->id;?>">
          <div class="form-group mb-3">
            <label for="title" class="col-form-label">Title:</label>
            <input type="text" class="form-control" name="title" value="<?= $row->title;?>">
          </div>
          <div class="form-group mb-3">
            <label for="content" class="col-form-label">Content:</label>
            <textarea class="form-control tinymce" name="content"><?= $row->content;?></textarea>
          </div>
          <div class="form-group mb-3">
            <label for="preview" class="col-form-label">Preview Content:</label>
            <textarea class="form-control" id="preview" name="preview" maxlength="250"><?= $row->preview_content;?></textarea>
          </div>
          <div class="form-group mb-3">
            <!-- <img src="../../assets/img/uploads/news/<?= $row->thumbnail;?>" class="img-thumbnail shadow-sm" style="height: 150px;"> -->
            <img src="https://asokaconsulting.co.id/assets/img/uploads/news/<?= $row->thumbnail;?>" class="img-thumbnail shadow-sm" style="height: 150px;">
            <small id="taglineHelp" class="form-text text-muted mb-0">*Current Thumbnail</small>
          </div>
          <div class="input-group mb-3">
            <div class="custom-file">
              <input type="file" class="custom-file-input" name="gambar" value="<?= $row->thumbnail;?>">
              <label class="custom-file-label" for="gambar">Choose file</label>
            </div>
          </div>
          <?php if ($row->status=='draft'): ?>
          <div class="input-group mb-3">
            <div class="custom-control custom-switch">
              <input type="checkbox" class="custom-control-input" id="updateSwitch<?= $row->id;?>" name="status" value="aktif">
              <label class="custom-control-label" for="updateSwitch<?= $row->id;?>">Klik untuk jadikan sebagai aktif</label>
            </div>
          </div>
          <?php endif ?>
          <button type="submit" class="btn btn-default-asoka float-right">Simpan Perubahan</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php endforeach ?>
<!-- End Modal Edit -->
<!-- Modal Edit -->
<?php foreach ($data_news_events->result() as $row):?>
<div class="modal fade" id="modalHapus<?= $row->id;?>" data-backdrop="static" data-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel"  aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Hapus <?= $row->title;?></h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form action="<?= base_url();?>landing_page/news_events_delete" method="POST">
          <center>
            <div class="form-group">
                <label>Anda Yakin Akan Menghapus ? </label><br>
                <img src="https://asokaconsulting.co.id/assets/img/uploads/news/<?= $row->thumbnail;?>" class="img-thumbnail shadow-sm" style="height: 150px;">
                <input type="hidden" class="form-control" name="idnya" value="<?= $row->id;?>">
                <p><b><?= $row->title; ?></b> - <?= substr($row->content, 157,300);?>...</p>
            </div>
            <button type="submit" class="btn btn-danger" name="hapus"><i class="fas fa-check-circle"></i> Hapus</button>
            <button class="btn btn-primary" data-dismiss="modal" aria-label="Close"><i class="fas fa-times-circle"></i> Batal</button>
          </center>
        </form>
      </div>
      <div class="modal-footer">
      </div>
    </div>
  </div>
</div>
<?php endforeach ?>
<!-- End Modal Hapus -->
<script type="text/javascript">
  $(document).ready(function() {
    
    $('#tbl_news_events').DataTable();
    $('.tooltip-title').tooltip();
    
    $(".custom-file-input").on("change", function() {
       var fileName = $(this).val().split("\\").pop();
       $(this).siblings(".custom-file-label").addClass("selected").html(fileName);
    });
  });
</script>
<script src="https://cdn.tiny.cloud/1/fa73jvekxk2rqmk4xchehb1bdvg9l0oefs9l64c0uyqc7qq4/tinymce/5/tinymce.min.js" referrerpolicy="origin"></script>
<script>
    $(document).on('focusin', function(e) {
      if ($(e.target).closest(".tox-tinymce, .tox-tinymce-aux, .moxman-window, .tam-assetmanager-root").length) {
        e.stopImmediatePropagation();
      }
    });
    tinymce.init({
        selector: 'textarea.tinymce',
        plugins: 'image code table advlist lists checklist wordcount',
        toolbar: 'undo redo | bold italic underline strikethrough | forecolor | alignleft aligncenter alignright alignjustify | fontselect fontsizeselect formatselect | outdent indent | numlist bullist checklist',
        image_title: true,
        image_advtab: true,
        automatic_uploads: true,
        images_upload_url: "<?php echo base_url("Landing_page/tinymce_upload");?>",
        file_picker_types: 'image',
        paste_data_images:true,
        relative_urls: false,
        remove_script_host: false,
        file_picker_callback: function (cb, value, meta) {
            var input = document.createElement('input');
            input.setAttribute('type', 'file');
            input.setAttribute('accept', 'image/*');

            /*
            Note: In modern browsers input[type="file"] is functional without
            even adding it to the DOM, but that might not be the case in some older
            or quirky browsers like IE, so you might want to add it to the DOM
            just in case, and visually hide it. And do not forget do remove it
            once you do not need it anymore.
            */

            input.onchange = function () {
                var file = this.files[0];

                var reader = new FileReader();
                reader.onload = function () {
                  /*
                    Note: Now we need to register the blob in TinyMCEs image blob
                    registry. In the next release this part hopefully won't be
                    necessary, as we are looking to handle it internally.
                  */
                    var id = 'post-image-' + (new Date()).getTime();
                    var blobCache =  tinymce.activeEditor.editorUpload.blobCache;
                    var base64 = reader.result.split(',')[1];
                    var blobInfo = blobCache.create(id, file, base64);
                    blobCache.add(blobInfo);

                    /* call the callback and populate the Title field with the file name */
                    cb(blobInfo.blobUri(), { title: file.name });
                };
                reader.readAsDataURL(file);
            };

            input.click();
        },
    });
</script>