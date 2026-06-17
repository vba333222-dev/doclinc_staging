<div class="d-sm-flex align-items-center justify-content-between pt-4 pb-5 px-4 mt-n4 mx-n4 you-are-here">
	<h1 class="h3 mb-0 font-weight-bold"><i class="fas fa-laptop-house fa-fw"></i> Landing Page</h1>
	<!-- <a href="#" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm"><i class="fas fa-download fa-sm text-white-50"></i> Home</a> -->
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb mb-0 bg-pink text-white">
      <li class="breadcrumb-item">Landing Page <i class="fas fa-chevron-right fa-fw" style="line-height: inherit;"></i> The Team</li>
    </ol>
  </nav>
</div>

<!-- Content Row -->
<div class="row">
  <div class="col-12">
    <div class="card shadow-sm">
      <div class="card-header">
        Data The Team
      </div>
      <div class="card-body">
        <button class="btn btn-sm btn-default-asoka mb-3" data-toggle="modal" data-target="#modalTambah">+ Tambah Team</button>
        <div class="table-responsive">
          <table class="table table-hover" id="tbl_team">
            <thead>
              <tr>
                <th>No.</th>
                <th>Nama Lengkap</th>
                <th>Jabatan</th>
                <th>No. SIPP</th>
                <!-- <th>Quotes<span class="text-white">QuotesQuotesQuotes</span></th> -->
                <th>Email</th>
                <th>Username</th>
                <th>Level</th>
                <!-- <th>Instagram</th>
                <th>TikTok</th>
                <th>LinkedIn</th> -->
                <th>Avatar</th> 
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
            <?php
              $i=1;
               foreach ($data_the_team->result() as $x) {
            ?>
              <tr>
                <td><?= $i;?></td>
                <td><?= $x->nama;?></td>
                <td><?= $x->jabatan;?></td>
                <td><?= $x->no_sipp;?></td>
                <!-- <td><?= $x->quotes;?></td> -->
                <td><?= $x->email;?></td>
                <td><?= $x->username;?></td>
                <td><?= $x->nama_level;?></td>
                <!-- <td><?= substr($x->ig, 0, 26).'...';?></td>
                <td><?= substr($x->twitter, 0 ,23).'...';?></td>
                <td><?= substr($x->fb, 0 , 25).'...';?></td> -->
                <td><img src="https://asokaconsulting.co.id/assets/img/testimonials/<?= $x->avatar;?>"   style="width:auto;height:70px;"></td> 
                <td>
                  <div class="btn-group btn-group-sm">
                    <a href="#modalEdit" class="btn btn-info tooltip-title" data-toggle="modal" 
                    data-id="<?= $x->id;?>" 
                    data-nama="<?= $x->nama;?>" 
                    data-jabatan="<?= $x->jabatan;?>" 
                    data-nosipp="<?= $x->no_sipp;?>" 
                    data-quotes="<?= $x->quotes;?>" 
                    data-email="<?= $x->email;?>"
                    data-username="<?= $x->username;?>"
                    data-level="<?= $x->nama_level;?>"
                    data-ig="<?= $x->ig;?>" 
                    data-twitter="<?= $x->twitter;?>" 
                    data-fb="<?= $x->fb;?>"
                    data-avatar="<?= $x->avatar;?>"
                    data-signature="<?= $x->signature;?>" title="Edit">
                      <i class="far fa-edit fa-fw"></i>
                    </a>           
                    <a href="<?php echo base_url('landing_page/the_team_delete/'.$x->id.'/'.$x->username);?>" class="btn btn-danger tooltip-title" title="Hapus">
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

<div class="modal fade" id="modalTambah" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Tambah Team</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div> 
      <div class="modal-body">
        <form id="add_team" action="<?php echo base_url();?>landing_page/add_the_team" method="POST" enctype="multipart/form-data"> 
          <div class="form-group mb-3">
            <label for="nama" class="col-form-label">Nama Lengkap (Beserta Gelar) :</label>
            <input type="text" class="form-control" name="nama" id="nama" required>
          </div> 
          <div class="form-group mb-3">
            <label for="jabatan" class="col-form-label">Jabatan :</label> 
            <input type="text" class="form-control" name="jabatan" id="jabatan" required>
          </div> 
          <div class="form-group mb-3">
            <label for="no_sipp" class="col-form-label">No. SIPP :</label> 
            <input type="text" class="form-control" name="no_sipp" id="no_sipp" required>
          </div> 
          <div class="form-group mb-3">
            <label for="nama" class="col-form-label">Quotes :</label> 
            <textarea name="quotes" id="quotes" class="form-control" required></textarea>
          </div> 
          <div class="form-group mb-3">
            <label for="level" class="col-form-label">Level :</label>
            <select class="form-control" id="level" name="level" required>
              <option value="" disabled selected>- Pilih -</option>
            <?php
              foreach($data_level->result() as $x){
            ?>
              <option value="<?= $x->id;?>"><?= $x->name;?> (<?= $x->description;?>)</option>  
            <?php
              }
            ?>
            </select>
          </div> 
          <div class="form-group mb-3">
            <label for="email" class="col-form-label">Email :</label>
            <input type="text" class="form-control" name="email" id="email" required>
          </div>
          <div class="form-group mb-3">
            <label for="username" class="col-form-label">Username :</label>
            <input type="text" class="form-control" name="username" id="username" required>
          </div>
          <div class="form-group mb-3">
            <label for="password" class="col-form-label">Password :</label>
            <input type="password" class="form-control" name="password" id="password" required>
          </div>
          <div class="form-group mb-3">
            <label for="c_password" class="col-form-label">Konfirmasi password :</label>
            <input type="password" class="form-control" name="c_password" id="c_password" required>
          </div> 
          <div class="form-group mb-3">
            <label for="nama" class="col-form-label">Instagram :</label>
            <input type="text" class="form-control" name="ig" id="ig">
          </div>
          <div class="form-group mb-3">
            <label for="nama" class="col-form-label">TikTok :</label>
            <input type="text" class="form-control" name="twitter" id="twitter">
          </div> 
          <div class="form-group mb-3">
            <label for="nama" class="col-form-label">LinkedIn :</label>
            <input type="text" class="form-control" name="fb" id="fb">
          </div> 
          <div class="form-group mb-3">
            <label for="nama" class="col-form-label">Avatar :</label>
            <input type="file" class="form-control" name="avatar" id="avatar" required accept="image/*">
          </div>
          <div class="form-group mb-3">
            <label for="nama" class="col-form-label">Digital Signature :</label>
            <input type="file" class="form-control" name="signature" id="signature" accept="image/*">
          </div>
          <div class="d-flex">
            <button type="submit" class="btn btn-default-asoka ml-auto">Simpan</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalEdit" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel"></h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form id="edit_team" action="<?php echo base_url();?>landing_page/the_team_update" method="POST" enctype="multipart/form-data">
          <input type="hidden" class="form-control" name="idnya" id="idnya">
          <div class="form-group mb-3">
            <label for="nama" class="col-form-label">Nama Lengkap (Beserta Gelar) :</label>
            <input type="text" class="form-control" name="nama_" id="nama_">
          </div> 
          <div class="form-group mb-3">
            <label for="jabatan" class="col-form-label">Jabatan :</label> 
            <input type="text" class="form-control" name="jabatan_" id="jabatan_">
          </div> 
          <div class="form-group mb-3">
            <label for="no_sipp_" class="col-form-label">No. SIPP :</label> 
            <input type="text" class="form-control" name="no_sipp_" id="no_sipp_">
          </div> 
          <div class="form-group mb-3">
            <label for="quotes_" class="col-form-label">Quotes :</label> 
            <textarea name="quotes_" id="quotes_" class="form-control"></textarea>
          </div>
          <div class="form-group mb-3">
            <label for="level_" class="col-form-label">Level saat ini : <span id="nama_level" class="font-weight-bold"></span> (Biarkan saja bila tidak ingin mengubah)</label>
            <select class="form-control" id="level_" name="level_">
              <option value="" disabled selected>- Pilih -</option>
            <?php
              foreach($data_level->result() as $x){
            ?>
              <option value="<?= $x->id;?>"><?= $x->name;?> (<?= $x->description;?>)</option>  
            <?php
              }
            ?>
            </select>
          </div> 
          <div class="form-group mb-3">
            <label for="email_" class="col-form-label">Email :</label> 
            <input type="text" class="form-control" name="email_" id="email_">
          </div> 
          <div class="form-group mb-3">
            <label for="username_" class="col-form-label">Username :</label> 
            <input type="text" class="form-control" name="username_" id="username_" readonly>
          </div> 
          <div class="form-group mb-3">
            <label for="password_" class="col-form-label">Password (Kosongkan bila tidak perlu)</label> 
            <input type="password" class="form-control" name="password_" id="password_">
          </div> 
          <div class="form-group mb-3">
            <label for="c_password_" class="col-form-label">Konfirmasi Password (Kosongkan bila tidak perlu)</label> 
            <input type="password" class="form-control" name="c_password_" id="c_password_">
          </div> 
          <div class="form-group mb-3">
            <label for="ig_" class="col-form-label">Instagram :</label>
            <input type="text" class="form-control" name="ig_" id="ig_">
          </div> 
          <div class="form-group mb-3">
            <label for="twitter_" class="col-form-label">TikTok :</label>
            <input type="text" class="form-control" name="twitter_" id="twitter_">
          </div> 
          <div class="form-group mb-3">
            <label for="fb_" class="col-form-label">LinkedIn :</label>
            <input type="text" class="form-control" name="fb_" id="fb_"> 
          </div> 
          <div class="form-group mb-3">
            <label for="avatar2_" class="col-form-label">Avatar (Kosongkan bila tidak perlu)</label> 
            <input type="hidden" class="form-control" name="avatar_" id="avatar_"> 
            <input type="file" class="form-control" name="avatar2_" id="avatar2_">
          </div>
          <div class="form-group mb-3">
            <label for="signature2_" class="col-form-label">Digital Signature (Kosongkan bila tidak perlu)</label> 
            <input type="hidden" class="form-control" name="signature_" id="signature_"> 
            <input type="file" class="form-control" name="signature2_" id="signature2_">
          </div>
          <div class="form-group mb-3" id="signaturenya">
          </div>
          <div class="d-flex">
            <button type="submit" class="btn btn-default-asoka ml-auto">Update</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<script type="text/javascript">
	$(document).ready(function() {
    $('#tbl_team').DataTable();
    $('.tooltip-title').tooltip();
    $('form#add_team').submit(function(event) {
      if ($('#password').val()!=$('#c_password').val()) {
        alert('Password tidak cocok!');
        return false;
      }
    });
    $('form#edit_team').submit(function(event) {
      if ($('#password_').val()!=$('#c_password_').val()) {
        alert('Password tidak cocok!');
        return false;
      }
    });
    $('#modalEdit').on('show.bs.modal', function (event) { 
      var button = $(event.relatedTarget); // Button that triggered the modal
      var idnya = button.data('id'); // Extract info from data-* attributes
      var nama = button.data('nama'); // Extract info from data-* attributes
      var jabatan = button.data('jabatan'); // Extract info from data-* attributes
      var no_sipp = button.data('nosipp'); // Extract info from data-* attributes
      var quotes = button.data('quotes'); // Extract info from data-* attributes
      var email = button.data('email'); // Extract info from data-* attributes
      var username = button.data('username'); // Extract info from data-* attributes
      var level = button.data('level'); // Extract info from data-* attributes
      var ig = button.data('ig'); // Extract info from data-* attributes
      var twitter = button.data('twitter'); // Extract info from data-* attributes
      var fb = button.data('fb'); // Extract info from data-* attributes
      var avatar = button.data('avatar'); // Extract info from data-* attributes  
      var signature = button.data('signature'); // Extract info from data-* attributes  
      // Update the modal's content. We'll use jQuery here, but you could use a data binding library or other methods instead.
      var modal = $(this);
      modal.find('.modal-title').text('Edit Team');
      modal.find('.modal-body input#idnya').val(idnya);
      modal.find('.modal-body input#nama_').val(nama);
      modal.find('.modal-body input#jabatan_').val(jabatan);
      modal.find('.modal-body input#no_sipp_').val(no_sipp);
      modal.find('.modal-body textarea#quotes_').val(quotes);
      modal.find('.modal-body input#email_').val(email);
      modal.find('.modal-body input#username_').val(username);
      modal.find('.modal-body span#nama_level').html(level);
      modal.find('.modal-body input#ig_').val(ig);
      modal.find('.modal-body input#twitter_').val(twitter);
      modal.find('.modal-body input#fb_').val(fb);
      modal.find('.modal-body input#avatar_').val(avatar);
      modal.find('.modal-body input#signature_').val(signature);
      if (signature!='') {
        $('#signaturenya').html('<img src="<?php echo base_url();?>assets/img/'+signature+'" width="100px" height="auto">');
      }else{
        $('#signaturenya').html('');
      }
    })
	});
</script>