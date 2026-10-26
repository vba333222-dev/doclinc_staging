<?php
/**
 * Keadaan kosong bersama untuk daftar dan tabel panel admin.
 *
 *   $empty_state = array(
 *       'title'   => 'Belum ada staf terdaftar',        // wajib
 *       'message' => 'Staf yang ditambahkan akan muncul di sini.', // opsional
 *       'icon'    => 'fas fa-user-nurse',               // opsional
 *       'action'  => array('label' => 'Tambah staf', 'href' => site_url('kelola_staff_puskesmas/create'),
 *                          'icon' => 'fas fa-plus-circle', 'variant' => 'primary'), // opsional
 *   );
 *   $this->load->view('commons/empty_state', array('empty_state' => $empty_state));
 *
 * Bentuknya memakai `.doclinc-empty-state` yang sudah dipakai dasbor, ditambah
 * tiga bagian baru (`.dl-adm-empty-title`, `-note`, `-actions`) yang diatur di
 * assets/css/doclinc-admin-shell.css.
 *
 * Kenapa judul dan keterangan dipisah: keadaan kosong yang hanya berisi satu
 * kalimat menyamakan tiga hal yang berbeda — daftar memang kosong, filter tidak
 * menemukan apa pun, dan datanya tidak bisa dibaca. Judul menyebut keadaan,
 * keterangan menyebut langkah berikutnya.
 */
$empty_state = (isset($empty_state) && is_array($empty_state)) ? $empty_state : array();

$empty_title = isset($empty_state['title']) ? trim((string) $empty_state['title']) : '';
$empty_message = isset($empty_state['message']) ? trim((string) $empty_state['message']) : '';
$empty_icon = isset($empty_state['icon']) ? strtolower(trim((string) $empty_state['icon'])) : '';
$empty_action = (isset($empty_state['action']) && is_array($empty_state['action'])) ? $empty_state['action'] : array();

if (preg_match('/^fa[srlb]? fa-[a-z0-9-]+(?: fa-[a-z0-9-]+)*$/', $empty_icon) !== 1) {
	$empty_icon = 'fas fa-inbox';
}

$empty_action_label = isset($empty_action['label']) ? trim((string) $empty_action['label']) : '';
$empty_action_href = isset($empty_action['href']) ? (string) $empty_action['href'] : '';
$empty_action_icon = isset($empty_action['icon']) ? strtolower(trim((string) $empty_action['icon'])) : '';
if (preg_match('/^fa[srlb]? fa-[a-z0-9-]+(?: fa-[a-z0-9-]+)*$/', $empty_action_icon) !== 1) {
	$empty_action_icon = '';
}

$empty_action_variants = array('primary', 'success', 'danger', 'outline-primary', 'outline-secondary');
$empty_action_variant = isset($empty_action['variant']) ? trim((string) $empty_action['variant']) : 'outline-primary';
if (!in_array($empty_action_variant, $empty_action_variants, true)) {
	$empty_action_variant = 'outline-primary';
}
?>
<div class="doclinc-empty-state dl-adm-empty">
	<i class="<?= html_escape($empty_icon); ?>" aria-hidden="true"></i>
	<?php if ($empty_title !== ''): ?>
		<p class="dl-adm-empty-title"><?= html_escape($empty_title); ?></p>
	<?php endif; ?>
	<?php if ($empty_message !== ''): ?>
		<p class="dl-adm-empty-note"><?= html_escape($empty_message); ?></p>
	<?php endif; ?>
	<?php if ($empty_action_label !== '' && $empty_action_href !== ''): ?>
		<p class="dl-adm-empty-actions">
			<a href="<?= html_escape($empty_action_href); ?>" class="btn btn-<?= html_escape($empty_action_variant); ?> dl-adm-action">
				<?php if ($empty_action_icon !== ''): ?><i class="<?= html_escape($empty_action_icon); ?>" aria-hidden="true"></i> <?php endif; ?><?= html_escape($empty_action_label); ?>
			</a>
		</p>
	<?php endif; ?>
</div>
