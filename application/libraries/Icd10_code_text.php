<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Memisahkan kode ICD-10 dari teks diagnosis yang diketik manusia.
 *
 * Kenapa kelas ini ada: pemilih ICD-10 mengirim teks berbentuk
 * "KODE — label", tetapi tenaga kesehatan juga mengetik sendiri. Bentuk yang
 * benar-benar muncul di lapangan (dan yang dulu gagal dikenali sehingga
 * diagnosisnya jatuh ke keranjang "belum dikode"):
 *
 *   "J06.9 — Acute upper respiratory infection, unspecified"  (dari pemilih)
 *   "Dermatitis atopik (L20.9)"                                (salin dari daftar)
 *   "L20.9 Dermatitis atopik"   "Dermatitis atopik - L20.9"    (ketik tangan)
 *   "ICD-10: L20.9"                                            (catatan bebas)
 *   "ISPA"                                                     (tanpa kode - memang tidak bisa ditebak)
 *
 * Kelas ini SENGAJA hanya memisahkan kode dari label; ia tidak memutuskan kode
 * mana yang sah. Keabsahan tetap diperiksa pemanggil terhadap master ICD-10.
 * Pemisahan itu yang mencegah teks seperti "Vitamin B12" berubah menjadi
 * diagnosis "B12": B12 memang cocok pola, tetapi tidak ada di master.
 *
 * Kode hanya dikenali dalam huruf besar. Itu bukan kelalaian: dengan
 * pencocokan tanpa peduli huruf besar/kecil, "l20.9" (huruf L kecil) akan
 * menjadi "L20.9", padahal "l10" dan "I10" adalah dua diagnosis berbeda.
 * Teks berhuruf kecil dibiarkan belum dikode - lebih baik menunggu dipilih
 * manual daripada menebak salah.
 */
class Icd10_code_text
{
	/** Bentuk kode pada master `20b_master_diagnosis_icd10_who_2019.json`. */
	const CODE_PATTERN = '/\A[A-Z][0-9]{2}(?:\.[0-9A-Z]{1,4})?\z/';

	/** Kode ICD-10 boleh diberi penanda dagger/asterisk († / *); penanda dibuang. */
	const MARKERS = array("\xE2\x80\xA0", "\xE2\x80\xA1", '*');

	const SOURCE_NONE = 'none';
	const SOURCE_CODE_ONLY = 'code_only';
	const SOURCE_PREFIX = 'prefix';
	const SOURCE_BRACKET = 'bracket';
	const SOURCE_SUFFIX = 'suffix';

	/**
	 * Hasil pemisahan. `code` NULL berarti teks tidak memuat kode yang bisa
	 * dipakai; `label` selalu berisi teks tanpa kode (atau teks aslinya).
	 */
	public static function extract($text)
	{
		$text = self::clean($text);
		$none = array('code' => null, 'label' => $text, 'source' => self::SOURCE_NONE);
		if ($text === '') {
			return $none;
		}

		$token = '[A-Z][0-9]{2}(?:\.[0-9A-Z]{1,4})?';

		/* 1. Kode saja: "L20.9". */
		if (preg_match('/\A(' . $token . ')\z/u', $text, $match) === 1) {
			return self::result($match[1], '', self::SOURCE_CODE_ONLY);
		}

		/* 2. Kode di depan: "L20.9 Dermatitis atopik", "L20.9 — Dermatitis atopik",
		 *    "ICD-10: L20.9 Dermatitis atopik" (awalan dibuang oleh clean()). */
		if (preg_match('/\A(' . $token . ')(?:\s*[\x{2014}\x{2013}\-:]\s*|\s+)(.+)\z/u', $text, $match) === 1) {
			return self::result($match[1], $match[2], self::SOURCE_PREFIX);
		}

		/* 3. Kode di ujung dalam kurung: "Dermatitis atopik (L20.9)". Kurung
		 *    terakhir yang menentukan, jadi "Anemia (defisiensi besi) (D50.9)"
		 *    tetap terbaca D50.9 dengan label "Anemia (defisiensi besi)". */
		if (preg_match('/\A(.+?)\s*[\(\[]\s*(' . $token . ')\s*[\)\]]\s*[.;,]?\z/u', $text, $match) === 1) {
			return self::result($match[2], $match[1], self::SOURCE_BRACKET);
		}

		/* 4. Kode di ujung tanpa kurung: "Dermatitis atopik - L20.9",
		 *    "Dermatitis atopik L20.9". Hanya diterima bila masih ada label di
		 *    depannya - kalau tidak, ini bukan teks diagnosis. */
		if (preg_match('/\A(.+?)\s*(?:[\x{2014}\x{2013}\-:]\s*|\s+)(' . $token . ')\z/u', $text, $match) === 1) {
			return self::result($match[2], $match[1], self::SOURCE_SUFFIX);
		}

		return $none;
	}

	/** Label tanpa kode, untuk ditampilkan ("Dermatitis atopik (L20.9)" -> "Dermatitis atopik"). */
	public static function labelWithoutCode($text)
	{
		$parsed = self::extract($text);
		return $parsed['label'] !== '' ? $parsed['label'] : self::clean($text);
	}

	/** Kode saja tanpa label, atau NULL. */
	public static function code($text)
	{
		$parsed = self::extract($text);
		return $parsed['code'];
	}

	/** Apakah nilai ini berbentuk kode ICD-10 yang sah secara pola? */
	public static function isCode($value)
	{
		return preg_match(self::CODE_PATTERN, self::stripMarkers(self::collapse((string) $value))) === 1;
	}

	/**
	 * Rapikan teks sebelum dipisah: buang karakter kendali, samakan tanda
	 * pisah, ringkas spasi, dan buang awalan "ICD-10:"/"DX:" yang sering
	 * diketik orang. Awalan dibuang hanya bila diikuti kode, supaya
	 * "ICD-10" sendirian tidak berubah menjadi label kosong.
	 */
	public static function clean($text)
	{
		$text = self::stripMarkers((string) $text);
		$text = (string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $text);
		$text = (string) preg_replace('/[\x{2012}\x{2013}\x{2014}\x{2212}]/u', '-', $text);
		$text = self::collapse($text);
		$text = (string) preg_replace('/\A(?:ICD[\s\-]?10|DX|DIAGNOSIS)\s*[:\-]\s*(?=[A-Z][0-9]{2})/u', '', $text);
		$text = (string) preg_replace('/\A[-\s]+|[-\s]+$/u', '', $text);
		return trim($text);
	}

	private static function collapse($text)
	{
		$text = (string) preg_replace('/\s+/u', ' ', (string) $text);
		return trim($text);
	}

	private static function stripMarkers($text)
	{
		foreach (self::MARKERS as $marker) {
			$text = str_replace($marker, '', $text);
		}
		return $text;
	}

	private static function result($code, $label, $source)
	{
		$code = strtoupper(self::stripMarkers(self::collapse($code)));
		$label = trim((string) preg_replace('/\A[-\s]+|[-\s]+$/u', '', self::collapse($label)));
		if (preg_match(self::CODE_PATTERN, $code) !== 1) {
			return array('code' => null, 'label' => $label === '' ? $code : $label, 'source' => self::SOURCE_NONE);
		}
		return array('code' => $code, 'label' => $label, 'source' => $source);
	}
}
