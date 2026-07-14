<div class="d-sm-flex align-items-center justify-content-between pt-4 pb-5 px-4 mt-n4 mx-n4 you-are-here">
    <h1 class="h3 mb-0 font-weight-bold"><i class="fas fa-fw fa-newspaper"></i> News & Feed</h1>
    <button type="button" class="btn btn-sm btn-success shadow-sm" data-toggle="modal" data-target="#modalTambah">
        <i class="fas fa-plus fa-sm text-white-50"></i> Tambah berita
    </button>
</div>
<div class="doclinc-helper-note mb-3">
    Pengelolaan konten dan feed yang tampil sebagai informasi publik DocLink.
</div>
<!-- Content Row -->
<div class="row">
    <div class="col">
        <div class="card shadow-sm">
            <div class="card-body table-responsive">
                <table class="table table-bordered" id="tbl_news_feed" style="width:100%" cellspacing="0">
                    <thead>
                        <th>No.</th>
                        <th>ID Feed</th>
                        <th>Subject</th>
                        <th>Gambar</th>
                        <th>Tanggal Upload</th>
                        <th>Status</th>
                        <th>Create User</th>
                        <th>Create Date</th>
                        <th>Aksi</th>
                    </thead>
                    <tbody>
                      <?php
                        $no=0;
                        foreach ($data_news_feed->result() as $row):
                            $no++;
                      ?>
                        <tr>
                          <td><?= $no;?></td>
                          <td><?= html_escape($row->feedId ?? '-');?></td>
                          <td><?= html_escape($row->subject ?? '-');?></td>
                          <td><?php if (!empty($row->gambar)): ?><img src="<?= base_url('uploads/feeds/') . rawurlencode($row->gambar); ?>" width="100" alt="News Feed"><?php else: ?>-<?php endif; ?></td>
                          <td><?= !empty($row->create_at) ? date('d-m-Y',strtotime($row->create_at)) : '-';?></td>
                          <td>
                                <?php
                                    $status = $row->status ?? '';
                                    $status_badge = '';
                                    switch ($status) {
                                        case 'aktif':
                                            $status_badge = '<span class="badge badge-success"><i class="fas fa-toggle-on"></i> Aktif</span>';
                                            break;
                                        case 'non-aktif':
                                            $status_badge = '<span class="badge badge-warning"><i class="fas fa-toggle-off"></i> Non Aktif</span>';
                                            break;
                                        default:
                                            $status_badge = '<span class="badge badge-dark">Unknown</span>';
                                            break;
                                    }
                                    echo $status_badge;
                                ?>
                            </td>
                          <td><?= html_escape($row->create_user ?? '-');?></td>
                          <td><?= html_escape($row->create_at ?? '-');?></td>
                          <td>
                            <div class="btn-group">
                              <button type="button" class="btn btn-outline-secondary">Aksi</button>
                              <button type="button" class="btn btn-outline-secondary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="sr-only">Buka menu</span>
                              </button>
                              <div class="dropdown-menu">
                                  <a class="dropdown-item" href="#modalEdit" data-toggle="modal" data-idfeed="<?= html_escape($row->feedId ?? ''); ?>">
                                      <i class="fas fa-edit fa-fw text-primary"></i> Edit
                                  </a>
                                  <a class="dropdown-item" href="#modalDelete" data-toggle="modal" data-idfeed="<?= html_escape($row->feedId ?? ''); ?>">
                                      <i class="fas fa-ban fa-fw text-danger"></i> Nonaktifkan
                                  </a>
                              </div>
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

<!-- Modal Tambah News Feed -->
<div class="modal fade" id="modalTambah" tabindex="-1" role="dialog" aria-labelledby="modalTambahLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalTambahLabel">Tambah News Feed</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="<?= site_url('kelola_news_feed/tambah'); ?>" method="POST" enctype="multipart/form-data">
          <div class="modal-body">
              <div class="form-group">
                <label for="subject" class="col-form-label">Subject :</label>
                <input type="text" class="form-control" id="subject" name="subject" required>
              </div>
              <div class="form-group">
                <label for="gambar" class="col-form-label">Gambar :</label>
                <input type="file" class="form-control" id="gambar" name="gambar" accept=".jpg,.jpeg,.png,image/jpeg,image/png" required>
                <small class="form-text text-muted">Format jpg/jpeg/png, maksimal 2 MB.</small>
              </div>
              <div class="form-group">
                <label for="status" class="col-form-label">Status :</label>
                <select class="form-control" id="status" name="status">
                  <option value="aktif">Aktif</option>
                  <option value="non-aktif">Non Aktif</option>
                </select>
              </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
            <button type="submit" class="btn btn-primary">Simpan</button>
          </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Edit News Feed -->
<div class="modal fade" id="modalEdit" tabindex="-1" role="dialog" aria-labelledby="modalEditLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalEditLabel">Edit News Feed</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="<?= site_url('kelola_news_feed/edit'); ?>" method="POST" enctype="multipart/form-data">
          <div class="modal-body">
              <input type="hidden" name="feedId_edit" id="feedId_edit">
              <div class="form-group">
                <label for="subject" class="col-form-label">Subject :</label>
                <input type="text" class="form-control" id="subject_edit" name="subject_edit" >
              </div>
              <div class="form-group">
                <label for="gambar_edit" class="col-form-label">Gambar :</label>
                <input type="file" class="form-control" id="gambar_edit" name="gambar" accept=".jpg,.jpeg,.png,image/jpeg,image/png">
                <small class="form-text text-muted">Kosongkan jika gambar tidak diubah. Format jpg/jpeg/png, maksimal 2 MB.</small>
              </div>
              <div class="form-group">
                <label for="status" class="col-form-label">Status :</label>
                <select class="form-control" id="status_edit" name="status_edit">
                  <option value="aktif">Aktif</option>
                  <option value="non-aktif">Non Aktif</option>
                </select>
              </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
            <button type="submit" class="btn btn-primary">Perbarui</button>
          </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Nonaktif News Feed -->
<div class="modal fade" id="modalDelete" tabindex="-1" role="dialog" aria-labelledby="modalDeleteLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalDeleteLabel">Nonaktifkan News Feed</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="<?= site_url('kelola_news_feed/delete'); ?>" method="POST">
          <div class="modal-body">
              <input type="hidden" name="feedId">
              <p>News feed akan dinonaktifkan dan tidak dihapus permanen.</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-danger">Nonaktifkan</button>
          </div>
      </form>
    </div>
  </div>
</div>

<script type="text/javascript">
  $(document).ready(function () {
        $('#tbl_news_feed').DataTable({
            language: {
                lengthMenu: 'Tampilkan _MENU_ data',
                search: 'Cari:',
                emptyTable: 'Tidak ada data',
                zeroRecords: 'Tidak ada data sesuai filter',
                info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ data',
                infoEmpty: 'Menampilkan 0 data',
                infoFiltered: '(difilter dari _MAX_ total data)',
                paginate: {
                    previous: 'Sebelumnya',
                    next: 'Berikutnya'
                }
            }
        });
    });

    $('#modalEdit').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var feedId = button.data('idfeed');
        var modal = $(this);
        $.ajax({
            url: "<?= site_url('kelola_news_feed/get_news_feed'); ?>",
            type: "POST",
            data: {feedId: feedId},
            dataType: "json",
            success: function(data) {
                modal.find('input[name="feedId_edit"]').val(data.feedId);
                modal.find('input[name="subject_edit"]').val(data.subject);
                modal.find('select[name="status_edit"]').val(data.status);
            }
        });
    });

    $('#modalDelete').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var feedId = button.data('idfeed');
        var modal = $(this);

        modal.find('input[name="feedId"]').val(feedId);
    });
</script>
