<div class="d-sm-flex align-items-center justify-content-between pt-4 pb-5 px-4 mt-n4 mx-n4 you-are-here">
	<h1 class="h3 mb-0 font-weight-bold"><i class="fas fa-laptop-house fa-fw"></i> Landing Page</h1>
	<!-- <a href="#" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm"><i class="fas fa-download fa-sm text-white-50"></i> Home</a> -->
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb mb-0 bg-pink text-white">
      <li class="breadcrumb-item">Landing Page <i class="fas fa-chevron-right fa-fw" style="line-height: inherit;"></i> Home</li>
    </ol>
  </nav>
</div>

<!-- Content Row -->
<div class="row">
  <div class="col-12 mb-3">
    <img src="<?php echo base_url();?>assets/img/hero.jpg" class="img-fluid img-thumbnail">
  </div>
  <div class="col-12">
    <form action="<?php echo base_url();?>landing_page/update_tagline" method="POST">
      <div class="form-group">
        <label for="tagline">Update tagline</label>
        <input type="text" class="form-control" id="tagline" name="tagline" aria-describedby="taglineHelp">
        <?php
          foreach ($data_home->result() as $row) {
            $tagline = $row->tagline;
          }
        ?>
        <small id="taglineHelp" class="form-text text-muted">Current tagline : <?= $tagline;?></small>
      </div>
      <button type="submit" class="btn btn-default-asoka" id="submit">Update</button>
    </form>
  </div>
</div>
<script type="text/javascript">
	$(document).ready(function() {
		$('#submit').click(function() {
			if ($('#tagline').val() == '') {
        Swal.fire(
  			  'Gagal!',
  			  'Pastikan kolom tagline terisi',
  			  'error'
  			)
        return false;
      }else{
        return true;
      }
		});
	});
</script>