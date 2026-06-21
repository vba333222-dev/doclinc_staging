        </div>
        <!-- /.container-fluid -->

        </div>
        <!-- End of Main Content -->

        <!-- Footer -->
        <footer class="sticky-footer bg-white">
        	<div class="container my-auto">
        		<div class="copyright text-center text-muted my-auto">
        			<span>Copyright &copy; DokLinC 2024</span>
        		</div>
        	</div>
        </footer>
        <!-- End of Footer -->

        </div>
        <!-- End of Content Wrapper -->

        </div>
        <div class="info mx-3 mb-3" style="position: fixed;bottom: 0;right: 0;z-index: 3;"><?php echo $this->session->flashdata('info'); ?></div>
        <!-- End of Page Wrapper -->

        <!-- Scroll to Top Button-->
        <a class="scroll-to-top rounded" href="#page-top">
        	<i class="fas fa-angle-up"></i>
        </a>

        <!-- Logout Modal-->
        <div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
        	<div class="modal-dialog" role="document">
        		<div class="modal-content">
        			<div class="modal-header">
        				<h5 class="modal-title" id="exampleModalLabel">Ready to Leave?</h5>
        				<button class="close" type="button" data-dismiss="modal" aria-label="Close">
        					<span aria-hidden="true">×</span>
        				</button>
        			</div>
        			<div class="modal-body">Select "Logout" below if you are ready to end your current session.</div>
        			<div class="modal-footer">
        				<button class="btn btn-secondary" type="button" data-dismiss="modal">Cancel</button>
					<a class="btn btn-primary" href="<?php echo site_url('login/logout'); ?>">Logout</a>
        			</div>
        		</div>
        	</div>
        </div>
        <!-- Password Modal-->
        <div class="modal fade" id="modalPassword" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
        	<div class="modal-dialog modal-sm" role="document">
        		<div class="modal-content">
        			<div class="modal-header">
        				<h5 class="modal-title" id="exampleModalLabel">Change Password</h5>
        				<button class="close" type="button" data-dismiss="modal" aria-label="Close">
        					<span aria-hidden="true">×</span>
        				</button>
        			</div>
			<form action="<?php echo site_url('home/change_password'); ?>" method="POST" id="change_password">
        				<div class="modal-body">
        					<div class="form-group">
        						<label for="old_password">Password lama</label>
        						<input type="password" class="form-control" id="old_password" name="old_password" required placeholder="Password lama">
        					</div>
        					<div class="form-group">
        						<label for="new_password">Password baru</label>
        						<input type="password" class="form-control" id="new_password" name="new_password" required placeholder="Password baru">
        					</div>
        					<div class="form-group mb-0">
        						<label for="c_new_password">Konfirmasi password baru</label>
        						<input type="password" class="form-control" id="c_new_password" name="c_new_password" required placeholder="Konfirmasi password baru">
        					</div>
        				</div>
        				<div class="modal-footer">
        					<button class="btn btn-secondary" type="button" data-dismiss="modal">Cancel</button>
        					<button class="btn btn-default-asoka" type="submit">Save</button>
        				</div>
        			</form>
        		</div>
        	</div>
        </div>

        <?php
        $admin_success_message = $this->session->flashdata('success');
        $admin_error_message = $this->session->flashdata('error');
        if ($admin_success_message !== NULL) {
            $this->session->unset_userdata('success');
        }
        if ($admin_error_message !== NULL) {
            $this->session->unset_userdata('error');
        }
        ?>
        <script type="text/javascript">
        	$(document).ready(function() {
        		if ($(window).width() < 481) {
        			$('body').addClass('sidebar-toggled');
        			$('#accordionSidebar').addClass('toggled');
        		}
        		$('form#change_password').submit(function(event) {
        			if ($('#new_password').val() != $('#c_new_password').val()) {
        				Swal.fire({
        					icon: 'error',
        					title: 'Oops!',
        					html: 'Pastikan "Password baru" dan "Konfirmasi password baru" sesuai',
        					confirmButtonText: 'Yuk ulangi'
        				});
        				return false;
        			}
        		});
        	});
			<?php if ($admin_success_message): ?>
				Swal.fire({
					title: "Berhasil!",
					icon: "success",
					text: "<?= html_escape($admin_success_message); ?>"
				});
			<?php endif; ?>
            <?php if ($admin_error_message): ?>
                Swal.fire({
                    title: "Gagal!",
                    icon: "error",
                    text: "<?= html_escape($admin_error_message); ?>"
                });
            <?php endif; ?>
        </script>

        <!-- Bootstrap core JavaScript-->
        <script src="<?php echo base_url(); ?>assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

        <!-- Core plugin JavaScript-->
        <script src="<?php echo base_url(); ?>assets/vendor/jquery-easing/jquery.easing.min.js"></script>

        <!-- Custom scripts for all pages-->
        <script src="<?php echo base_url(); ?>assets/js/sb-admin-2.min.js"></script>
        <?php
        $googleMapsApiKey = $this->config->item('google_maps_api_key');
        $mapProvider = $this->config->item('map_provider');
        ?>
        <?php if ($mapProvider === 'google' && !empty($googleMapsApiKey)): ?>
            <script src="https://maps.googleapis.com/maps/api/js?key=<?= rawurlencode($googleMapsApiKey); ?>&language=id&libraries=places"></script>
        <?php endif; ?>

        </body>

        </html>
