/**
 * Infografis penyakit terbanyak: grafik batang kode ICD-10 + drill-down per
 * kecamatan. Memakai Chart.js 3.6.2 yang sudah dimuat header panel admin.
 *
 * Aturan yang dipegang:
 *   - Grafik adalah pelengkap, bukan satu-satunya sumber angka. Tabel di
 *     bawahnya memuat data yang sama dan tetap terbaca tanpa JavaScript.
 *   - Warna diambil dari token CSS (--dl-ref-*), tidak ada hex di berkas ini.
 *   - prefers-reduced-motion dihormati: animasi grafik dimatikan.
 *   - Routing panel admin memakai query-string (index.php?/...), jadi parameter
 *     tambahan disambung dengan '&' bila URL sudah mengandung '?'.
 */
(function () {
	'use strict';

	var bootstrapElement = document.getElementById('doclincDiagnosisBootstrap');
	if (!bootstrapElement) {
		return;
	}

	var config;
	try {
		config = JSON.parse(bootstrapElement.textContent || '{}');
	} catch (error) {
		return;
	}

	function token(name) {
		var value = window.getComputedStyle(document.documentElement).getPropertyValue(name);
		return (value || '').trim();
	}

	function reducedMotion() {
		return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	}

	function drillUrl(code) {
		/* Panel ini memakai segmen URL, bukan query string: `index_page` bernilai
		 * 'index.php?' sehingga query string ikut menjadi URI dan '='/'&' ditolak
		 * filter URI CodeIgniter (permintaan dijawab 400). */
		return config.drillUrl + '/' + encodeURIComponent(code) + '/' + (config.filterSegments || '30/0/');
	}

	function drawChart() {
		var canvas = document.getElementById(config.canvasId);
		if (!canvas || typeof window.Chart !== 'function') {
			return null;
		}
		if (!Array.isArray(config.labels) || config.labels.length === 0) {
			return null;
		}
		if (canvas.dataset.doclincChartReady === '1') {
			return null;
		}
		canvas.dataset.doclincChartReady = '1';

		var barColor = token('--dl-ref-green-600');
		var gridColor = token('--dl-ref-line');
		var inkColor = token('--dl-ref-ink');
		var labelColor = token('--dl-ref-muted');

		/* Pada layar sempit label panjang terpotong di sumbu. Kode saja tetap
		 * terbaca; label lengkap tetap ada di tooltip dan di tabel bawah. */
		var narrow = window.matchMedia && window.matchMedia('(max-width: 575.98px)').matches;
		var axisLabels = (narrow && Array.isArray(config.codes) && config.codes.length === config.labels.length)
			? config.codes
			: config.labels;

		return new window.Chart(canvas.getContext('2d'), {
			type: 'bar',
			data: {
				labels: axisLabels,
				datasets: [{
					label: 'Diagnosis tercatat',
					data: config.values,
					backgroundColor: barColor || undefined,
					borderWidth: 0,
					borderRadius: 4
				}]
			},
			options: {
				indexAxis: 'y',
				responsive: true,
				maintainAspectRatio: false,
				animation: reducedMotion() ? false : { duration: 320 },
				plugins: {
					legend: { display: false },
					tooltip: {
						callbacks: {
							title: function (items) {
								if (!items.length || !Array.isArray(config.labels)) {
									return '';
								}
								return config.labels[items[0].dataIndex] || '';
							},
							label: function (context) {
								return context.parsed.x + ' diagnosis tercatat';
							}
						}
					}
				},
				scales: {
					x: {
						beginAtZero: true,
						ticks: { precision: 0, color: labelColor || undefined },
						grid: { color: gridColor || undefined }
					},
					y: {
						ticks: { color: inkColor || undefined },
						grid: { display: false }
					}
				}
			}
		});
	}

	drawChart();

	/* Drill-down: satu kode ICD-10 -> sebarannya per kecamatan. */
	var drillPanel = document.getElementById('doclincDiagnosisDrill');
	var drillTitle = document.getElementById('doclincDiagnosisDrillTitle');
	var drillStatus = document.getElementById('doclincDiagnosisDrillStatus');
	var drillBody = document.getElementById('doclincDiagnosisDrillBody');
	var drillButtons = document.querySelectorAll('[data-doclinc-drill]');

	function setStatus(message) {
		if (drillStatus) {
			drillStatus.textContent = message;
		}
	}

	function renderRows(code, rows) {
		if (!drillBody) {
			return;
		}
		drillBody.textContent = '';
		if (!Array.isArray(rows) || rows.length === 0) {
			setStatus('Kode ' + code + ' belum punya sebaran wilayah pada periode ini (barisnya belum terpetakan ke kecamatan).');
			return;
		}
		setStatus('Sebaran kode ' + code + ' pada periode terpilih.');

		var table = document.createElement('table');
		table.className = 'doclinc-analytics-table';
		var caption = document.createElement('caption');
		caption.textContent = 'Jumlah baris kode ' + code + ' per kecamatan.';
		table.appendChild(caption);

		var thead = document.createElement('thead');
		var headRow = document.createElement('tr');
		['Kecamatan', 'Diagnosis', 'Rekam medis'].forEach(function (label, index) {
			var th = document.createElement('th');
			th.scope = 'col';
			if (index > 0) {
				th.className = 'doclinc-analytics-number';
			}
			th.textContent = label;
			headRow.appendChild(th);
		});
		thead.appendChild(headRow);
		table.appendChild(thead);

		var tbody = document.createElement('tbody');
		rows.forEach(function (row) {
			var tr = document.createElement('tr');
			var nameCell = document.createElement('td');
			nameCell.textContent = row.kecamatan;
			tr.appendChild(nameCell);
			['entries', 'records'].forEach(function (key) {
				var cell = document.createElement('td');
				cell.className = 'doclinc-analytics-number';
				cell.textContent = Number(row[key] || 0).toLocaleString('id-ID');
				tr.appendChild(cell);
			});
			tbody.appendChild(tr);
		});
		table.appendChild(tbody);
		drillBody.appendChild(table);
	}

	function loadDrill(button) {
		var code = button.getAttribute('data-doclinc-drill');
		if (!code || !drillPanel) {
			return;
		}
		if (drillTitle) {
			drillTitle.textContent = 'Sebaran kode ' + code + ' per kecamatan';
		}
		drillPanel.hidden = false;
		setStatus('Memuat sebaran kode ' + code + '...');
		drillButtons.forEach(function (other) {
			other.setAttribute('aria-expanded', other === button ? 'true' : 'false');
		});

		window.fetch(drillUrl(code), {
			method: 'GET',
			credentials: 'same-origin',
			headers: { 'X-Requested-With': 'XMLHttpRequest' }
		}).then(function (response) {
			return response.json();
		}).then(function (body) {
			if (!body || body.success !== true || !body.data) {
				setStatus('Sebaran kode ' + code + ' tidak bisa dimuat (' + ((body && body.safe_error_code) || 'unknown') + ').');
				return;
			}
			renderRows(code, body.data.rows);
		}).catch(function () {
			setStatus('Sebaran kode ' + code + ' gagal dimuat. Periksa koneksi lalu coba lagi.');
		});
	}

	drillButtons.forEach(function (button) {
		button.addEventListener('click', function () {
			loadDrill(button);
		});
	});

	/* Tema panel bisa berubah; grafik digambar ulang supaya warnanya ikut token baru. */
	var themeToggle = document.getElementById('doclincThemeToggle');
	if (themeToggle) {
		themeToggle.addEventListener('click', function () {
			var canvas = document.getElementById(config.canvasId);
			if (!canvas || typeof window.Chart !== 'function') {
				return;
			}
			var existing = window.Chart.getChart(canvas);
			if (existing) {
				existing.destroy();
			}
			canvas.dataset.doclincChartReady = '0';
			window.setTimeout(drawChart, 0);
		});
	}
})();
