<?php
/**
 * Bingkai kartu daftar bersama untuk halaman panel admin.
 *
 *   $list_card = array(
 *       'part'   => 'open',                    // wajib: 'open' atau 'close'
 *       'title'  => 'Daftar staf',             // wajib saat part=open
 *       'sub'    => 'Kelola akun login staf.', // opsional, satu baris
 *       'count'  => '12 baris',                // opsional, sudah diformat pemanggil
 *       'action' => array('kind' => 'modal', 'label' => 'Tambah akun',
 *                         'target' => '#modalTambah', 'icon' => 'fas fa-plus-circle',
 *                         'variant' => 'success'),   // opsional
 *       'card_class' => 'doclinc-account-list-card', // opsional, kelas tambahan
 *       'body_class' => 'doclinc-table-body table-responsive', // opsional
 *   );
 *   $this->load->view('commons/list_card', array('list_card' => $list_card));
 *
 * Kenapa dua bagian (`open`/`close`) dan bukan satu panggilan: view CodeIgniter
 * tidak punya slot, jadi isi kartu (toolbar, tabel, daftar kartu) ditulis view
 * pemanggil di antara keduanya. Yang disatukan adalah **bingkainya** — kelas
 * kartu, susunan kepala kartu (judul + keterangan + hitungan + aksi), dan
 * pembungkus isi — karena di situlah markup antar modul berbeda-beda:
 * `shadow` vs `shadow-sm`, `mb-4` ada/tidak, `card-body` vs
 * `card-body doclinc-table-body`, judul tingkat enam berikon atau tidak.
 *
 * Judul kartu dirender `<h2 class="h6">`: tingkat judul untuk struktur (satu
 * `<h1>` per halaman, sisanya `<h2>`), kelas `.h6` untuk ukuran. Pola yang sama
 * sudah dipakai kartu dasbor, jadi tidak ada perubahan tampilan.
 *
 * Aksi kepala kartu memakai bahasa tombol yang sama dengan `commons/page_head`
 * — `btn btn-<varian> dl-adm-action`. Tingginya TIDAK dikecilkan di sini:
 * `assets/css/doclinc-reference-ui.css` menetapkan
 * `min-height: var(--dl-ref-control-primary) !important` (48px) untuk
 * `.doclinc-admin-shell .btn-primary/.btn-success`, dan berkas shell sengaja
 * tidak melawan `!important`. Sebelumnya partial ini menambahkan
 * `dl-adm-action-sm` (34px) yang kalah di runtime, sehingga ukuran yang tertulis
 * di CSS tidak pernah terjadi — dan itu baru terlihat setelah token desain
 * benar-benar termuat. Hierarki "aksi halaman" di atas "aksi daftar" sekarang
 * dibawa oleh penempatan dan judul kartu, bukan oleh tombol di bawah ambang
 * sentuh.
 */
$list_card = (isset($list_card) && is_array($list_card)) ? $list_card : array();

$card_part = isset($list_card['part']) ? strtolower(trim((string) $list_card['part'])) : 'open';

$card_class_pattern = '/^[a-z0-9 _-]*$/';
$card_extra = isset($list_card['card_class']) ? trim((string) $list_card['card_class']) : '';
if (preg_match($card_class_pattern, $card_extra) !== 1) {
	$card_extra = '';
}

$card_body_class = isset($list_card['body_class']) ? trim((string) $list_card['body_class']) : 'doclinc-table-body';
if ($card_body_class === '' || preg_match($card_class_pattern, $card_body_class) !== 1) {
	$card_body_class = 'doclinc-table-body';
}

if ($card_part === 'close') {
	?>
		</div>
	</div>
	<?php
	return;
}

$card_title = isset($list_card['title']) ? trim((string) $list_card['title']) : '';
$card_sub = isset($list_card['sub']) ? trim((string) $list_card['sub']) : '';
$card_count = isset($list_card['count']) ? trim((string) $list_card['count']) : '';
$card_action = (isset($list_card['action']) && is_array($list_card['action'])) ? (array) $list_card['action'] : array();

$card_action_label = isset($card_action['label']) ? trim((string) $card_action['label']) : '';
$card_action_kind = isset($card_action['kind']) ? strtolower(trim((string) $card_action['kind'])) : 'link';
if (!in_array($card_action_kind, array('modal', 'link'), true)) {
	$card_action_kind = 'link';
}
$card_action_target = isset($card_action[$card_action_kind === 'modal' ? 'target' : 'href'])
	? trim((string) $card_action[$card_action_kind === 'modal' ? 'target' : 'href'])
	: '';
$card_action_icon = isset($card_action['icon']) ? strtolower(trim((string) $card_action['icon'])) : '';
if (preg_match('/^fa[srlb]? fa-[a-z0-9-]+(?: fa-[a-z0-9-]+)*$/', $card_action_icon) !== 1) {
	$card_action_icon = '';
}
$card_action_variants = array('primary', 'success', 'danger', 'outline-primary', 'outline-secondary');
$card_action_variant = isset($card_action['variant']) ? trim((string) $card_action['variant']) : 'primary';
if (!in_array($card_action_variant, $card_action_variants, true)) {
	$card_action_variant = 'primary';
}
$card_has_action = $card_action_label !== '' && $card_action_target !== '';
$card_count_class = $card_has_action ? 'badge badge-light border mr-2' : 'badge badge-light border';
$card_classes = 'card shadow mb-4 doclinc-table-card' . ($card_extra !== '' ? ' ' . $card_extra : '');
?>
<div class="<?= html_escape($card_classes); ?>">
	<div class="card-header py-3 d-flex align-items-center justify-content-between">
		<div>
			<?php if ($card_title !== ''): ?>
				<h2 class="h6 m-0 font-weight-bold"><?= html_escape($card_title); ?></h2>
			<?php endif; ?>
			<?php if ($card_sub !== ''): ?>
				<div class="doclinc-muted-text mt-1"><?= html_escape($card_sub); ?></div>
			<?php endif; ?>
		</div>
		<?php if ($card_count !== '' || $card_has_action): ?>
			<div class="d-flex align-items-center">
				<?php if ($card_count !== ''): ?>
					<span class="<?= $card_count_class; ?>"><?= html_escape($card_count); ?></span>
				<?php endif; ?>
				<?php if ($card_has_action && $card_action_kind === 'modal'): ?>
					<button type="button" class="btn btn-<?= html_escape($card_action_variant); ?> dl-adm-action" data-toggle="modal" data-target="<?= html_escape($card_action_target); ?>">
						<?php if ($card_action_icon !== ''): ?><i class="<?= html_escape($card_action_icon); ?>" aria-hidden="true"></i> <?php endif; ?><?= html_escape($card_action_label); ?>
					</button>
				<?php elseif ($card_has_action): ?>
					<a href="<?= html_escape($card_action_target); ?>" class="btn btn-<?= html_escape($card_action_variant); ?> dl-adm-action">
						<?php if ($card_action_icon !== ''): ?><i class="<?= html_escape($card_action_icon); ?>" aria-hidden="true"></i> <?php endif; ?><?= html_escape($card_action_label); ?>
					</a>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
	<div class="card-body <?= html_escape($card_body_class); ?>">
