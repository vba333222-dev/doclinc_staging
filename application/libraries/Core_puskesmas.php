<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Sembilan Puskesmas inti Kota Cilegon.
 *
 * Sumber kebenaran kode ada di `doclinc_service_area_definitions()`
 * (`application/helpers/puskesmas_routing_helper.php`): delapan poligon
 * layanan, dan Kecamatan Citangkil dipecah menjadi dua fasilitas. Daftar di
 * kelas ini adalah bentuk datar dari definisi itu, dipakai panel admin supaya
 * tabel Puskesmas tidak lagi bergantung pada isi `m_puskesmas` - basis data
 * staging pernah memuat kode warisan (`PKM08`, `PKM01`) yang membuat jumlah
 * baris berubah setiap kali fixture diisi ulang.
 *
 * Yang TIDAK dilakukan kelas ini: menulis, memigrasi, atau mengganti nama baris
 * `m_puskesmas`. Ia hanya menyatakan kode mana yang inti dan nama resminya,
 * sehingga layar bisa membedakan "fasilitas inti" dari "fasilitas tambahan"
 * tanpa menebak dari data.
 */
class Core_puskesmas
{
	/**
	 * Kode => nama resmi, dalam urutan kecamatan.
	 *
	 * Urutan ini juga urutan tampil di daftar, jadi ia sengaja mengikuti
	 * urutan `doclinc_service_area_definitions()`.
	 */
	const CODES = array(
		'10280101' => 'Puskesmas Cilegon',
		'10280201' => 'Puskesmas Cibeber',
		'10280301' => 'Puskesmas Ciwandan',
		'10280401' => 'Puskesmas Pulomerak',
		'10280402' => 'Puskesmas Gerogol',
		'10280501' => 'Puskesmas Citangkil',
		'10280601' => 'Puskesmas Purwakarta',
		'10280701' => 'Puskesmas Jombang',
		'2241001' => 'Puskesmas Citangkil II',
	);

	/**
	 * Kode => kecamatan. Citangkil memuat dua fasilitas; keduanya berbagi satu
	 * poligon layanan, jadi kecamatannya sama.
	 */
	const KECAMATAN = array(
		'10280101' => 'Cilegon',
		'10280201' => 'Cibeber',
		'10280301' => 'Ciwandan',
		'10280401' => 'Pulomerak',
		'10280402' => 'Gerogol',
		'10280501' => 'Citangkil',
		'10280601' => 'Purwakarta',
		'10280701' => 'Jombang',
		'2241001' => 'Citangkil',
	);

	/**
	 * Kode inti, berurutan.
	 *
	 * @return array<int,string>
	 */
	public function codes()
	{
		return array_keys(self::CODES);
	}

	/**
	 * @param mixed $code
	 * @return bool
	 */
	public function isCore($code)
	{
		return array_key_exists($this->normalize($code), self::CODES);
	}

	/**
	 * Nama resmi untuk kode inti, atau null bila kode bukan inti.
	 *
	 * @param mixed $code
	 * @return string|null
	 */
	public function name($code)
	{
		$code = $this->normalize($code);

		return isset(self::CODES[$code]) ? self::CODES[$code] : null;
	}

	/**
	 * @param mixed $code
	 * @return string
	 */
	public function kecamatan($code)
	{
		$code = $this->normalize($code);

		return isset(self::KECAMATAN[$code]) ? self::KECAMATAN[$code] : '';
	}

	/**
	 * Daftar siap-render: kode, nama, kecamatan.
	 *
	 * @return array<int,array<string,string>>
	 */
	public function rows()
	{
		$rows = array();
		foreach (self::CODES as $code => $name) {
			$rows[] = array(
				'kode_pkm' => (string) $code,
				'nama_puskesmas' => $name,
				'kecamatan' => $this->kecamatan($code),
			);
		}

		return $rows;
	}

	/**
	 * `m_puskesmas.kode_pkm` bertipe varchar dan sebagian baris warisan memuat
	 * spasi; kelas ini memperlakukan kode sebagai string yang sudah dipangkas.
	 *
	 * @param mixed $code
	 * @return string
	 */
	public function normalize($code)
	{
		return is_scalar($code) ? trim((string) $code) : '';
	}
}
