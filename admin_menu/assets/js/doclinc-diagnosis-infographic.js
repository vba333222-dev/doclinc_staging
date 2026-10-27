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

	/* Warna batang menunjukkan POSISI dalam peringkat (tangga yang sama dengan
	 * peta, --dl-ref-seq-1..5), bukan besar nilainya: sumbu grafik ini adalah
	 * peringkat, dan pada data nyata banyak kode berimpit di nilai yang hampir
	 * sama sehingga warna berbasis nilai membuat lima batang teratas tampak
	 * identik. Angka di ujung batang tetap nilai aslinya, jadi tidak ada
	 * pembaca yang menyimpulkan selisih yang tidak ada. Nilai yang sama selalu
	 * mendapat warna yang sama: dua kode dengan jumlah identik tidak boleh
	 * dibedakan warnanya. */
	function barColors(values) {
		var unique = [];
		values.forEach(function (value) {
			var number = Number(value) || 0;
			if (unique.indexOf(number) === -1) {
				unique.push(number);
			}
		});
		unique.sort(function (a, b) {
			return b - a;
		});
		return values.map(function (value) {
			var number = Number(value) || 0;
			if (unique.length <= 1) {
				return token('--dl-ref-seq-5');
			}
			var position = unique.indexOf(number);
			var step = Math.round((position / (unique.length - 1)) * 4);
			var level = Math.max(2, Math.min(5, 5 - step));
			return token('--dl-ref-seq-' + level);
		});
	}

	/* Angka di ujung batang. Tanpa ini, tinggi batang hanya bisa dibaca dengan
	 * hover atau dengan menurunkan mata ke tabel - dan grafik yang memaksa
	 * pembacaannya lewat tooltip bukan grafik yang terbaca. */
	function valueLabelsPlugin(inkColor, fontSize, fontFamily) {
		return {
			id: 'doclincBarValueLabels',
			afterDatasetsDraw: function (chart) {
				var meta = chart.getDatasetMeta(0);
				if (!meta || !meta.data || !meta.data.length) {
					return;
				}
				var ctx = chart.ctx;
				ctx.save();
				ctx.fillStyle = inkColor || '#1f2a24';
				ctx.font = '600 ' + fontSize + 'px ' + fontFamily;
				ctx.textAlign = 'left';
				ctx.textBaseline = 'middle';
				meta.data.forEach(function (bar, index) {
					var value = Number(config.values[index]) || 0;
					ctx.fillText(value.toLocaleString('id-ID'), bar.x + 6, bar.y);
				});
				ctx.restore();
			}
		};
	}

	/* Grafik yang tidak dapat digambar tidak boleh menyisakan kotak kosong tanpa
	 * penjelasan: kanvas disembunyikan dan satu kalimat menyebut di mana angka
	 * yang sama bisa dibaca. Pustakanya disajikan dari origin ini
	 * (assets/vendor/chartjs), jadi keadaan ini hanya muncul bila berkas vendor
	 * hilang atau diblokir. */
	function markChartUnavailable(canvas) {
		var wrap = canvas.parentNode;
		canvas.hidden = true;
		if (!wrap || wrap.querySelector('.doclinc-analytics-chart-note')) {
			return;
		}
		if (wrap.getAttribute && wrap.classList && wrap.classList.contains('doclinc-analytics-chart')) {
			wrap.setAttribute('data-chart-state', 'unavailable');
		}
		var note = document.createElement('p');
		note.className = 'doclinc-analytics-chart-note';
		note.textContent = 'Grafik tidak dapat digambar karena pustaka grafik belum termuat. Angka yang sama ada di tabel di bawah.';
		wrap.appendChild(note);
	}

	function drawChart() {
		var canvas = document.getElementById(config.canvasId);
		if (!canvas) {
			return null;
		}
		if (typeof window.Chart !== 'function') {
			markChartUnavailable(canvas);
			return null;
		}
		if (!Array.isArray(config.labels) || config.labels.length === 0) {
			return null;
		}
		if (canvas.dataset.doclincChartReady === '1') {
			return null;
		}
		canvas.dataset.doclincChartReady = '1';

		var gridColor = token('--dl-ref-line');
		var inkColor = token('--dl-ref-ink');
		var labelColor = token('--dl-ref-muted');
		var fontFamily = token('--dl-ref-font') || 'sans-serif';
		var totalCoded = Number(config.totalCoded) || 0;

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
					backgroundColor: barColors(config.values),
					hoverBackgroundColor: barColors(config.values),
					borderWidth: 0,
					borderRadius: 4,
					/* Batang tipis lebih mudah dibandingkan daripada balok tebal
					 * yang memenuhi tinggi baris; 26px masih nyaman disentuh. */
					maxBarThickness: 26,
					categoryPercentage: 0.82,
					barPercentage: 0.9
				}]
			},
			plugins: [valueLabelsPlugin(inkColor, narrow ? 10 : 11, fontFamily)],
			options: {
				indexAxis: 'y',
				responsive: true,
				maintainAspectRatio: false,
				animation: reducedMotion() ? false : { duration: 320 },
				/* Ruang kanan untuk angka di ujung batang; tanpa ini angka
				 * terbesar tergambar di luar area grafik. */
				layout: { padding: { right: narrow ? 34 : 48, left: 0, top: 0, bottom: 0 } },
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
								var value = Number(context.parsed.x) || 0;
								var share = totalCoded > 0 ? Math.round((value / totalCoded) * 100) : 0;
								return value.toLocaleString('id-ID') + ' diagnosis tercatat'
									+ (totalCoded > 0 ? ' (' + share + '% dari diagnosis yang sudah dikode)' : '');
							}
						}
					}
				},
				scales: {
					x: {
						beginAtZero: true,
						ticks: {
							precision: 0,
							color: labelColor || undefined,
							font: { size: narrow ? 10 : 11 }
						},
						grid: { color: gridColor || undefined }
					},
					y: {
						ticks: {
							color: inkColor || undefined,
							/* Semua kode harus tampil: grafik ini tentang
							 * peringkat, dan peringkat yang barisnya
							 * dilewati tidak bisa dibaca. */
							autoSkip: false,
							font: { size: narrow ? 10 : 11, weight: '600' }
						},
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
