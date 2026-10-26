<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once dirname(APPPATH, 2) . '/application/libraries/Icd10_code_text.php';

/**
 * Merapikan pengodean diagnosis: mengubah teks yang SUDAH memuat kode ICD-10
  * menjadi baris yang sudah dikode, dan menyediakan jalur manual untuk teks yang tidak
 * memuat kode sama sekali.
 *
 * Masalah yang diselesaikan. Pemilih ICD-10 mengirim "KODE — label", tetapi
 * tenaga kesehatan juga mengetik sendiri, misalnya "Dermatitis atopik (L20.9)".
 * Semua bentuk selain "KODE — label" dulu disimpan sebagai `uncoded`, sehingga
 * diagnosis yang jelas-jelas punya kode tidak pernah masuk peringkat maupun
 * peta. Di sini teks itu dibaca ulang (`Icd10_code_text`) dan kodenya ditulis
 * kembali - hanya bila kodenya benar-benar ada di master ICD-10 repo.
 *
 * Aturan yang dipegang:
 *   - Kode hanya ditulis bila ADA di master `20b_master_diagnosis_icd10_who_2019`
 *     DAN `dapat_dipilih_dalam_diagnosis` = true. Kategori seperti `A09`
 *     sengaja tidak dipakai otomatis: pemilih diagnosis pun tidak menawarkannya,
 *     dan memilih subkode adalah keputusan klinis, bukan pekerjaan mesin.
 *   - Tidak pernah menimpa baris yang sudah punya kode, tidak pernah DELETE.
 *     Syaratnya selalu `coding_state <> 'coded'` DAN `diagnosis_code` kosong,
 *     jadi menjalankan ulang tidak mengubah apa pun (idempoten).
 *   - `display_text` TIDAK diubah. Itu catatan apa yang diketik tenaga
 *     kesehatan; yang diperbaiki hanya kolom kode.
 *   - `suggestion_term_id` dibiarkan apa adanya: id itu milik tabel
 *     `clinical_suggestion_terms`, dan panel ini bekerja dari master berkas
 *     (sumber yang sama dengan Penjelajah ICD-10).
 */
class Diagnosis_coding_m extends CI_Model
{
	/** Sumber & versi yang ditulis ke `reference_source`/`reference_version`. */
	const SOURCE_DATASET = '20b_master_diagnosis_icd10_who_2019.json';
	const SOURCE_VERSION = '2019';

	/** Status tiap teks: siap dikode otomatis, perlu diperiksa, perlu dipilih manual. */
	const STATUS_READY = 'ready';
	const STATUS_REVIEW = 'review';
	const STATUS_MANUAL = 'manual';

	private $master_cache = null;
	private $icd_model = null;

	/** Kolom yang dipakai untuk menulis balik kode sudah ada? */
	public function schemaReady()
	{
		if (!$this->db->table_exists('medicalrecord_diagnoses')) {
			return false;
		}
		foreach (array('coding_state', 'diagnosis_code', 'diagnosis_label', 'display_text') as $field) {
			if (!$this->db->field_exists($field, 'medicalrecord_diagnoses')) {
				return false;
			}
		}
		return true;
	}

	/** Model Penjelajah ICD-10, atau NULL bila modulnya tidak ada. */
	private function icd()
	{
		if ($this->icd_model === null) {
			if (!is_file(APPPATH . 'modules/icd10/models/Icd10_m.php')) {
				return null;
			}
			$this->load->model('icd10/Icd10_m');
			$this->icd_model = $this->Icd10_m;
		}
		return $this->icd_model;
	}

	/**
	 * Peta `kode => ['code','label','label_id','selectable','level']` dari master
	 * repo. Kosong berarti master tidak bisa dibaca - dan dalam keadaan itu panel
	 * TIDAK boleh menulis apa pun, karena tidak ada dasar untuk memastikan
	 * sebuah kode sah.
	 */
	public function master()
	{
		if ($this->master_cache !== null) {
			return $this->master_cache;
		}
		$icd = $this->icd();
		if ($icd === null) {
			$this->master_cache = array();
			return $this->master_cache;
		}
		$map = array();
		foreach ($icd->codeIndex() as $code => $entry) {
			$map[(string) $code] = array(
				'code' => (string) $code,
				'label' => trim((string) ($entry['nama'] ?? '')),
				/* Nama Indonesia masih draft (53 kode) - ditampilkan sebagai
				 * keterangan tambahan, tidak pernah menggantikan judul resmi. */
				'label_id' => trim((string) ($entry['nama_indonesia'] ?? '')),
				'selectable' => !empty($entry['dapat_dipilih']),
				'level' => (string) ($entry['level'] ?? ''),
			);
		}
		$this->master_cache = $map;
		return $this->master_cache;
	}

	public function masterAvailable()
	{
		return count($this->master()) > 0;
	}

	/**
	 * Beri status pada setiap kelompok teks dari
	 * `Diagnosis_infographic_m::getUncodedTextGroups()` dan hitung totalnya.
	 */
	public function analyze(array $groups)
	{
		$master_available = $this->masterAvailable();
		$master = $this->master();
		$decorated = array();
		$totals = array(
			'groups' => 0,
			'rows' => 0,
			'ready_groups' => 0,
			'ready_rows' => 0,
			'review_groups' => 0,
			'review_rows' => 0,
			'manual_groups' => 0,
			'manual_rows' => 0,
		);
		foreach ($groups as $group) {
			$row = $this->describe($group, $master, $master_available);
			$decorated[] = $row;
			$totals['groups']++;
			$totals['rows'] += (int) $row['entries'];
			$key = $row['status'] . '_';
			if (isset($totals[$key . 'groups'])) {
				$totals[$key . 'groups']++;
				$totals[$key . 'rows'] += (int) $row['entries'];
			}
		}
		return array(
			'master_available' => $master_available,
			'groups' => $decorated,
			'totals' => $totals,
		);
	}

	/** Satu kelompok teks: kode apa yang terbaca, dan apa yang menghalanginya. */
	private function describe(array $group, array $master, $master_available)
	{
		$text = isset($group['text']) ? (string) $group['text'] : '';
		$parsed = Icd10_code_text::extract($text);
		$code = $parsed['code'];
		$label = $parsed['label'] !== '' ? $parsed['label'] : (string) ($group['label'] ?? '');
		$status = self::STATUS_MANUAL;
		$reason = 'no_code_in_text';
		$code_label = '';

		if ($code !== null) {
			$status = self::STATUS_REVIEW;
			if (!$master_available) {
				$reason = 'master_unavailable';
			} elseif (!isset($master[$code])) {
				$reason = 'code_not_in_master';
			} elseif (empty($master[$code]['selectable'])) {
				$reason = 'code_not_selectable';
				$code_label = (string) $master[$code]['label'];
			} else {
				$status = self::STATUS_READY;
				$reason = 'code_in_text';
				$code_label = (string) $master[$code]['label'];
			}
		}

		/* Label yang ditulis ke `diagnosis_label` bila kode benar-benar dipakai:
		 * teks yang diketik tanpa kode, atau judul master bila teksnya hanya
		 * berisi kode ("L20.9"). */
		$write_label = trim($label);
		if ($write_label === '' && $code !== null && isset($master[$code])) {
			$write_label = (string) $master[$code]['label'];
		}

		return array(
			'text' => $text,
			'label' => $label,
			'entries' => isset($group['entries']) ? (int) $group['entries'] : 0,
			'records' => isset($group['records']) ? (int) $group['records'] : 0,
			'last_seen' => isset($group['last_seen']) ? (string) $group['last_seen'] : '',
			'code' => $code,
			'code_label' => $code_label,
			'write_label' => $write_label,
			'source' => $parsed['source'],
			'status' => $status,
			'reason' => $reason,
		);
	}

	/**
	 * Tulis kode untuk semua kelompok berstatus READY.
	 * Mengembalikan jumlah baris & teks yang benar-benar berubah.
	 */
	public function repair(array $groups)
	{
		$analysis = $this->analyze($groups);
		if (!$analysis['master_available']) {
			return array('ok' => false, 'reason' => 'master_unavailable', 'rows' => 0, 'texts' => 0);
		}
		$rows = 0;
		$texts = 0;
		$this->db->trans_begin();
		foreach ($analysis['groups'] as $group) {
			if ($group['status'] !== self::STATUS_READY) {
				continue;
			}
			$affected = $this->write($group['text'], $group['code'], $group['write_label']);
			if ($affected > 0) {
				$rows += $affected;
				$texts++;
			}
		}
		if ($this->db->trans_status() === false) {
			$this->db->trans_rollback();
			return array('ok' => false, 'reason' => 'transaction_failed', 'rows' => 0, 'texts' => 0);
		}
		$this->db->trans_commit();
		return array('ok' => true, 'reason' => null, 'rows' => $rows, 'texts' => $texts);
	}

	/**
	 * Kodakan satu teks dengan kode pilihan admin (jalur manual). Kode tetap
	 * harus ada di master dan boleh dipakai; kalau tidak, tidak ada yang ditulis.
	 */
	public function assign($text, $code)
	{
		$text = (string) $text;
		$code = strtoupper(trim((string) $code));
		if ($text === '') {
			return array('ok' => false, 'reason' => 'text_missing', 'rows' => 0, 'code' => null, 'label' => '');
		}
		if (!Icd10_code_text::isCode($code)) {
			return array('ok' => false, 'reason' => 'invalid_code', 'rows' => 0, 'code' => null, 'label' => '');
		}
		if (!$this->masterAvailable()) {
			return array('ok' => false, 'reason' => 'master_unavailable', 'rows' => 0, 'code' => null, 'label' => '');
		}
		$master = $this->master();
		if (!isset($master[$code])) {
			return array('ok' => false, 'reason' => 'code_not_in_master', 'rows' => 0, 'code' => null, 'label' => '');
		}
		if (empty($master[$code]['selectable'])) {
			return array('ok' => false, 'reason' => 'code_not_selectable', 'rows' => 0, 'code' => null, 'label' => '');
		}

		/* Label yang ditulis ke `diagnosis_label`:
		 *   1. teks hanya berisi kode ("A09")     -> judul master kode pilihan;
		 *   2. kode di teks = kode pilihan, atau kode di teks adalah induk/
		 *      subkode dari pilihan ("A09" -> "A09.0") -> teks tanpa kode itu
		 *      ("Dispepsia (K29.7)" + K29.7 -> "Dispepsia");
		 *   3. selain itu teks dibiarkan apa adanya ("Vitamin B12" + D51.9),
		 *      karena membuang "B12" akan menghapus bagian keterangan dokter. */
		$parsed = Icd10_code_text::extract($text);
		$label = trim($parsed['label']);
		$parsed_code = $parsed['code'];
		$same_family = $parsed_code !== null && (
			$parsed_code === $code
			|| strpos($code, $parsed_code . '.') === 0
			|| strpos($parsed_code, $code . '.') === 0
		);
		if ($label === '') {
			$label = (string) $master[$code]['label'];
		} elseif (!$same_family) {
			$label = Icd10_code_text::clean($text);
		}
		$this->db->trans_begin();
		$rows = $this->write($text, $code, $label);
		if ($this->db->trans_status() === false) {
			$this->db->trans_rollback();
			return array('ok' => false, 'reason' => 'transaction_failed', 'rows' => 0, 'code' => $code, 'label' => $label);
		}
		$this->db->trans_commit();
		return array('ok' => true, 'reason' => null, 'rows' => $rows, 'code' => $code, 'label' => $label);
	}

	/**
	 * UPDATE satu teks. Syaratnya sengaja ketat: hanya baris yang belum dikode
	 * DAN belum punya kode. Baris yang sudah benar tidak pernah disentuh.
	 */
	private function write($text, $code, $label)
	{
		$text = (string) $text;
		$label = trim((string) $label);
		if ($text === '' || $label === '' || !Icd10_code_text::isCode($code)) {
			return 0;
		}
		if (function_exists('mb_substr') && mb_strlen($label, 'UTF-8') > 255) {
			$label = mb_substr($label, 0, 255, 'UTF-8');
		}
		$update = array(
			'diagnosis_code' => (string) $code,
			'diagnosis_label' => $label,
			'coding_state' => 'coded',
			'reference_source' => self::SOURCE_DATASET,
			'reference_version' => self::SOURCE_VERSION,
		);
		if ($this->db->field_exists('updated_at', 'medicalrecord_diagnoses')) {
			$update['updated_at'] = date('Y-m-d H:i:s');
		}
		$this->db->where('display_text', $text);
		$this->db->where("(coding_state IS NULL OR coding_state <> 'coded')", NULL, FALSE);
		$this->db->where("(diagnosis_code IS NULL OR TRIM(diagnosis_code) = '')", NULL, FALSE);
		$this->db->update('medicalrecord_diagnoses', $update);
		return (int) $this->db->affected_rows();
	}

	/**
	 * Pencarian master untuk pemilih manual. Hanya kode yang boleh dipakai
	 * sebagai diagnosis (`dapat_dipilih_dalam_diagnosis`) yang ditampilkan -
	 * menampilkan kategori yang tidak bisa dipakai hanya menambah jalan buntu.
	 */
	public function search($query, $limit = 25)
	{
		$query = trim((string) $query);
		$limit = (int) $limit;
		if ($limit < 1 || $limit > 50) {
			$limit = 25;
		}
		$icd = $this->icd();
		if ($icd === null) {
			return array('available' => false, 'query' => $query, 'rows' => array(), 'total' => 0);
		}
		if ($query === '') {
			return array('available' => true, 'query' => '', 'rows' => array(), 'total' => 0);
		}
		$result = $icd->search($query, 200);
		$needle = strtoupper($query);
		$items = array();
		$strong = false;
		foreach ($result['rows'] as $row) {
			if (empty($row['dapat_dipilih'])) {
				continue;
			}
			$code = strtoupper((string) $row['kode']);
			$name = strtoupper((string) $row['nama']);
			/* Nama Indonesia ditampilkan di bawah judul resmi, jadi ia juga
			 * dihitung sebagai kecocokan: admin mengetik "demam" dan melihat
			 * "Fever, unspecified / Demam, tidak spesifik" - memberinya
			 * peringatan "hanya cocok sebagian" di situ justru menyesatkan. */
			$label_id = trim((string) ($row['nama_indonesia'] !== '' ? $row['nama_indonesia'] : $row['alias_indonesia']));
			$name_id = strtoupper($label_id);
			$score = 4;
			if ($code === $needle) {
				$score = 0;
			} elseif (strpos($code, $needle) === 0) {
				$score = 1;
			} elseif (strpos($name, $needle) === 0) {
				$score = 2;
			} elseif ($name_id !== '' && strpos($name_id, $needle) === 0) {
				$score = 2;
			} elseif (strpos($name, $needle) !== false) {
				$score = 3;
			} elseif ($name_id !== '' && strpos($name_id, $needle) !== false) {
				$score = 3;
			}
			if ($score <= 2) {
				$strong = true;
			}
			$items[] = array(
				'score' => $score,
				'code' => $code,
				'label' => (string) $row['nama'],
				'label_id' => $label_id,
				'chapter' => (string) $row['chapter_name'],
			);
		}
		usort($items, function ($left, $right) {
			if ($left['score'] !== $right['score']) {
				return $left['score'] < $right['score'] ? -1 : 1;
			}
			return strcmp($left['code'], $right['code']);
		});
		return array(
			'available' => true,
			'query' => $query,
			'rows' => array_slice($items, 0, $limit),
			'total' => count($items),
			/* Tidak ada satu pun kecocokan kuat (kode sama, kode berawalan,
			 * atau judul berawalan). Halaman pemilih memakai ini untuk
			 * memperingatkan bahwa hasilnya hanya cocok sebagian - misalnya
			 * "ISPA" yang ikut cocok pada "Epispadias". */
			'weak' => count($items) > 0 && !$strong,
		);
	}

	/**
	 * Kalimat penjelas untuk admin. Ditulis di satu tempat supaya tabel, pesan
	 * setelah menyimpan, dan halaman pemilih memakai kata yang sama.
	 */
	public static function reasonLabel($reason, $code = null)
	{
		switch ((string) $reason) {
			case 'code_in_text':
				return 'Kode tertulis di teks dan ada di daftar ICD-10.';
			case 'code_not_selectable':
				return ($code === null ? 'Kode yang tertulis' : 'Kode ' . $code)
					. ' adalah kode induk (kategori), bukan diagnosis. Pilih subkodenya.';
			case 'code_not_in_master':
				return ($code === null ? 'Kode di dalam teks' : 'Kode ' . $code)
					. ' tidak ada di daftar ICD-10.';
			case 'master_unavailable':
				return 'Daftar kode ICD-10 tidak bisa dibaca.';
			case 'no_code_in_text':
				return 'Tidak ada kode di teks ini.';
			case 'invalid_code':
				return 'Bukan bentuk kode ICD-10 (contoh yang benar: L20.9).';
			case 'text_missing':
				return 'Teks diagnosis tidak ikut terkirim.';
			default:
				return 'Perlu diperiksa.';
		}
	}
}
