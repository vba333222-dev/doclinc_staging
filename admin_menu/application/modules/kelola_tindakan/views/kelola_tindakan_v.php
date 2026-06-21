<div class="d-sm-flex align-items-center justify-content-between pt-4 pb-5 px-4 mt-n4 mx-n4 you-are-here">
    <h1 class="h3 mb-0 font-weight-bold"><i class="fas fa-fw fa-comments"></i> Kelola tindakan</h1>
</div>
<!-- Content Row -->
<div class="row">
    <div class="col">
        <div class="card shadow-sm">
            <div class="card-body table-responsive">
                <table class="table table-bordered" id="tbl_tindakan" style="width:100%" cellspacing="0">
                    <thead>
                        <th>No.</th>
                        <th>ID</th>
                        <th>Warga</th>
                        <th>Keluhan</th>
                        <th>Diagnosa</th>
                        <th>Saran</th>
                        <th>Aksi</th>
                    </thead>
                    <tbody>
                      <?php 
                        $no=0;
                        foreach ($data_tindakan->result() as $row):
                            $no++;
                      ?>
                        <tr>
                          <td><?= $no;?></td>
                          <td><?= $row->konsul_id;?></td>
                          <td></td>
                          <td></td>
                          <td><?= html_escape($row->diagnosa ?? '-');?></td>
                          <td><?= html_escape($row->saran ?? '-');?></td>
                          <td>
                            <div class="btn-group">
                              <button type="button" class="btn btn-outline-secondary">Aksi</button>
                              <button type="button" class="btn btn-outline-secondary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="sr-only">Toggle Dropdown</span>
                              </button>
                              <div class="dropdown-menu">
                                  <a class="dropdown-item" href="#modalEdit" data-toggle="modal" data-idtindakan="<?= $row->konsul_id;?>">
                                      <i class="fas fa-edit fa-fw text-primary"></i> Edit
                                  </a>
                                  <a class="dropdown-item" href="#modalDelete" data-toggle="modal" data-idtindakan="<?= $row->konsul_id;?>">
                                      <i class="fas fa-trash fa-fw text-danger"></i> Delete
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


<div class="modal fade" id="modalEdit" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Edit tindakan?</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="<?= site_url('kelola_tindakan/edit_tindakan'); ?>" method="POST">
          <div class="modal-body">
              <input type="hidden" name="konsul_id">
              <div class="form-group">
                <label for="saran" class="col-form-label">Saran :</label>
                <input type="text" class="form-control" id="saran" name="saran" required>
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
<div class="modal fade" id="modalDelete" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Hapus tindakan?</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="<?= site_url('kelola_tindakan/delete_tindakan'); ?>" method="POST">
          <div class="modal-body">
              <input type="hidden" name="konsul_id">
              <div class="form-group">
                <label for="remark" class="col-form-label">Catatan :</label>
                <textarea class="form-control" id="remark" name="remark" required></textarea>
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
        $('#tbl_tindakan').DataTable(); 
    });
    <?php if ($this->session->flashdata('success')): ?>
        Swal.fire({
            title: "Berhasil!",
            icon: "success",
            text: "<?= $this->session->flashdata('success'); ?>"
        });
    <?php endif; ?>
    $('#modalEdit').on('show.bs.modal', function (event) {
      var button = $(event.relatedTarget);
      var konsul_id = button.data('idtindakan');
      var modal = $(this);
      modal.find('.modal-body input[name="konsul_id"]').val(konsul_id);
    });
    $('#modalDelete').on('show.bs.modal', function (event) {
      var button = $(event.relatedTarget);
      var konsul_id = button.data('idtindakan');
      var modal = $(this);
      modal.find('.modal-body input[name="konsul_id"]').val(konsul_id);
    });
</script>
