/**
 * Arsip rekam medis: membuka rincian satu rekam tanpa memuat ulang halaman.
 *
 * Tabel arsip hanya memuat ringkasan layanan. Isi klinis (anamnesis,
 * diagnosis, terapi, anjuran) baru diminta setelah tombol "Buka isi klinis"
 * ditekan, dan permintaan itu melewati gerbang akses yang sudah ada di server:
 * 428 bila alasan akses belum ada di sesi ini, 403 bila akun tidak berhak,
 * 200 bila boleh (sekaligus dicatat di penampil audit). Skrip ini tidak
 * memutuskan boleh atau tidak; ia hanya menyampaikan jawaban server apa adanya.
 */
(function () {
	'use strict';

	var modal = document.getElementById('doclincRecordModal');
	if (!modal || !window.jQuery) {
		return;
	}

	var $ = window.jQuery;
	var $modal = $(modal);
	var endpoint = modal.getAttribute('data-endpoint') || '';
	var meta = document.getElementById('doclincRecordMeta');
	var clinical = document.getElementById('doclincRecordClinical');
	var gate = document.getElementById('doclincRecordGate');
	var openButton = document.getElementById('doclincRecordOpen');
	var reasonWrap = document.getElementById('doclincRecordReasonWrap');
	var reasonSelect = document.getElementById('doclincRecordReason');
	var reasonText = document.getElementById('doclincRecordReasonText');
	var note = document.getElementById('doclincRecordNote');
	var csrfName = (document.querySelector('meta[name="csrf-token-name"]') || {}).content || '';
	var csrfHash = (document.querySelector('meta[name="csrf-token-hash"]') || {}).content || '';
	var current = null;
	var lastButton = null;
	var busy = false;

	function cell(label, value) {
		var box = document.createElement('div');
		var term = document.createElement('span');
		var detail = document.createElement('strong');
		term.textContent = label;
		detail.textContent = value || '-';
		box.appendChild(term);
		box.appendChild(detail);
		return box;
	}

	function fill(container, rows) {
		container.textContent = '';
		rows.forEach(function (row) {
			container.appendChild(cell(row[0], row[1]));
		});
	}

	function setNote(message) {
		note.textContent = message || '';
	}

	function describe(payload) {
		return [
			['Nomor permintaan', '#' + payload.requestId],
			['Tanggal layanan', payload.date],
			['Puskesmas', payload.puskesmas + (payload.code ? ' (' + payload.code + ')' : '')],
			['Dokter penanggung jawab', payload.doctor || 'Belum tercatat'],
			['Dicatat oleh', payload.recordedBy || 'Belum tercatat'],
			['Jenis layanan', payload.service],
			['Status permintaan', payload.status]
		];
	}

	function statement(data) {
		return [
			['Anamnesis', data.anamnesis],
			['Diagnosis', data.diagnosis],
			['Terapi', data.treatment],
			['Anjuran', data.recommendations]
		];
	}

	function payloadOf(button) {
		try {
			return JSON.parse(button.getAttribute('data-record') || '{}');
		} catch (error) {
			return null;
		}
	}

	function showReason() {
		reasonWrap.hidden = false;
		gate.hidden = false;
		openButton.hidden = false;
		openButton.textContent = 'Buka dengan alasan ini';
		setNote('Pilih alasan akses. Alasan dan waktu akses dicatat di penampil audit.');
		reasonSelect.focus();
	}

	function showClinical(data) {
		fill(clinical, statement(data));
		clinical.hidden = false;
		reasonWrap.hidden = true;
		gate.hidden = true;
		setNote('Isi klinis dibuka dan tercatat di penampil audit.');
		// Fokus dipindahkan ke dialog supaya Escape dan Tab tetap bekerja
		// setelah tombol pemicunya ikut tersembunyi.
		modal.focus();
	}

	function request(reason) {
		var body = { record_id: current.recordId };
		if (reason) {
			body.reason_code = reason.code;
			body.reason_text = reason.text || '';
		}
		if (csrfName !== '') {
			body[csrfName] = csrfHash;
		}

		return $.ajax({ url: endpoint, type: 'POST', data: body, dataType: 'json' });
	}

	function ask() {
		if (!current || busy) {
			return;
		}

		var reason = null;
		if (!reasonWrap.hidden) {
			if (reasonSelect.value === '') {
				setNote('Pilih alasan akses terlebih dahulu.');
				reasonSelect.focus();
				return;
			}
			reason = { code: reasonSelect.value, text: reasonText.value };
		}

		busy = true;
		openButton.setAttribute('aria-busy', 'true');
		request(reason).done(function (response) {
			if (response && response.success && response.data) {
				showClinical(response.data);
				return;
			}
			setNote((response && response.message) || 'Rekam medis tidak bisa dibuka.');
		}).fail(function (xhr) {
			var body = xhr.responseJSON || {};
			if (xhr.status === 428) {
				showReason();
				return;
			}
			setNote(body.message || 'Rincian rekam medis tidak bisa dibuka.');
			if (xhr.status === 403 || xhr.status === 404) {
				gate.hidden = true;
			}
		}).always(function () {
			busy = false;
			openButton.removeAttribute('aria-busy');
		});
	}

	function openRecord(button) {
		var payload = payloadOf(button);
		if (!payload || !payload.recordId) {
			return;
		}

		current = payload;
		lastButton = button;
		fill(meta, describe(payload));
		clinical.textContent = '';
		clinical.hidden = true;
		gate.hidden = false;
		openButton.hidden = false;
		openButton.textContent = 'Buka isi klinis';
		reasonWrap.hidden = true;
		reasonText.hidden = true;
		reasonText.value = '';
		reasonSelect.value = '';
		setNote('');
		$modal.modal('show');
	}

	document.addEventListener('click', function (event) {
		var button = event.target.closest ? event.target.closest('.doclinc-record-open') : null;
		if (button) {
			openRecord(button);
		}
	});

	openButton.addEventListener('click', ask);

	reasonSelect.addEventListener('change', function () {
		reasonText.hidden = reasonSelect.value !== 'other';
		if (!reasonText.hidden) {
			reasonText.focus();
		}
	});

	$modal.on('hidden.bs.modal', function () {
		if (lastButton) {
			lastButton.focus();
		}
	});

	// Bootstrap hanya menutup lewat Escape bila fokus berada di dalam dialog.
	// Pendengar ini menjaga Escape tetap bekerja ketika fokus sempat berpindah
	// ke luar modal setelah isi klinis tampil.
	document.addEventListener('keydown', function (event) {
		if (event.key === 'Escape' && modal.classList.contains('show')) {
			$modal.modal('hide');
		}
	});
}());
