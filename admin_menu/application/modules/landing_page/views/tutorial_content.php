<div class="modal fade" id="tutor" tabindex="-1" aria-labelledby="exampleModalLabeltutor" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="exampleModalLabeltutor">Contenting Guides</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
        	<div class="modal-body">
				<ul class="nav nav-tabs" id="myTab" role="tablist">
				  <li class="nav-item" role="presentation">
				    <a class="nav-link active" id="link-tab" data-toggle="tab" href="#link" role="tab" aria-controls="link" aria-selected="true">Link/Button</a>
				  </li>
				  <li class="nav-item" role="presentation">
				    <a class="nav-link" id="table-tab" data-toggle="tab" href="#table" role="tab" aria-controls="table" aria-selected="false">Tables</a>
				  </li>
				  <li class="nav-item" role="presentation">
				    <a class="nav-link" id="table-tab" data-toggle="tab" href="#youtube" role="tab" aria-controls="table" aria-selected="false">Video Embed Youtube</a>
				  </li>
				</ul>
				<div class="tab-content border bg-white" id="myTabContent">
				  <div class="tab-pane fade show active p-3" id="link" role="tabpanel" aria-labelledby="link-tab">
				  	<p>Cara untuk memodifikasi tampilan link menjadi button.</p>
	        		<ol>
	        			<li>
	        				Pada menu "View", klik "Source Code"<br>
							<img src="<?php echo base_url();?>assets/img/source_code.jpg" width="200px" height="auto">
	        			</li>
	        			<li>
	        				Format link : <code>&lt;a href="https://somelink"&gt;textlink&lt;/a&gt;</code><br>
	        				Tambahkan <span class="font-weight-bold">class="btn btn-default-asoka"</span> seperti gambar di bawah ini.<br>
							<img src="<?php echo base_url();?>assets/img/link_source_code.jpg" class="img-fluid">
	        			</li>
	        			<li>Selesai.</li>
	        		</ol>
				  </div>
				  <div class="tab-pane fade p-3" id="table" role="tabpanel" aria-labelledby="table-tab">
				  	<p>Untuk penulisan konten tabel.</p>
				  	<ol>
				  		<li>
	        				Pada menu "View", klik "Source Code"<br>
							<img src="<?php echo base_url();?>assets/img/source_code.jpg" width="200px" height="auto">
	        			</li>
	        			<li>
	        				Format tabel biasanya diawali dengan karakter <code>&lt;table&gt;</code> dan ditutup dengan <code>&lt;/table&gt;</code> seperti gambar di bawah ini.
							<img src="<?php echo base_url();?>assets/img/table_source_code.jpg" class="img-fluid">
	        			</li>
	        			<li>
	        				Tambahkan <code>&lt;div class="table-responsive"&gt;</code> sebelum <code>&lt;table&gt;</code> dan tambahkan <code>&lt;/div&gt;</code> setelah <code>&lt;/table&gt;</code>.<br>
	        				Kemudian tambahkan <span class="font-weight-bold">class="table table-bordered"</span> di dalam <code>&lt;table&gt;</code>.<br>
	        				Lihat gambar di bawah ini.<br>
							<img src="<?php echo base_url();?>assets/img/table_source_code2.jpg" class="img-fluid">
	        			</li>
	        			<li>Selesai.</li>
				  	</ol>
				  </div>
				  <div class="tab-pane fade p-3" id="youtube" role="tabpanel" aria-labelledby="youtube-tab">
				  	<p>Untuk memasukkan link video youtube pada Content Events & News.</p>
				  	<ol>
				  		<li>
	        				Klik kanan pada panel pemutar video youtube, lalu piilih "<i class="fas fa-chevron-left"></i> <i class="fas fa-chevron-right"></i> Copy Embed Code".<br>Lihat gambar di bawah.<br>
							<img src="<?php echo base_url();?>assets/img/embed_youtube.jpg" class="img-fluid">
	        			</li>
				  		<li>
	        				Kemudian saat ingin menambah/mengubah content events & news, Pada menu "View", klik "Source Code"<br>
							<img src="<?php echo base_url();?>assets/img/source_code.jpg" width="200px" height="auto">
	        			</li>
	        			<li>
	        				Pada kolom inputan "Source Code" ketikkan <code>&lt;div class="ratio ratio-16x9"&gt;</code>. Lalu Paste link hasil dari Copy Embed Code youtube dan hapus atribut <code>width</code> dan <code>height</code> yang ada di dalam <code>&lt;iframe&gt;</code>, dan ketikkan lagi <code>&lt;/div&gt;</code> setelah <code>&lt;/iframe&gt;</code>. Lihat gambar di bawah.<br>
							<img src="<?php echo base_url();?>assets/img/embed_youtube_source_code.jpg" class="img-fluid">
	        			</li>
	        			<li>Selesai.</li>
				  	</ol>
				  </div>
				</div>
        	</div>
		</div>
    </div>
</div>