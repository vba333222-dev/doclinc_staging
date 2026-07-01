<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
				<div id="profile" class="content animate__animated animate__fadeInUp animate__faster">
					<div class="card shadow border-0 rounded-4">
						<div class="card-body">
							<h4 class="card-title text-center text-success mb-4">Profil Pengguna</h4>
							<div class="form-floating mb-3">
								<input type="text" class="form-control shadow-sm border-success" id="nama_lengkap" value="<?= $this->session->userdata('nama'); ?>" placeholder="Nama Lengkap" readonly>
								<label for="nama_lengkap"><i class="bi bi-person-fill me-2"></i>Nama Lengkap</label>
							</div>
							<div class="form-floating mb-3">
								<input type="date" class="form-control shadow-sm border-success" id="tgl" value="<?= $profile['tgl'] ?>" placeholder="Tanggal Lahir" readonly>
								<label for="tgl"><i class="bi bi-calendar-event-fill me-2"></i>Tanggal Lahir</label>
							</div>
							<div class="form-floating mb-3">
								<input type="text" class="form-control shadow-sm border-success" id="jk" value="<?= $profile['gender'] ?>" placeholder="Jenis Kelamin" readonly>
								<label for="jk"><i class="bi bi-gender-ambiguous me-2"></i>Jenis Kelamin</label>
							</div>
							<div class="form-floating mb-3">
								<input type="text" class="form-control shadow-sm border-success" id="no_hp" value="<?= $profile['no_hp'] ?>" placeholder="Nomor HP" readonly>
								<label for="no_hp"><i class="bi bi-telephone-fill me-2"></i>Nomor HP</label>
							</div>
							<div class="form-floating mb-3">
								<textarea class="form-control shadow-sm border-success" placeholder="Alamat" id="alamat" readonly style="height: 100px"><?= $profile['alamat'] ?></textarea>
								<label for="alamat"><i class="bi bi-geo-alt-fill me-2"></i>Alamat</label>
							</div>
							<div class="d-grid gap-2">
								<button type="button" class="btn btn-success shadow-sm" data-bs-toggle="modal" data-bs-target="#modalProfil">
									<i class="bi bi-pencil-fill me-2"></i>Edit Profil
								</button>
								<button type="button" class="btn btn-danger shadow-sm" id="btn-logout">
									<i class="bi bi-box-arrow-right me-2"></i>Logout
								</button>
							</div>
						</div>
					</div>
				</div>
