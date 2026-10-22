/* DokLinc - penangan global "sesi berakhir".
 *
 * Kenapa ada: ketika sesi dicabut (mis. akun masuk di perangkat lain), endpoint
 * AJAX menjawab 401 {safe_error_code: 'session_expired'}. Tanpa penangan ini
 * halaman tetap terlihat seperti masih masuk padahal tidak ada yang terkirim -
 * berbahaya untuk chat dan tindakan klinis. Di sini 401 itu menjadi pesan yang
 * jelas, lalu pengguna diarahkan ke halaman login beserta sebabnya.
 *
 * Aman saat fitur sesi-tunggal mati: tidak ada respons berkode itu, jadi tidak
 * ada yang terjadi. Tidak mengubah status HTTP maupun isi respons apa pun.
 */
(function (root, factory) {
	if (typeof module === 'object' && module.exports) {
		/* Node: kembalikan pabriknya supaya uji dapat menyuntik root tiruan. */
		module.exports = factory;
		return;
	}
	root.DoclincSessionGuard = factory(root);
})(typeof window !== 'undefined' ? window : this, function (root) {
	'use strict';

	var HANDLING_TIMEOUT_MS = 8000;
	var handling = false;
	var config = null;

	function readConfig() {
		if (config !== null) {
			return config;
		}
		config = {};
		var node = root.document && root.document.getElementById('doclincSessionGuardConfig');
		if (node && node.textContent) {
			try {
				config = JSON.parse(node.textContent) || {};
			} catch (error) {
				config = {};
			}
		}
		return config;
	}

	function loginUrl(reason) {
		var base = String(readConfig().login_url || '/login');
		var separator = base.indexOf('?') === -1 ? '?' : '&';
		return base + separator + 'session=expired&reason=' + encodeURIComponent(reason);
	}

	function messageFor(reason) {
		if (reason === 'other_device') {
			return {
				title: 'Sesi diakhiri',
				text: 'Akun Anda masuk di perangkat lain, jadi sesi ini diakhiri. Jika bukan Anda, segera ganti kata sandi.'
			};
		}
		if (reason === 'signed_out') {
			return { title: 'Sesi diakhiri', text: 'Sesi Anda sudah diakhiri. Masuk lagi untuk melanjutkan.' };
		}
		return { title: 'Sesi berakhir', text: 'Sesi Anda berakhir. Masuk lagi untuk melanjutkan.' };
	}

	function goToLogin(reason) {
		root.location.assign(loginUrl(reason));
	}

	/**
	 * Hentikan kanal realtime dan polling sebelum halaman dialihkan.
	 *
	 * Server sudah menghancurkan sesi, tetapi koneksi WebSocket yang sudah
	 * terbuka tidak ikut tertutup dengan sendirinya: perangkat yang dicabut
	 * masih bisa menerima pesan sampai tokennya kedaluwarsa. Menutupnya di sini
	 * memutus pengiriman seketika. Pencabutan kanal dari sisi server tetap
	 * keputusan terpisah.
	 */
	function stopRealtime() {
		['DoclincNotificationRuntime', 'DoclincRequestRuntime', 'DoclincRealtimeSharedClient'].forEach(function (name) {
			var target = root[name];
			if (target && typeof target.teardown === 'function') {
				try {
					target.teardown();
				} catch (error) {
					/* Kanal yang gagal ditutup tidak boleh menghalangi pengalihan. */
				}
			}
		});
	}

	function handle(payload) {
		if (handling || !payload || payload.safe_error_code !== 'session_expired') {
			return;
		}
		handling = true;
		var reason = typeof payload.reason === 'string' && payload.reason !== '' ? payload.reason : 'expired';
		stopRealtime();
		var copy = messageFor(reason);
		if (typeof root.Swal === 'function') {
			root.Swal.fire({
				icon: 'warning',
				title: copy.title,
				text: copy.text,
				confirmButtonText: 'Masuk lagi',
				allowOutsideClick: false,
				allowEscapeKey: false
			}).then(function () { goToLogin(reason); });
			/* Jangan menggantung bila modal tidak dijawab (mis. tab tidak aktif). */
			root.setTimeout(function () { goToLogin(reason); }, HANDLING_TIMEOUT_MS);
			return;
		}
		if (typeof root.alert === 'function') {
			root.alert(copy.title + '. ' + copy.text);
		}
		goToLogin(reason);
	}

	function payloadFromText(text) {
		try {
			return JSON.parse(text);
		} catch (error) {
			return null;
		}
	}

	/* jQuery dipakai seluruh halaman; ini menangkap semua $.ajax/$.post/$.get.
	   Semua akses lewat root supaya berkas ini bisa diuji tanpa peramban. */
	if (root.jQuery && typeof root.jQuery(root.document).ajaxError === 'function') {
		root.jQuery(root.document).ajaxError(function (event, xhr) {
			if (xhr && xhr.status === 401) {
				handle(payloadFromText(xhr.responseText));
			}
		});
	}

	/* fetch() tidak lewat jQuery; respons dibaca dari salinan agar isi aslinya
	   tetap utuh untuk pemanggil. */
	if (typeof root.fetch === 'function') {
		var originalFetch = root.fetch;
		root.fetch = function () {
			return originalFetch.apply(this, arguments).then(function (response) {
				if (response && response.status === 401 && typeof response.clone === 'function') {
					response.clone().text().then(function (text) {
						handle(payloadFromText(text));
					}).catch(function () {});
				}
				return response;
			});
		};
	}

	return { handle: handle, loginUrl: loginUrl, stopRealtime: stopRealtime };
});
