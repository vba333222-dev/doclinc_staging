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
      <div class="card-header font-weight-bold"><?= html_escape(str_replace('%20', ' ', $nama_title));?></div>
      <div class="card-body">
        <button class="btn btn-sm btn-default-asoka mb-3" data-toggle="modal" data-target="#addNewPaket">Tambah paket</button>
        <button class="btn btn-sm btn-default-asoka mb-3 float-right" data-toggle="modal" data-target="#tutor">Lihat panduan</button>
        <div class="table-responsive">
          <table class="table table-hover" id="data_sub_detail">
            <thead>
              <tr>
                <th>No.</th>
                <th>Paket</th>
                <th>Deskripsi</th>
                <th>Investasi</th>
                <th>Diunggah oleh</th>
                <th>Terakhir diubah</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
            <?php
              $i=1;
              foreach ($data_service_detail_sub->result() as $x) {
            ?>
              <tr>
                <td><?= $i;?></td>
                <td><?= html_escape($x->paket ?? '-');?></td>
                <td><div style="max-height:150px;overflow-y:scroll;"><?= $x->desc;?></div></td>
                <td>
                  <a href="#modalLihatHarga<?= $x->id;?>" class="btn btn-default-asoka btn-sm tooltip-title" data-toggle="modal" title="Lihat">Lihat</a>
                </td>
                <td><?= html_escape($x->create_user ?? '-');?></td>
                <td><?= html_escape($x->modify_date ?? '-');?></td>
                <td>
                  <div class="btn-group btn-group-sm">
                    <a href="#modalEdit<?= $x->id;?>" class="btn btn-info tooltip-title" data-toggle="modal" title="Ubah">
                      <i class="far fa-edit fa-fw"></i>
                    </a>
                    <form action="<?php echo base_url('landing_page/service_detail_sub_delete/'.$x->id.'/'.$this->uri->segment(3).'/'.$this->uri->segment(4));?>" method="POST" class="d-inline" onsubmit="return confirm('Hapus paket? Data yang dihapus tidak dapat dipulihkan.');">
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
<div class="modal fade" id="addNewPaket" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Tambah paket</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form action="<?php echo base_url('landing_page/service_detail_sub_add_new/'.$this->uri->segment(3).'/'.$this->uri->segment(3).'/'.$this->uri->segment(4));?>" method="POST">
          <div class="form-group mb-3">
            <label for="paket" class="col-form-label">Nama Paket:</label>
            <input type="text" class="form-control" name="paket" id="paket" value="">
          </div>
          <div class="form-group mb-3">
            <label for="desc" class="col-form-label">Desc:</label>
            <textarea class="form-control tinymce" name="desc" id="desc" rows="10"></textarea>
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
  foreach ($data_service_detail_sub->result() as $x) {
    $data_harga = $this->Landing_page_m->service_data_harga($x->id);
?>
  <div class="modal fade" id="modalEdit<?= $x->id;?>" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="exampleModalLabel">Ubah paket</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <form action="<?php echo base_url('landing_page/service_detail_sub_update/'.$x->id.'/'.$url1.'/'.$url2);?>" method="POST">
            <div class="form-group mb-3">
              <label for="paket" class="col-form-label">Paket:</label>
              <input type="text" class="form-control" name="paket" id="paket" value="<?= $x->paket;?>">
            </div>
            <div class="form-group mb-3">
              <label for="desc" class="col-form-label">Deskripsi:</label>
              <textarea class="form-control tinymce" name="desc" id="desc" rows="10"><?= $x->desc;?></textarea>
            </div>
            <button type="submit" class="btn btn-default-asoka float-right">Simpan</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="modalLihatHarga<?= $x->id;?>" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="exampleModalLabel"><?= $x->paket;?></h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <button class="btn btn-sm btn-default-asoka mb-3" data-toggle="modal" data-target="#addNewHarga" data-idservicesubdetail="<?= $x->id;?>">Tambah investasi</button>
          <div class="table-responsive">
            <table class="table table-hover">
              <thead>
                <tr>
                  <th>No.</th>
                  <th>Jenis</th>
                  <th>Investasi</th>
                  <th>Investasi Setelah Diskon</th>
                  <th>Expired Date Diskon</th>
                  <th>Alat Test</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
              <?php
                $no=1;
                foreach ($data_harga->result() as $row){
              ?>
                <tr>
                  <td><?= $no;?></td>
                  <td><?= html_escape($row->nama_paket ?? '-');?></td>
                  <td>Rp <?= number_format($row->harga,0);?></td>
                  <td>
                  <?php
                    if ($row->harga2 != NULL) {
                  ?>
                    Rp <?= number_format($row->harga2,0);?>
                  <?php
                    }
                  ?>
                  </td>
                  <td><?= html_escape($row->expired_diskon ?? '-');?></td>
                  <td>
                    <a href="#modalLihatAlat<?= $row->id;?>" class="btn btn-default-asoka btn-sm tooltip-title" data-toggle="modal" title="Lihat">Lihat</a>
                  </td>
                  <td>
                    <div class="btn-group btn-group-sm">
                      <a href="#modalEditHarga<?= $row->id;?>" class="btn btn-info tooltip-title" data-toggle="modal" title="Ubah harga">
                        <i class="far fa-edit fa-fw"></i>
                      </a>
                      <form action="<?php echo base_url('landing_page/service_harga_delete/'.$row->id.'/'.$this->uri->segment(3).'/'.$this->uri->segment(4));?>" method="POST" class="d-inline" onsubmit="return confirm('Hapus investasi? Data yang dihapus tidak dapat dipulihkan.');">
                        <button type="submit" class="btn btn-danger tooltip-title" title="Hapus harga"><i class="far fa-trash-alt fa-fw"></i></button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php
                $no++;}
              ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
<?php
    foreach ($data_harga->result() as $row){
?>
    <div class="modal fade" id="modalLihatAlat<?= $row->id;?>" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="exampleModalLabel"><?= html_escape($row->nama_paket ?? '-');?></h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <button class="btn btn-sm btn-default-asoka mb-3" data-toggle="modal" data-target="#addNewAlat" data-idharga="<?= $row->id;?>">Tambah alat</button>
            <div class="table-responsive">
              <table class="table table-sm">
                <thead>
                  <tr>
                    <th>No.</th>
                    <th>Alat Test</th>
                    <th>Aksi</th>
                  </tr>
                </thead>
                <tbody>
                <?php
                  $no=0;
                  $data_harga_detail = $this->Landing_page_m->getDataHargaDetail($row->id);
                  foreach ($data_harga_detail->result() as $key) {
                    $no++;
                ?>
                  <tr>
                    <td><?= $no;?></td>
                    <td><?= html_escape($key->nama_alat_tes ?? '-');?></td>
                    <td>
                      <form action="<?php echo base_url('landing_page/service_harga_detail_delete_alat/'.$key->id.'/'.$this->uri->segment(3).'/'.$this->uri->segment(4));?>" method="POST" class="d-inline" onsubmit="return confirm('Hapus alat tes? Data yang dihapus tidak dapat dipulihkan.');">
                        <button type="submit" class="btn btn-danger tooltip-title btn-sm" title="Hapus"><i class="far fa-trash-alt fa-fw"></i></button>
                      </form>
                    </td>
                  </tr>
                <?php
                  }
                ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
<?php
    }
  }
?>

<?php
  $url1 = $this->uri->segment(3);
  $url2 = $this->uri->segment(4);
  foreach ($data_harga_all->result() as $x) {
?>
  <div class="modal fade" id="modalEditHarga<?= $x->id;?>" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="exampleModalLabel">Ubah investasi</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <form action="<?php echo base_url('landing_page/service_harga_update/'.$x->id.'/'.$url1.'/'.$url2);?>" method="POST">
            <div class="form-group mb-3">
              <label for="nama_paket" class="col-form-label">Jenis:</label>
              <input type="text" class="form-control" name="nama_paket" id="nama_paket" value="<?= $x->nama_paket;?>">
            </div>
            <div class="form-group mb-3">
              <label for="harga" class="col-form-label">Harga:</label>
              <input type="text" class="form-control" name="harga" id="harga" value="<?= $x->harga;?>">
            </div>
            <div class="form-group mb-3">
              <label for="harga2" class="col-form-label">Harga Setelah Diskon:</label>
              <input type="text" class="form-control" name="harga2" id="harga2" value="<?= $x->harga2;?>">
            </div>
            <div class="form-group mb-3">
              <label for="exp_diskon_" class="col-form-label">Expired Date Diskon:</label>
              <input type="date" class="form-control" name="exp_diskon_" id="exp_diskon_" value="<?= $x->expired_diskon;?>">
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
<div class="modal fade" id="addNewHarga" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Tambah investasi</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form action="<?php echo base_url('landing_page/service_harga_add_new/'.$this->uri->segment(3).'/'.$this->uri->segment(4));?>" method="POST">
          <input type="hidden" name="idservicesubdetail" id="idservicesubdetail">
          <div class="form-group mb-3">
            <label for="nama_paket" class="col-form-label">Jenis:</label>
            <input type="text" class="form-control" name="nama_paket" id="nama_paket" value="">
          </div>
          <div class="form-group mb-3">
            <label for="harga" class="col-form-label">Harga:</label>
            <input type="text" class="form-control tinymce" name="harga" id="harga" value="">
          </div>
          <div class="form-group mb-3">
            <label for="harga2" class="col-form-label">Harga Setelah Diskon (Optional, boleh tidak diisi):</label>
            <input type="text" class="form-control tinymce" name="harga2" id="harga2" value="">
          </div>
          <div class="form-group mb-3">
            <label for="exp_diskon" class="col-form-label">Expired Date Diskon (Optional, boleh tidak diisi):</label>
            <input type="date" class="form-control tinymce" name="exp_diskon" id="exp_diskon" value="">
          </div>
          <button type="submit" class="btn btn-default-asoka float-right">Simpan</button>
        </form>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="addNewAlat" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Tambah alat tes</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form action="<?php echo base_url('landing_page/service_harga_detail_add_alat/'.$this->uri->segment(3).'/'.$this->uri->segment(4));?>" method="POST">
          <input type="hidden" name="idharga" id="idharga">
          <div class="form-group mb-3">
            <label for="alat_test" class="col-form-label">Pilih alat test:</label>
            <select class="form-control" name="alat_test" id="alat_test" required>
              <option disabled selected value="">- Pilih -</option>
              <?php
              foreach ($data_alat_test_all->result() as $key) {
              ?>
                <option value="<?= $key->id_alat_tes;?>"><?= $key->nama_alat_tes;?></option>
              <?php
              } 
              ?>
            </select>
          </div>
          <button type="submit" class="btn btn-default-asoka float-right">Simpan</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php include 'tutorial_content.php'; ?>
<script type="text/javascript">
  $(document).ready(function() {
    $('#data_sub_detail').DataTable();
    $('.tooltip-title').tooltip();
    $(document).on('focusin', function(e) {
      if ($(e.target).closest(".tox-tinymce, .tox-tinymce-aux, .moxman-window, .tam-assetmanager-root").length) {
        e.stopImmediatePropagation();
      }
    });

    $('#addNewHarga').on('show.bs.modal', function (event) {
      var button = $(event.relatedTarget); // Button that triggered the modal
      var id = button.data('idservicesubdetail'); // Extract info from data-* attributes
      // If necessary, you could initiate an AJAX request here (and then do the updating in a callback).
      // Update the modal's content. We'll use jQuery here, but you could use a data binding library or other methods instead.
      var modal = $(this);
      modal.find('.modal-body input#idservicesubdetail').val(id);
    })
    $('#addNewAlat').on('show.bs.modal', function (event) {
      var button = $(event.relatedTarget); // Button that triggered the modal
      var id = button.data('idharga'); // Extract info from data-* attributes
      // If necessary, you could initiate an AJAX request here (and then do the updating in a callback).
      // Update the modal's content. We'll use jQuery here, but you could use a data binding library or other methods instead.
      var modal = $(this);
      modal.find('.modal-body input#idharga').val(id);
    })
  });
</script>
<script src="https://cdn.tiny.cloud/1/fa73jvekxk2rqmk4xchehb1bdvg9l0oefs9l64c0uyqc7qq4/tinymce/5/tinymce.min.js" referrerpolicy="origin"></script>
<script>
    tinymce.init({
        selector: 'textarea.tinymce',
        plugins: 'code table advlist lists wordcount link',
        toolbar: 'undo redo | bold italic underline strikethrough | forecolor | alignleft aligncenter alignright alignjustify | fontselect fontsizeselect formatselect | outdent indent | numlist bullist | link'
    });
</script>
