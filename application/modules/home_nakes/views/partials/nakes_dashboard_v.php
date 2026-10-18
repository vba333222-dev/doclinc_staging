<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
				<div id="beranda" class="content <?= (isset($nakes_initial_section) && $nakes_initial_section === 'operasional') ? '' : 'active'; ?>">
					<div class="dl-dashboard-stack nakes-dashboard-workspace">
						<?php
						$role_prerequisite_state = isset($role_prerequisite_state) && is_array($role_prerequisite_state) ? $role_prerequisite_state : array();
						$role_prerequisite_blocked = !empty($role_prerequisite_state['enforced']) && empty($role_prerequisite_state['allowed']);
						$role_prerequisite_incomplete = array_key_exists('complete', $role_prerequisite_state) && empty($role_prerequisite_state['complete']);
						$role_prerequisite_labels = !empty($role_prerequisite_state['missing_labels']) && is_array($role_prerequisite_state['missing_labels'])
							? array_slice($role_prerequisite_state['missing_labels'], 0, 3)
							: array();
						$role_prerequisite_extra_count = !empty($role_prerequisite_state['missing_labels']) && is_array($role_prerequisite_state['missing_labels'])
							? max(0, count($role_prerequisite_state['missing_labels']) - count($role_prerequisite_labels))
							: 0;
						$role_prerequisite_cta_url = !empty($role_prerequisite_state['cta_url']) ? (string) $role_prerequisite_state['cta_url'] : '';
						$role_prerequisite_cta_label = !empty($role_prerequisite_state['cta_label']) ? (string) $role_prerequisite_state['cta_label'] : '';
						?>
						<?php if ($role_prerequisite_incomplete) : ?>
							<div class="alert <?= $role_prerequisite_blocked ? 'alert-warning' : 'alert-info'; ?> d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2 mb-0" role="<?= $role_prerequisite_blocked ? 'alert' : 'status'; ?>" data-role-prerequisite-alert data-enforced="<?= $role_prerequisite_blocked ? 'true' : 'false'; ?>">
								<div>
									<strong><?= $role_prerequisite_blocked ? 'Data profil wajib dilengkapi.' : (!empty($nakes_is_command_center) ? 'Data Puskesmas belum lengkap.' : 'Data profesi Anda belum lengkap.'); ?></strong>
									<span><?= html_escape(!empty($role_prerequisite_labels) ? 'Data yang belum tercatat: ' . implode(', ', $role_prerequisite_labels) . ($role_prerequisite_extra_count > 0 ? ', serta ' . $role_prerequisite_extra_count . ' data lainnya' : '') . '.' : 'Lengkapi data profil untuk melanjutkan.'); ?></span>
									<?php if (!$role_prerequisite_blocked) : ?><span class="d-block small mt-1">Aplikasi tetap dapat digunakan.</span><?php endif; ?>
								</div>
								<?php if ($role_prerequisite_cta_url !== '') : ?>
									<a class="btn btn-warning btn-sm" href="#profile" onclick="showContent('profile')"><?= html_escape($role_prerequisite_cta_label !== '' ? $role_prerequisite_cta_label : 'Lengkapi profil'); ?></a>
								<?php else : ?>
									<span class="small fw-semibold">Hubungi Admin Dinas Kesehatan.</span>
								<?php endif; ?>
							</div>
						<?php endif; ?>
						<?php if (!empty($nakes_is_personal)) : ?>
							<div class="alert alert-info mb-0" role="status">Hanya tugas Anda yang tampil di sini.</div>
						<?php endif; ?>
						<?php $this->load->view('partials/nakes_stat_cards_v', get_defined_vars()); ?>
						<?php if (!empty($nakes_is_command_center)) : ?>
						<?php
						$dashboard_staff_rows = isset($puskesmas_staff_list) && is_array($puskesmas_staff_list) ? $puskesmas_staff_list : array();
						$dashboard_staff_count = isset($puskesmas_staff_count) ? (int) $puskesmas_staff_count : count($dashboard_staff_rows);
						?>
						<div class="dl-nakes-card dl-dashboard-section nk-staff-card doclinc-presence-panel" id="doclincNakesPresence" data-presence-roster="true" aria-live="polite" aria-busy="<?= !empty($nakes_presence_enabled) ? 'true' : 'false'; ?>">
						<?php $this->load->view('partials/nakes_section_header_v', array(
							'section_title' => 'Staf Puskesmas',
							'section_meta' => !empty($nakes_presence_enabled) ? 'Status diperbarui otomatis' : (string) $dashboard_staff_count . ' terdaftar',
							'section_inside' => true,
						)); ?>

							<?php if (empty($dashboard_staff_rows)) : ?>
								<div class="nk-staff-empty">Belum ada staf.</div>
							<?php else : ?>
								<div class="nk-staff-list nk-staff-list--presence">
									<?php foreach ($dashboard_staff_rows as $staff) :
										$staff_name = trim((string) (isset($staff->nama) ? $staff->nama : ''));
										$staff_profesi = trim((string) (isset($staff->profesi) ? $staff->profesi : ''));
										$staff_phone = trim((string) (isset($staff->no_hp) ? $staff->no_hp : ''));
										$staff_sip = trim((string) (isset($staff->nomor_sip) ? $staff->nomor_sip : ''));
										$staff_user_id = isset($staff->profile_user_id) ? (int) $staff->profile_user_id : (isset($staff->user_id) ? (int) $staff->user_id : 0);
										$staff_photo = isset($staff->profile_photo) ? trim((string) $staff->profile_photo) : '';
									?>
										<div class="nk-staff-item nk-staff-item--presence" data-presence-user-id="<?= html_escape((string) $staff_user_id); ?>">
											<?php $this->load->view('partials/nakes_avatar_v', array(
												'avatar_name' => $staff_name,
												'avatar_photo' => $staff_photo,
												'avatar_user_id' => $staff_user_id,
												'avatar_alt' => 'Foto ' . ($staff_name !== '' ? $staff_name : 'Nakes'),
												'avatar_class' => 'nk-avatar--sm nk-avatar--nakes',
												'avatar_icon' => 'fas fa-user-md',
											)); ?>
											<div class="nk-staff-main">
												<strong><?= html_escape($staff_name !== '' ? $staff_name : 'Nama belum diisi'); ?></strong>
												<?php if ($staff_profesi !== '') : ?><span><?= html_escape($staff_profesi); ?></span><?php endif; ?>
												<span class="nk-staff-presence-chip">
											<span class="doclinc-presence-dot is-offline" data-presence-dot aria-hidden="true"></span>
											<small class="nk-staff-presence is-offline" data-presence-label>Offline</small>
										</span>
												<small class="nk-staff-last-seen" data-presence-last-seen>Belum pernah online</small>
											</div>
											<?php if ($staff_phone !== '') : ?>
												<div class="nk-staff-meta"><span><?= html_escape($staff_phone); ?></span></div>
											<?php endif; ?>
										</div>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						</div>
						<?php endif; ?>
						
											</div>
				</div>
