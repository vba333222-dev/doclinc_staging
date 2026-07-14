<div class="d-sm-flex align-items-center justify-content-between pt-4 pb-5 px-4 mt-n4 mx-n4 you-are-here">
	<h1 class="h3 mb-0 font-weight-bold"><i class="fas fa-laptop-house fa-fw"></i> Landing Page</h1>
	<!-- <a href="#" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm"><i class="fas fa-download fa-sm text-white-50"></i> Home</a> -->
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb mb-0 bg-pink text-white">
      <li class="breadcrumb-item">Landing Page <i class="fas fa-chevron-right fa-fw" style="line-height: inherit;"></i> FAQ</li>
    </ol>
  </nav>
</div>

<!-- Content Row -->
<div class="row">
  <div class="col-12">
    <div class="card shadow-sm">
      <div class="card-header">
        Data FAQ
      </div>
      <div class="card-body">
        <button class="btn btn-sm btn-default-asoka mb-3" data-toggle="modal" data-target="#modalTambah">Tambah FAQ</button>
        <div class="table-responsive">
          <table class="table table-hover" id="tbl_services">
            <thead>
              <tr>
                <th>No.</th>
                <th>Question</th>
                <th>Answer</th>
                <th>Diunggah Oleh</th>
                <th>Terakhir Diubah</th> 
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
            <?php
                $i=1;
                foreach ($data_faq->result() as $x) {
            ?>
              <tr>
                <td><?= $i;?></td>
                <td><?= html_escape($x->question ?? '-');?></td>
                <td><?= html_escape($x->answer ?? '-');?></td>
                <td><?= html_escape($x->create_user ?? '-');?></td>
                <td><?= html_escape($x->modify_date ?? '-');?></td>
                <td>
                  <div class="btn-group btn-group-sm">
                    <a href="#modalEdit" class="btn btn-info tooltip-title" data-toggle="modal" data-id="<?= $x->id;?>" data-question="<?= $x->question;?>" data-answer="<?= $x->answer;?>"  title="Ubah">
                      <i class="far fa-edit fa-fw"></i>
                    </a>
                    <form action="<?php echo base_url('landing_page/delete_faq/'.$x->id);?>" method="POST" class="d-inline" onsubmit="return confirm('Hapus FAQ? Data yang dihapus tidak dapat dipulihkan.');">
                      <button type="submit" class="btn btn-danger tooltip-title"><i class="far fa-trash-alt fa-fw"></i></button>
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
        <h5 class="modal-title" id="exampleModalLabel">Tambah FAQ</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
          <span aria-hidden="true">&times;</span>
        </button>
      </div> 
      <div class="modal-body">
        <form action="<?php echo base_url();?>landing_page/add_faq" method="POST" enctype="multipart/form-data"> 
          <div class="form-group mb-3">
            <label for="question" class="col-form-label">Question :</label>
            <input type="text" class="form-control" name="question" id="question">
          </div> 
          <div class="form-group mb-3">
            <label for="answer" class="col-form-label">Answer :</label> 
            <input type="text" class="form-control" name="answer" id="answer">
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
        <form action="<?php echo base_url();?>landing_page/faq_update" method="POST">
          <input type="hidden" class="form-control" name="idnya" id="idnya">
          <div class="form-group mb-3">
            <label for="question" class="col-form-label">Question :</label>
            <input type="text" class="form-control" name="question_" id="question_">
          </div> 
          <div class="form-group mb-3">
            <label for="answer" class="col-form-label">Answer :</label> 
            <input type="text" class="form-control" name="answer_" id="answer_">
          </div>  
          <button type="submit" class="btn btn-default-asoka float-right">Simpan</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script type="text/javascript">
	$(document).ready(function() {
    $('#tbl_team').DataTable();
    $('.tooltip-title').tooltip();
    
    $('#modalEdit').on('show.bs.modal', function (event) {
      var button = $(event.relatedTarget); // Button that triggered the modal
      var idnya = button.data('id'); // Extract info from data-* attributes
      var question = button.data('question'); // Extract info from data-* attributes
      var answer = button.data('answer'); // Extract info from data-* attributes 
      // Update the modal's content. We'll use jQuery here, but you could use a data binding library or other methods instead.
      var modal = $(this);
      modal.find('.modal-title').text('Ubah FAQ');
      modal.find('.modal-body input#idnya').val(idnya);
      modal.find('.modal-body input#question_').val(question);
      modal.find('.modal-body input#answer_').val(answer); 
    })
	});
</script>
