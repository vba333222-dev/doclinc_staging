<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Kode aktivasi akun nakes: sekali pakai, berbatas waktu.
 *
 * Peran dalam serah-terima
 * ------------------------
 * Admin Dinas Kesehatan tidak lagi hanya menyerahkan password sementara. Ia
 * menyerahkan **kode 8 karakter** yang dibacakan/dikirim ke nakes; nakes
 * memakainya di halaman aktivasi dan langsung membuat password pribadinya.
 * Password sementara tetap bekerja (jalur lama tidak dimatikan), tetapi kode ini
 * yang punya masa berlaku dan hanya bisa dipakai sekali.
 *
 * Cara penyimpanan, dan batasnya
 * -----------------------------
 * Yang disimpan adalah `hash('sha256', KODE . pepper)` dengan pepper dari
 * `DOCLINC_ENCRYPTION_KEY`. Hash cepat dipakai **bukan** karena dianggap setara
 * bcrypt, melainkan karena kode harus bisa dicari langsung saat dipakai (bcrypt
 * tidak bisa). Ruang kode hanya 31^8 ≈ 8,5e11, jadi keamanannya bertumpu pada
 * tiga hal yang harus tetap ada: sekali pakai (`used_at`), masa berlaku
 * (`expires_at`), dan pembatasan percobaan masuk di halaman aktivasi. Bila salah
 * satu dilepas, hashing cepat ini tidak lagi cukup.
 *
 * Fail-closed: bila pepper tidak tersedia, `issue()` menolak membuat kode.
 */
class Nakes_activation_code
{
	const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
	const LENGTH = 8;
	const TABLE = 'nakes_activation_codes';

	private $db;
	private $pepper;
	private $ttl_seconds;

	public function __construct($db, $pepper = null, array $config = array())
	{
		$this->db = $db;
		$this->pepper = is_string($pepper) ? trim($pepper) : '';
		$this->ttl_seconds = max(300, (int) ($config['ttl_seconds'] ?? 604800));
	}

	public function failSafe()
	{
		return $this->db->table_exists(self::TABLE) && $this->pepper !== '';
	}

	public function ttl_seconds()
	{
		return $this->ttl_seconds;
	}

	/** Kode dibaca manusia: huruf besar, tanpa spasi/tanda hubung. */
	public function normalize($code)
	{
		$code = strtoupper((string) $code);
		$code = preg_replace('/[^A-Z0-9]/', '', $code);
		return is_string($code) ? $code : '';
	}

	/**
	 * Terbitkan kode baru. Kode sebelumnya yang belum terpakai langsung
	 * dibatalkan supaya hanya ada satu kode hidup per akun.
	 *
	 * @return string|null kode dalam bentuk terbaca, atau null bila gagal
	 */
	public function issue($user_id, $issued_by_user_id = null, $reason = 'account_created')
	{
		$user_id = (int) $user_id;
		if ($user_id < 1 || !$this->failSafe()) {
			return null;
		}
		$code = $this->generate();
		if ($code === null) {
			return null;
		}
		$now = date('Y-m-d H:i:s');
		/* Urutan dan ketiadaan transaksi eksplisit di sini disengaja:
		   `trans_begin()`/`trans_commit()` di dalam transaksi pemanggil akan
		   meng-commit transaksi luar (perilaku CodeIgniter 3), sehingga pustaka ini
		   tidak bisa dipakai di dalam transaksi uji maupun transaksi pemanggil.
		   Kode baru disimpan lebih dulu, kode lama dibatalkan setelahnya; kalau
		   pembatalan gagal, kode baru tetap sah dan Admin cukup menerbitkan ulang. */
		$inserted = $this->db->insert(self::TABLE, array(
			'user_id' => $user_id,
			'code_hash' => $this->hash($code),
			'expires_at' => date('Y-m-d H:i:s', time() + $this->ttl_seconds),
			'used_at' => null,
			'issued_by_user_id' => $issued_by_user_id === null ? null : (int) $issued_by_user_id,
			'issued_reason' => substr((string) $reason, 0, 40),
			'created_at' => $now,
		));
		if (!$inserted) {
			return null;
		}
		$new_id = (int) $this->db->insert_id();
		$this->db->where('user_id', $user_id);
		$this->db->where('id !=', $new_id);
		$this->db->where('used_at IS NULL', null, false);
		$this->db->update(self::TABLE, array('used_at' => $now));
		return $code;
	}

	/**
	 * Pakai kode: tandai terpakai dan kembalikan user_id.
	 *
	 * Perubahan status dilakukan dengan syarat "belum terpakai dan belum
	 * kedaluwarsa" (`compare-and-swap`), jadi dua permintaan bersamaan tidak bisa
	 * sama-sama berhasil.
	 *
	 * @return int|null
	 */
	public function consume($code)
	{
		$normalized = $this->normalize($code);
		if (strlen($normalized) !== self::LENGTH || !$this->failSafe()) {
			return null;
		}
		$row = $this->db
			->select('id, user_id')
			->where('code_hash', $this->hash($normalized))
			->where('used_at IS NULL', null, false)
			->where('expires_at >', date('Y-m-d H:i:s'))
			->limit(1)
			->get(self::TABLE)
			->row();
		if (!$row) {
			return null;
		}
		$this->db->where('id', (int) $row->id);
		$this->db->where('used_at IS NULL', null, false);
		$this->db->where('expires_at >', date('Y-m-d H:i:s'));
		$this->db->update(self::TABLE, array('used_at' => date('Y-m-d H:i:s')));
		if ((int) $this->db->affected_rows() !== 1) {
			return null;
		}
		return (int) $row->user_id;
	}

	/** Kode hidup milik satu akun, untuk ditampilkan sebagai keadaan di panel. */
	public function activeFor($user_id)
	{
		if (!$this->failSafe()) {
			return null;
		}
		return $this->db
			->select('id, expires_at, issued_reason, created_at')
			->where('user_id', (int) $user_id)
			->where('used_at IS NULL', null, false)
			->where('expires_at >', date('Y-m-d H:i:s'))
			->order_by('id', 'DESC')
			->limit(1)
			->get(self::TABLE)
			->row();
	}

	/** Batalkan seluruh kode hidup milik satu akun. */
	public function revoke($user_id)
	{
		if (!$this->failSafe()) {
			return 0;
		}
		$this->db->where('user_id', (int) $user_id);
		$this->db->where('used_at IS NULL', null, false);
		$this->db->update(self::TABLE, array('used_at' => date('Y-m-d H:i:s')));
		return (int) $this->db->affected_rows();
	}

	private function hash($code)
	{
		return hash('sha256', (string) $code . '|' . $this->pepper);
	}

	private function generate()
	{
		$alphabet = self::ALPHABET;
		$max = strlen($alphabet) - 1;
		$code = '';
		for ($i = 0; $i < self::LENGTH; $i++) {
			$index = random_int(0, $max);
			$code .= $alphabet[$index];
		}
		return $code === '' ? null : $code;
	}
}
