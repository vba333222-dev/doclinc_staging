<?php
/**
 * Kepala halaman bersama untuk seluruh halaman panel admin.
 *
 * Dipakai dari dalam view dengan satu array deskriptif:
 *
 *   $page_head = array(
 *       'title'   => 'Staf Puskesmas',                       // wajib
 *       'sub'     => 'Data staf dan akun login per Puskesmas.', // opsional
 *       'actions' => array(                                  // opsional
 *           array('kind' => 'help', 'label' => 'Penjelasan pengelolaan staf', 'target' => '#dlStaffHelp'),
 *           array('kind' => 'link', 'label' => 'Tambah staf', 'href' => site_url('kelola_staff_puskesmas/create'),
 *                 'icon' => 'fas fa-plus-circle', 'variant' => 'success'),
 *       ),
 *   );
 *   $this->load->view('commons/page_head', array('page_head' => $page_head));
 *
 * Mengapa deskriptor, bukan HTML jadi: satu-satunya alasan kepala halaman
 * menyimpang adalah markupnya dikarang ulang di tiap view. Kalau partial ini
 * menerima potongan HTML, kebiasaan itu tidak berubah — pemanggil tetap bisa
 * menulis <a class="btn btn-sm ..."> sendiri. Dengan deskriptor, kelas tombol,
 * ukuran sasaran sentuh, `aria-hidden` pada ikon, dan `aria-label` pada tombol
 * ikon dipaksakan di satu tempat.
 *
 * Kelas CSS-nya sudah ada di assets/css/doclinc-admin-shell.css dan berkas itu
 * dimuat paling akhir oleh commons/header.php, jadi tidak ada aturan yang perlu
 * `!important` untuk menang atas custom.css.
 */
$page_head = (isset($page_head) && is_array($page_head)) ? $page_head : array();

$head_title = isset($page_head['title']) ? trim((string) $page_head['title']) : '';
$head_sub = isset($page_head['sub']) ? trim((string) $page_head['sub']) : '';
$head_actions = (isset($page_head['actions']) && is_array($page_head['actions'])) ? $page_head['actions'] : array();

/* Ikon hanya boleh berupa nama kelas Font Awesome. Nilai yang tidak cocok
   dibuang, bukan di-escape lalu ditempel: atribut class yang di-escape tetap
   bisa menyelipkan kelas lain kalau pemanggil keliru. */
$head_icon_of = function ($value) {
	$value = strtolower(trim((string) $value));
	return preg_match('/^fa[srlb]? fa-[a-z0-9-]+(?: fa-[a-z0-9-]+)*$/', $value) === 1 ? $value : '';
};

/* Varian tombol dibatasi pada daftar putih; nilai asing jatuh ke `primary`
   supaya salah ketik tidak menghasilkan tombol tanpa warna. */
$head_variant_of = function ($value) {
	$allowed = array('primary', 'success', 'danger', 'outline-primary', 'outline-secondary');
	$value = trim((string) $value);
	return in_array($value, $allowed, true) ? $value : 'primary';
};
?>
<div class="dl-adm-head">
	<div class="dl-adm-head-text">
		<h1 class="dl-adm-head-title"><?= html_escape($head_title); ?></h1>
		<?php if ($head_sub !== ''): ?>
			<p class="dl-adm-head-sub"><?= html_escape($head_sub); ?></p>
		<?php endif; ?>
	</div>
	<?php if (!empty($head_actions)): ?>
		<div class="dl-adm-head-actions">
			<?php foreach ($head_actions as $head_action): ?>
				<?php
				if (!is_array($head_action)) {
					continue;
				}
				$head_kind = isset($head_action['kind']) ? (string) $head_action['kind'] : 'link';
				$head_label = isset($head_action['label']) ? trim((string) $head_action['label']) : '';
				$head_target = isset($head_action['target']) ? trim((string) $head_action['target']) : '';
				$head_href = isset($head_action['href']) ? (string) $head_action['href'] : '';
				$head_icon = $head_icon_of(isset($head_action['icon']) ? $head_action['icon'] : '');
				$head_variant = $head_variant_of(isset($head_action['variant']) ? $head_action['variant'] : 'primary');

				/* Aksi tanpa label tidak dirender sama sekali. Tombol ikon tanpa
				   nama aksesibel adalah persis cacat yang ingin dicegah berkas ini. */
				if ($head_label === '') {
					continue;
				}
				?>
				<?php if ($head_kind === 'help' && $head_target !== ''): ?>
					<button type="button" class="dl-adm-info" data-toggle="modal" data-target="<?= html_escape($head_target); ?>" aria-label="<?= html_escape($head_label); ?>">
						<i class="fas fa-info-circle" aria-hidden="true"></i>
					</button>
				<?php elseif ($head_kind === 'modal' && $head_target !== ''): ?>
					<button type="button" class="btn btn-<?= html_escape($head_variant); ?> dl-adm-action" data-toggle="modal" data-target="<?= html_escape($head_target); ?>">
						<?php if ($head_icon !== ''): ?><i class="<?= html_escape($head_icon); ?>" aria-hidden="true"></i> <?php endif; ?><?= html_escape($head_label); ?>
					</button>
				<?php elseif ($head_kind === 'link' && $head_href !== ''): ?>
					<a href="<?= html_escape($head_href); ?>" class="btn btn-<?= html_escape($head_variant); ?> dl-adm-action">
						<?php if ($head_icon !== ''): ?><i class="<?= html_escape($head_icon); ?>" aria-hidden="true"></i> <?php endif; ?><?= html_escape($head_label); ?>
					</a>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
