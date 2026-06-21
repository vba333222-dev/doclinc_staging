<div class="d-sm-flex align-items-center justify-content-between pt-4 pb-5 px-4 mt-n4 mx-n4 you-are-here">
	<h1 class="h3 mb-0 font-weight-bold"><i class="fas fa-fw fa-users"></i> Kelola Warga</h1>
	<!-- <a href="#" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm"><i class="fas fa-download fa-sm text-white-50"></i> Generate Report</a> -->
</div>
<!-- Content Row -->
<div class="row">
    <div class="col">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered" id="tbl_warga" style="width:100%" cellspacing="0">
                        <thead>
                            <th>No.</th>
                            <th>Status</th>
                            <th>Nama</th>
                            <th>Email</th>
                            <th>No.HP</th>
                            <th>Tgl Lahir</th>
                            <th>Gender</th>
                            <th>Alamat</th>
                            <th>KTP</th>
                            <th>Foto</th>
                            <th>Role</th>
                        </thead>
                        <tbody>
                          <?php 
                            $no=0;
                            $color_status='';
                            foreach ($data_warga->result() as $row):
                                $no++;
                                if ($row->status=='aktif') {$color_status='success';}
                                if ($row->status=='nonaktif') {$color_status='danger';}

                          ?>
                            <tr>
                              <td><?= $no;?></td>
                              <td>
                                  <div class="btn-group">
                                      <button type="button" class="btn btn-<?= $color_status;?>"><?= $row->status;?></button>
                                      <button type="button" class="btn btn-outline-<?= $color_status;?> dropdown-toggle dropdown-toggle-split" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <span class="sr-only">Toggle Dropdown</span>
                                      </button>
                                      <div class="dropdown-menu">
                                        <?php if ($row->status=='aktif'){ ?>
                                            <a class="dropdown-item" href="#modalNonaktif" data-toggle="modal" data-iduser="<?= $row->userId;?>" data-namauser="<?= $row->nama;?>">
                                                <i class="fas fa-ban fa-fw text-danger"></i> Non Aktifkan
                                            </a>
                                        <?php }elseif ($row->status=='nonaktif'){ ?>
                                            <a class="dropdown-item" href="#modalAktif" data-toggle="modal" data-iduser="<?= $row->userId;?>" data-namauser="<?= $row->nama;?>">
                                                <i class="fas fa-check-circle fa-fw text-success"></i> Aktifkan
                                            </a>
                                        <?php }?>
                                      </div>
                                  </div>
                              </td>
                              <td><?= html_escape($row->nama ?? '-');?></td>
                              <td><?= html_escape($row->email ?? '-');?></td>
                              <td><?= html_escape($row->no_hp ?? '-');?></td>
                              <td><?= html_escape($row->tgl ?? '-');?></td>
                              <td><?= html_escape($row->gender ?? '-');?></td>
                              <td><?= html_escape($row->alamat ?? '-');?></td>
                              <td>
                                <?php 
                                    if(!empty($row->ktp)){
                                ?>
                                    <a href="../uploads/<?= $row->ktp;?>" target="_BLANK">Lihat</a>
                                <?php 
                                    }
                                ?>
                              </td>
                              <td>
                                <?php 
                                    if(!empty($row->foto)){
                                ?>
                                    <a href="../uploads/<?= $row->foto;?>" target="_BLANK">Lihat</a>
                                <?php 
                                    }
                                ?>
                              </td>
                              <td><?= $row->role;?></td>
                            </tr>
                          <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="modalAktif" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Aktifkan?</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="<?= site_url('kelola_warga/aktifkan_user'); ?>" method="POST">
          <div class="modal-body">
              <input type="hidden" name="id_user">
              <p>Anda yakin ingin mengaktifkan akun <span class="font-weight-bold" name="nama_user"></span></p>
              <div class="form-group mb-0">
                <label for="remark_aktif" class="col-form-label">Catatan :</label>
                <textarea class="form-control" id="remark_aktif" name="remark_aktif" required></textarea>
              </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-primary">Submit</button>
          </div>
      </form>
    </div>
  </div>
</div>
<div class="modal fade" id="modalNonaktif" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Non Aktifkan?</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="<?= site_url('kelola_warga/nonaktifkan_user'); ?>" method="POST">
          <div class="modal-body">
              <input type="hidden" name="id_user">
              <p>Anda yakin ingin menonaktifkan akun <span class="font-weight-bold" name="nama_user"></span></p>
              <div class="form-group mb-0">
                <label for="remark_nonaktif" class="col-form-label">Catatan :</label>
                <textarea class="form-control" id="remark_nonaktif" name="remark_nonaktif" required></textarea>
              </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-primary">Submit</button>
          </div>
      </form>
    </div>
  </div>
</div>
<script type="text/javascript">
    $(document).ready( function () {
        $('#tbl_warga').DataTable({
            dom: "<'row'<'col-sm-12 col-md-4'B><'col-sm-12 col-md-4 text-center'l><'col-sm-12 col-md-4'f>>" +
                 "<'row'<'col-sm-12'tr>>" +
                 "<'row'<'col-sm-12 col-md-6 pe-0'i><'col-sm-12 col-md-6 ps-0'p>>",
            buttons: [
                { extend:'colvis', text:'<i class="fas fa-columns"></i>', className:'btn btn-sm btn-outline-primary bg-white text-primary'},
                { extend:'copy', text:'<i class="far fa-copy"></i> Copy', className:'btn btn-sm btn-outline-primary bg-white text-primary' },
                { extend:'excel', text:'<i class="far fa-file-excel"></i> Excel', className:'btn btn-sm btn-outline-primary bg-white text-primary' },
                { extend:'print', text:'<i class="fas fa-print"></i> Print', className:'btn btn-sm btn-outline-primary bg-white text-primary' }
            ],
            colReorder: true
        }); 
    });
    <?php if ($this->session->flashdata('success')): ?>
        Swal.fire({
            title: "Berhasil!",
            icon: "success",
            text: "<?= $this->session->flashdata('success'); ?>"
        });
    <?php endif; ?>
    $('#modalAktif').on('show.bs.modal', function (event) {
      var button = $(event.relatedTarget);
      var iduser = button.data('iduser');
      var namauser = button.data('namauser');
      var modal = $(this);
      modal.find('.modal-body span[name="nama_user"]').html(namauser);
      modal.find('.modal-body input[name="id_user"]').val(iduser);
    });
    $('#modalNonaktif').on('show.bs.modal', function (event) {
      var button = $(event.relatedTarget);
      var iduser = button.data('iduser');
      var namauser = button.data('namauser');
      var modal = $(this);
      modal.find('.modal-body span[name="nama_user"]').html(namauser);
      modal.find('.modal-body input[name="id_user"]').val(iduser);
    });
</script>
