/**
 * Peta sebaran penyakit per kecamatan Kota Cilegon.
 *
 * Cara kerjanya: bingkai peta digambar dari GeoJSON batas kecamatan yang ada di
 * repo (assets/geojson/kecamatan-cilegon.geojson), lalu tiap kecamatan diberi
 * warna menurut salah satu dari dua pertanyaan:
 *
 *   1. "Jumlah per kode" - enam kelas tetap dari jumlah diagnosis terkode untuk
 *      kode ICD-10 yang sedang dipilih (tangga --dl-ref-seq-*).
 *   2. "Penyakit terbanyak" - warna nominal (--dl-ref-cat-*) yang menunjuk kode
 *      yang paling banyak di kecamatan itu. Hue-nya mengikuti peringkat kode
 *      kota, jadi hue yang sama tidak pernah berarti dua kode berbeda.
 *
 * Kecamatan bisa DIKLIK (atau difokuskan lalu Enter): warnanya berubah menjadi
 * warna "dipilih" dan sebuah KARTU MENGAMBANG di atas peta menampilkan
 * infografik kecamatan itu - empat angka (terkode, jenis penyakit, penyakit
 * dominan, belum dikode), pita porsi kode, lalu 10 kode ICD-10 terbanyak.
 * Tidak ada gelembung angka di atas peta dan tidak ada daftar kecamatan di
 * sampingnya: satu-satunya jalan membuka angka kecamatan adalah mengklik
 * kecamatannya, dan jawabannya muncul di tempat yang sama.
 *
 * Aturan yang dipegang, sama seperti grafik batang di halaman ini:
 *   - Angka diambil dari bootstrap JSON yang disiapkan server. Berkas ini tidak
 *     menghitung ulang dan tidak mengarang angka; kecamatan tanpa data bernilai 0.
 *   - Angka yang sama tersedia sebagai tabel di dalam <details>, jadi halaman
 *     tetap terbaca tanpa JavaScript dan tanpa peta.
 *   - Warna diambil dari token CSS (--dl-ref-*, --dl-map-*), tidak ada hex di sini.
 *   - prefers-reduced-motion dihormati: tanpa autoplay dan tanpa transisi.
 *   - Peta tidak memuat tile dari internet. Panel ini dipakai di jaringan
 *     Puskesmas yang tidak selalu punya akses keluar, dan latar kosong membuat
 *     warna sebaran lebih mudah dibandingkan.
 */
(function () {
	'use strict';

	var host = document.getElementById('doclincDiseaseMap');
	if (!host || typeof window.L === 'undefined') {
		return;
	}

	var bootstrapElement = document.getElementById('doclincDiseaseMapBootstrap');
	if (!bootstrapElement) {
		return;
	}

	var config;
	try {
		config = JSON.parse(bootstrapElement.textContent || '{}');
	} catch (error) {
		return;
	}

	var codes = Array.isArray(config.codes) ? config.codes : [];
	var rows = Array.isArray(config.kecamatan) ? config.kecamatan : [];
	if (rows.length === 0) {
		return;
	}

	var select = document.getElementById('doclincMapCode');
	var modeSelect = document.getElementById('doclincMapMode');
	var playButton = document.getElementById('doclincMapPlay');
	var status = document.getElementById('doclincMapStatus');
	var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	var stepMs = Number(config.stepMs) > 0 ? Number(config.stepMs) : 1700;

	function token(name, fallback) {
		var value = window.getComputedStyle(document.documentElement).getPropertyValue(name);
		var trimmed = (value || '').trim();
		return trimmed === '' ? fallback : trimmed;
	}

	var fillToken = token('--dl-ref-green-600', '#2f7d5d');
	var deepToken = token('--dl-ref-green-900', '#123f2f');
	var selectToken = token('--dl-ref-select', '#2e6685');
	/* Abu netral untuk batas "tanpa data" di mode penyakit terbanyak. */
	var mutedToken = token('--dl-ref-muted', '#55665e');

	/* Enam langkah tangga nilai, dibaca dari token. Nilainya konkret - bukan
	 * color-mix() - supaya bisa dipasang sebagai atribut `fill` SVG tanpa
	 * bergantung pada dukungan browser terhadap fungsi warna baru di atribut
	 * presentasi. */
	var fillTokens = [
		token('--dl-ref-seq-0', '#eaf2ee'),
		token('--dl-ref-seq-1', '#c3e1d2'),
		token('--dl-ref-seq-2', '#91c7ad'),
		token('--dl-ref-seq-3', '#5b9a7b'),
		token('--dl-ref-seq-4', '#2d7d56'),
		token('--dl-ref-seq-5', '#12472f')
	];

	/* Warna nominal untuk kode penyakit (mode "Penyakit terbanyak"). Urutannya
	 * mengikuti peringkat kode kota, bukan urutan abjad: kode peringkat 1 selalu
	 * mendapat hue pertama pada setiap periode, jadi legenda tetap bisa dibaca
	 * saat angkanya berubah. Enam hue tanpa biru - biru adalah warna "dipilih",
	 * dan seleksi tidak boleh terbaca sebagai salah satu kelas. */
	var catTokens = [
		token('--dl-ref-cat-0', '#1f6b4c'),
		token('--dl-ref-cat-1', '#6d7a1d'),
		token('--dl-ref-cat-2', '#a9761b'),
		token('--dl-ref-cat-3', '#9e4526'),
		token('--dl-ref-cat-4', '#8a3560'),
		token('--dl-ref-cat-5', '#2b6f6c')
	];

	/* Token dibaca sekali saat panel digambar. Tema panel bisa berganti tanpa
	 * memuat ulang halaman, jadi nilainya dibaca ulang saat atribut `data-theme`
	 * berubah - kalau tidak, peta tetap memakai warna tema sebelumnya. */
	function refreshTokens() {
		fillToken = token('--dl-ref-green-600', fillToken);
		deepToken = token('--dl-ref-green-900', deepToken);
		selectToken = token('--dl-ref-select', selectToken);
		mutedToken = token('--dl-ref-muted', mutedToken);
		fillTokens = [
			token('--dl-ref-seq-0', fillTokens[0]),
			token('--dl-ref-seq-1', fillTokens[1]),
			token('--dl-ref-seq-2', fillTokens[2]),
			token('--dl-ref-seq-3', fillTokens[3]),
			token('--dl-ref-seq-4', fillTokens[4]),
			token('--dl-ref-seq-5', fillTokens[5])
		];
		catTokens = [
			token('--dl-ref-cat-0', catTokens[0]),
			token('--dl-ref-cat-1', catTokens[1]),
			token('--dl-ref-cat-2', catTokens[2]),
			token('--dl-ref-cat-3', catTokens[3]),
			token('--dl-ref-cat-4', catTokens[4]),
			token('--dl-ref-cat-5', catTokens[5])
		];
		if (!state) {
			return;
		}
		applyFrame(state.code);
		/* Hue nominal juga dipakai di kartu infografik, jadi kartu digambar
		 * ulang saat tema berganti - kalau tidak, kartu tetap memakai warna
		 * tema sebelumnya di atas peta yang sudah baru. */
		renderSelection(state.selected);
	}

	if (window.MutationObserver) {
		new MutationObserver(function (records) {
			records.forEach(function (record) {
				if (record.attributeName === 'data-theme') {
					refreshTokens();
				}
			});
		}).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
	}

	/* Indeks baris data per nama kecamatan. Nama dari GeoJSON dan dari database
	 * bisa berbeda huruf besar/kecil, jadi kuncinya dinormalkan. */
	var byName = {};
	rows.forEach(function (row) {
		var key = String(row.kecamatan || '').trim().toLowerCase();
		if (key !== '') {
			byName[key] = row;
		}
	});

	function dataFor(name) {
		var key = String(name || '').trim().toLowerCase();
		return byName[key] || null;
	}

	function formatNumber(value) {
		return (Number(value) || 0).toLocaleString('id-ID');
	}

	/* Persentase bulat. Pembagi nol menghasilkan 0, bukan NaN yang akan tampil
	 * sebagai "NaN%" di panel. */
	function percent(part, whole) {
		var total = Number(whole) || 0;
		if (total <= 0) {
			return 0;
		}
		return Math.round(((Number(part) || 0) * 100) / total);
	}

	/**
	 * Kode yang paling banyak di satu kecamatan. Server sudah mengurutkan
	 * `top_codes` menurun (dan menurut kode saat jumlahnya sama), jadi entri
	 * pertama adalah pemenangnya - tidak ada pengurutan ulang di browser.
	 */
	function dominantFor(name) {
		var row = dataFor(name);
		if (!row || !Array.isArray(row.top_codes) || row.top_codes.length === 0) {
			return null;
		}
		var top = row.top_codes[0];
		var code = String(top.code || '');
		if (code === '') {
			return null;
		}
		return { code: code, label: String(top.label || ''), entries: Number(top.entries) || 0 };
	}

	/* Hue untuk satu kode penyakit. Di luar enam kode teratas kota, kode tetap
	 * mendapat warna netral yang TIDAK dipinjam dari kode lain: legenda yang
	 * memberi dua kode warna sama lebih buruk daripada satu warna "lainnya". */
	function codeColour(code) {
		if (!state || state.mode !== 'dominant') {
			return fillTokens[3];
		}
		for (var i = 0; i < codes.length && i < catTokens.length; i++) {
			if (String(codes[i].code) === String(code)) {
				return catTokens[i];
			}
		}
		return fillTokens[3];
	}

	function valueFor(name, code) {
		var row = dataFor(name);
		if (!row) {
			return 0;
		}
		/* Mode "Penyakit terbanyak" menjawab "kecamatan ini berapa banyak",
		 * jadi angkanya total terkode - kode yang dipakai untuk hue diambil
		 * per kecamatan di `dominantFor()`, bukan dari pilihan di toolbar. */
		if (state && state.mode === 'dominant') {
			code = '';
		}
		if (!code) {
			/* Semua kode: pakai jumlah diagnosis terkode seluruh kode. */
			return Number(row.coded) || 0;
		}
		var value = row.by_code ? row.by_code[code] : 0;
		return Number(value) || 0;
	}

	function maxFor(code) {
		if (state && state.mode === 'dominant') {
			code = '';
		}
		if (code) {
			var max = 0;
			rows.forEach(function (row) {
				var value = row.by_code ? Number(row.by_code[code]) || 0 : 0;
				max = Math.max(max, value);
			});
			return max;
		}
		return Number(config.maxCoded) > 0 ? Number(config.maxCoded) : 1;
	}

	/* Warna peta dibagi ke kelas tetap, bukan gradien opasitas: pembaca
	 * membandingkan wilayah dengan mencocokkan warna ke legenda, dan dua
	 * kecamatan yang opasitasnya berbeda 3% tidak bisa dibedakan. Batas kelas
	 * dihitung dari nilai terbesar kode yang sedang tampil, lalu legenda diberi
	 * angka batas itu - jadi "kelas 3" selalu berarti rentang yang tertulis.
	 *
	 * Saat nilai maksimum lebih kecil dari jumlah kelas, batas yang tidak masuk
	 * akal dibuang: legenda tidak boleh menulis "3-3" atau "6-4". */
	var MAX_LEVEL = fillTokens.length - 1;

	function classBreaks(max) {
		var cuts = [];
		if (!max || max <= 0) {
			return cuts;
		}
		for (var k = 1; k < MAX_LEVEL; k++) {
			var candidate = Math.ceil((max * k) / MAX_LEVEL);
			var last = cuts.length ? cuts[cuts.length - 1] : 0;
			if (candidate > last && candidate < max) {
				cuts.push(candidate);
			}
		}
		return cuts;
	}

	function levelFor(value, cuts) {
		if (!value || value <= 0) {
			return 0;
		}
		var level = 1;
		for (var i = 0; i < cuts.length; i++) {
			if (value > cuts[i]) {
				level = i + 2;
			}
		}
		return level;
	}

	/* Angka pada legenda. Kelas tanpa batas (karena nilai maksimum kecil)
	 * disembunyikan, bukan dibiarkan menampilkan rentang palsu. */
	function renderLegend(max) {
		var ranges = document.querySelectorAll('#doclincMapLegend .doclinc-map-legend-range');
		if (!ranges.length) {
			return;
		}
		var cuts = classBreaks(max);
		var used = cuts.length + 1;
		for (var level = 0; level <= MAX_LEVEL; level++) {
			var node = null;
			for (var i = 0; i < ranges.length; i++) {
				if (Number(ranges[i].getAttribute('data-level')) === level) {
					node = ranges[i];
					break;
				}
			}
			if (!node) {
				continue;
			}
			var item = node.parentNode;
			if (level > used) {
				if (item) {
					item.hidden = true;
				}
				continue;
			}
			if (item) {
				item.hidden = false;
			}
			if (level === 0) {
				node.textContent = '0';
			} else if (level === used) {
				var from = level === 1 ? 1 : cuts[level - 2] + 1;
				node.textContent = max > 0 ? from.toLocaleString('id-ID') + '\u2013' + max.toLocaleString('id-ID') : '0';
			} else {
				var lower = level === 1 ? 1 : cuts[level - 2] + 1;
				node.textContent = lower.toLocaleString('id-ID') + '\u2013' + cuts[level - 1].toLocaleString('id-ID');
			}
		}
	}

	var map = L.map(host, {
		attributionControl: false,
		zoomControl: true,
		scrollWheelZoom: false,
		boxZoom: false,
		zoomSnap: 0.25,
		minZoom: 9,
		maxZoom: 13,
		keyboard: true
	});

	var layers = [];
	/* Batas seluruh kota, disimpan supaya peta bisa dipaskan ulang setelah
	 * ukurannya berubah (layar penuh). `null` selama GeoJSON belum tergambar. */
	var cityBounds = null;
	var state = { code: '', mode: 'count', frame: 0, playing: false, timer: null, selected: '' };

	function labelFor(name) {
		var row = dataFor(name);
		var heading = '<strong>' + name + '</strong>';
		if (!row) {
			return heading + '<br><span>Tanpa data</span>';
		}
		if (state && state.mode === 'dominant') {
			var dominant = dominantFor(name);
			if (!dominant) {
				return heading + '<br><span>' + formatNumber(row.coded) + ' diagnosis</span>';
			}
			return heading + '<br><span>' + dominant.code + ' · ' + formatNumber(dominant.entries)
				+ ' (' + percent(dominant.entries, row.coded) + '%)</span>'
				+ '<br><span>klik untuk infografik kecamatan</span>';
		}
		return heading + '<br><span>' + formatNumber(valueFor(name, state.code)) + ' diagnosis</span>'
			+ '<br><span>klik untuk infografik kecamatan</span>';
	}

	/* Satu kalimat yang sama untuk aria-label di kedua mode: nilainya selalu
	 * yang sedang tampil, supaya pembaca layar tidak mendengar angka mode lain. */
	function describePolygon(name) {
		var row = dataFor(name);
		if (!row) {
			return 'Kecamatan ' + name + ': belum ada diagnosis';
		}
		if (state && state.mode === 'dominant') {
			var dominant = dominantFor(name);
			if (!dominant) {
				return 'Kecamatan ' + name + ': ' + formatNumber(row.coded) + ' diagnosis sudah dikode';
			}
			return 'Kecamatan ' + name + ': ' + formatNumber(row.coded) + ' diagnosis sudah dikode, '
				+ 'terbanyak ' + dominant.code + ' (' + percent(dominant.entries, row.coded) + '%)';
		}
		return 'Kecamatan ' + name + ': ' + formatNumber(valueFor(name, state.code)) + ' diagnosis sudah dikode';
	}

	function setStatus(code) {
		if (!status) {
			return;
		}
		if (state.mode === 'dominant') {
			/* Status menjawab apa yang sedang diwarnai, bukan kode yang
			 * kebetulan masih terpilih di toolbar: di mode ini hue tiap
			 * kecamatan datang dari kode terbanyak kecamatan itu sendiri. */
			status.textContent = 'Penyakit terbanyak per kecamatan · '
				+ formatNumber(rows.reduce(function (sum, row) {
					return sum + (Number(row.coded) || 0);
				}, 0)) + ' diagnosis sudah dikode';
			return;
		}
		if (!code) {
			/* Jumlahkan kolom yang SAMA dengan yang dipakai peringkat dan warna
			 * untuk "semua kode" (baris `coded`), bukan `entries`. `entries`
			 * hanya menjumlahkan delapan kode teratas pada matriks, sehingga
			 * status bisa lebih kecil daripada jumlah baris di peringkat -
			 * dua angka berbeda untuk pertanyaan yang sama. */
			status.textContent = 'Semua kode · ' + rows.reduce(function (sum, row) {
				return sum + (Number(row.coded) || 0);
			}, 0).toLocaleString('id-ID') + ' diagnosis';
			return;
		}
		var found = null;
		for (var i = 0; i < codes.length; i++) {
			if (codes[i].code === code) {
				found = codes[i];
				break;
			}
		}
		if (!found) {
			status.textContent = code;
			return;
		}
		status.textContent = code + (found.label ? ' · ' + found.label : '') + ' · '
			+ Number(found.entries || 0).toLocaleString('id-ID') + ' diagnosis';
	}

	/**
	 * Warnai peta untuk satu kode. Warna = kelas nilai; kecamatan terpilih
	 * memakai warna "dipilih" dan tidak lagi memakai warna kelasnya, karena dua
	 * penanda pada satu poligon (garis tebal + warna kelas) membuat orang
	 * menebak mana yang harus dibaca.
	 */
	function applyFrame(code) {
		if (state.mode === 'dominant') {
			applyDominantFrame();
			return;
		}
		toggleLegend();
		var max = maxFor(code);
		var cuts = classBreaks(max);
		layers.forEach(function (layer) {
			var name = layer.doclincName;
			var value = valueFor(name, code);
			var isSelected = state.selected !== '' && name === state.selected;
			var level = levelFor(value, cuts);
			layer.setStyle({
				fillColor: isSelected ? selectToken : fillTokens[level],
				fillOpacity: 1,
				color: isSelected ? selectToken : (level >= 4 ? deepToken : fillToken),
				weight: isSelected ? 3 : (level >= 4 ? 1.6 : 1),
				dashArray: null
			});
			if (layer.doclincTooltip) {
				layer.setTooltipContent(labelFor(name));
			}
			var element = layer.getElement();
			if (element) {
				element.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
				element.setAttribute('aria-label', describePolygon(name)
					+ (isSelected ? ', dipilih' : '')
					+ '. Tekan Enter untuk infografik kecamatan.');
			}
		});
		renderLegend(max);
	}

	/**
	 * Mode "Penyakit terbanyak": tiap kecamatan memakai hue kode terbanyaknya.
	 * Kecamatan tanpa diagnosis terkode tidak diberi hue apa pun - ia memakai
	 * kelas nol dengan garis putus-putus, jadi "tidak ada data" tidak menyamar
	 * sebagai salah satu penyakit. Seleksi tetap biru, sama seperti mode lain.
	 */
	function applyDominantFrame() {
		toggleLegend();
		layers.forEach(function (layer) {
			var name = layer.doclincName;
			var row = dataFor(name);
			var dominant = dominantFor(name);
			var isSelected = state.selected !== '' && name === state.selected;
			var fill = fillTokens[0];
			/* Garis netral, bukan hijau: batas putus-putus ini yang menjelaskan
			 * "tidak ada data", dan hue-nya tidak boleh terbaca sebagai kelas. */
			var stroke = mutedToken;
			var weight = 1.2;
			var dash = '5 4';
			if (row && dominant) {
				fill = codeColour(dominant.code);
				stroke = deepToken;
				weight = 1.4;
				dash = null;
			}
			if (isSelected) {
				fill = selectToken;
				stroke = selectToken;
				weight = 3;
				dash = null;
			}
			layer.setStyle({
				fillColor: fill,
				fillOpacity: 1,
				color: stroke,
				weight: weight,
				dashArray: dash
			});
			if (layer.doclincTooltip) {
				layer.setTooltipContent(labelFor(name));
			}
			var element = layer.getElement();
			if (element) {
				element.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
				element.setAttribute('aria-label', describePolygon(name)
					+ (isSelected ? ', dipilih' : '')
					+ '. Tekan Enter untuk infografik kecamatan.');
			}
		});
		renderDominantLegend();
	}

	/* Legenda mode "Penyakit terbanyak": satu hue per kode yang menjadi
	 * terbanyak di setidaknya satu kecamatan, berurutan menurut peringkat kode
	 * kota. Hanya kode yang benar-benar muncul sebagai pemenang yang ditulis -
	 * legenda yang memuat kode yang tidak dipakai peta hanya menambah tebak-tebakan. */
	function renderDominantLegend() {
		var box = document.getElementById('doclincMapLegendCats');
		if (!box) {
			return;
		}
		box.textContent = '';
		var title = document.createElement('span');
		title.className = 'doclinc-map-legend-title';
		title.textContent = 'Penyakit terbanyak';
		box.appendChild(title);

		var buckets = {};
		rows.forEach(function (row) {
			var dominant = dominantFor(row.kecamatan);
			if (!dominant) {
				return;
			}
			if (!buckets[dominant.code]) {
				buckets[dominant.code] = { code: dominant.code, label: dominant.label, kecamatan: 0 };
			}
			buckets[dominant.code].kecamatan += 1;
		});
		var cityOrder = codes.map(function (row) {
			return String(row.code);
		});
		var entries = Object.keys(buckets).map(function (key) {
			return buckets[key];
		});
		entries.sort(function (a, b) {
			var ai = cityOrder.indexOf(a.code);
			var bi = cityOrder.indexOf(b.code);
			if (ai < 0) {
				ai = cityOrder.length + 1;
			}
			if (bi < 0) {
				bi = cityOrder.length + 1;
			}
			return ai - bi || a.code.localeCompare(b.code);
		});
		entries.forEach(function (entry) {
			var item = document.createElement('span');
			item.className = 'doclinc-map-legend-item';
			var swatch = document.createElement('span');
			swatch.className = 'doclinc-map-swatch';
			swatch.setAttribute('aria-hidden', 'true');
			swatch.style.background = codeColour(entry.code);
			var text = document.createElement('span');
			text.className = 'doclinc-map-legend-text';
			text.textContent = entry.code + (entry.label ? ' · ' + entry.label : '')
				+ ' · ' + formatNumber(entry.kecamatan) + ' kec.';
			text.title = text.textContent;
			item.appendChild(swatch);
			item.appendChild(text);
			box.appendChild(item);
		});

		/* Tanpa data punya penanda sendiri (garis putus-putus), supaya ia tidak
		 * terbaca sebagai salah satu penyakit yang kebetulan berwarna muda. */
		box.appendChild(legendItem('doclinc-map-swatch-empty', 'tanpa data'));
		box.appendChild(legendItem('doclinc-map-swatch-selected', 'dipilih', true));
	}

	function legendItem(swatchClass, label, selected) {
		var item = document.createElement('span');
		item.className = 'doclinc-map-legend-item' + (selected ? ' doclinc-map-legend-selected' : '');
		var swatch = document.createElement('span');
		swatch.className = 'doclinc-map-swatch ' + swatchClass;
		swatch.setAttribute('aria-hidden', 'true');
		var text = document.createElement('span');
		text.textContent = label;
		item.appendChild(swatch);
		item.appendChild(text);
		return item;
	}

	/* Dua legenda tidak pernah tampil bersamaan: satu tangga nilai, satu peta
	 * hue. Menampilkan keduanya sekaligus membuat pembaca mencari arti warna
	 * yang sedang tidak dipakai peta. */
	function toggleLegend() {
		var sequential = document.getElementById('doclincMapLegend');
		var categorical = document.getElementById('doclincMapLegendCats');
		var dominant = state.mode === 'dominant';
		if (sequential) {
			sequential.hidden = dominant;
		}
		if (categorical) {
			categorical.hidden = !dominant;
		}
	}

	/**
	 * Empat angka kecamatan - pertanyaan "kecamatan ini sakit apa" dijawab
	 * sebelum daftar kodenya. Semua angka datang dari server; persentase dihitung
	 * dari dua angka server (bagian dan total), bukan dari panjang daftar.
	 */
	function renderFigures(top, row) {
		var box = document.getElementById('doclincMapFigures');
		if (!box) {
			return;
		}
		box.textContent = '';
		if (!row) {
			return;
		}
		var coded = Number(row.coded) || 0;
		var entries = Number(row.entries) || 0;
		var uncoded = Number(row.uncoded) || 0;
		var city = 0;
		rows.forEach(function (item) {
			city += Number(item.coded) || 0;
		});
		var dominant = top.length > 0 ? top[0] : null;
		/* Payload lama tanpa `codes_coded` tidak boleh tampil sebagai "0 jenis
		 * penyakit" di samping daftar yang jelas berisi kode: angka nol terbaca
		 * sebagai fakta. Yang dipakai cadangannya adalah panjang daftar - lebih
		 * kecil dari kenyataan, tetapi tidak pernah salah arah. */
		var distinctCodes = (row.codes_coded === undefined || row.codes_coded === null)
			? top.length
			: row.codes_coded;
		var figures = [
			{
				label: 'Sudah dikode',
				value: formatNumber(coded),
				note: percent(coded, city) + '% dari ' + formatNumber(city) + ' diagnosis yang sudah dikode'
			},
			{
				label: 'Jenis penyakit',
				value: formatNumber(distinctCodes),
				note: 'kode ICD-10 berbeda'
			},
			{
				label: 'Penyakit dominan',
				value: dominant ? String(dominant.code || '') : '\u2014',
				note: dominant
					? percent(dominant.entries, coded) + '% dari kecamatan ini'
					: 'belum ada kode',
				code: true
			},
			{
				/* Satu istilah untuk satu status: panel "Diagnosis belum dikode",
				   kartu angka, dan tabel semuanya memakai "belum dikode".
				   `admin_panel_diagnosis_coding.spec.js` mengunci keseragaman itu;
				   istilah kedua di sini ("belum teridentifikasi") membuat admin
				   mengira keduanya keadaan berbeda. */
				label: 'Belum dikode',
				value: formatNumber(uncoded),
				note: percent(uncoded, entries) + '% dari ' + formatNumber(entries) + ' diagnosis'
			}
		];
		figures.forEach(function (figure) {
			var cell = document.createElement('div');
			cell.className = 'doclinc-map-figure';
			var label = document.createElement('span');
			label.className = 'doclinc-map-figure-label';
			label.textContent = figure.label;
			var value = document.createElement('span');
			value.className = 'doclinc-map-figure-value' + (figure.code ? ' doclinc-map-figure-code' : '');
			value.textContent = figure.value;
			var note = document.createElement('span');
			note.className = 'doclinc-map-figure-note';
			note.textContent = figure.note;
			cell.appendChild(label);
			cell.appendChild(value);
			cell.appendChild(note);
			box.appendChild(cell);
		});
	}

	/**
	 * Pita porsi: satu batang 100% dibagi menurut kode. Bentuk ini menyampaikan
	 * hal yang tidak terbaca dari sepuluh batang terpisah - apakah satu penyakit
	 * mendominasi atau kasusnya tersebar. Sisa yang tidak masuk sepuluh kode
	 * teratas tetap digambar sebagai "kode lain", supaya pitanya berjumlah 100%
	 * dari angka yang tertulis di subjudul.
	 */
	function renderShare(top, row) {
		var strip = document.getElementById('doclincMapShare');
		var caption = document.getElementById('doclincMapShareNote');
		if (!strip) {
			return;
		}
		strip.textContent = '';
		var coded = row ? Number(row.coded) || 0 : 0;
		if (!row || top.length === 0 || coded <= 0) {
			strip.hidden = true;
			if (caption) {
				caption.textContent = '';
			}
			return;
		}
		strip.hidden = false;
		var used = 0;
		var described = [];
		top.forEach(function (entry) {
			var entries = Number(entry.entries) || 0;
			used += entries;
			var segment = document.createElement('span');
			segment.className = 'doclinc-map-share-seg';
			segment.style.inlineSize = ((entries / coded) * 100).toFixed(2) + '%';
			segment.style.background = codeColour(String(entry.code || ''));
			segment.title = String(entry.code || '') + ' · ' + formatNumber(entries)
				+ ' (' + percent(entries, coded) + '%)';
			strip.appendChild(segment);
			described.push(String(entry.code || '') + ' ' + percent(entries, coded) + '%');
		});
		var rest = Math.max(0, coded - used);
		if (rest > 0) {
			var other = document.createElement('span');
			other.className = 'doclinc-map-share-seg doclinc-map-share-seg-rest';
			other.style.inlineSize = ((rest / coded) * 100).toFixed(2) + '%';
			other.title = 'Kode lain · ' + formatNumber(rest);
			strip.appendChild(other);
			described.push('kode lain ' + percent(rest, coded) + '%');
		}
		strip.setAttribute('aria-label', 'Porsi kode di kecamatan ini: ' + described.join(', ') + '.');
		if (caption) {
			caption.textContent = 'Pita = porsi tiap kode dari ' + formatNumber(coded)
				 + ' diagnosis sudah dikode'
				+ (rest > 0 ? '; sisa ' + formatNumber(rest) + ' ada di kode lain.' : '.');
		}
	}

	/**
	 * Kartu infografik untuk satu kecamatan, atau menyembunyikan kartu bila
	 * namanya kosong. Semua angka berasal dari payload server (`coded`,
	 * `codes_coded`, `top_codes`); berkas ini menyusunnya, tidak menghitung ulang.
	 *
	 * Kartu ini mengambang di atas peta, jadi ia diperlakukan sebagai dialog:
	 * fokus dipindahkan ke kartu saat kartu BARU terbuka (pembaca layar yang
	 * menekan Enter pada poligon harus mendengar isinya, bukan tetap berada di
	 * poligon di belakangnya), dan dikembalikan ke poligon saat kartu ditutup.
	 * Saat isi kartu digambar ulang - ganti mode atau tema - fokus dibiarkan,
	 * karena fokus yang melompat tanpa sebab lebih mengganggu daripada membantu.
	 */
	function renderSelection(name) {
		var panel = document.getElementById('doclincMapSelection');
		var list = document.getElementById('doclincMapSelectionList');
		var title = document.getElementById('doclincMapSelectionTitle');
		var note = document.getElementById('doclincMapSelectionStatus');
		if (!panel || !list) {
			return;
		}
		var wasHidden = panel.hidden;
		if (!name) {
			panel.hidden = true;
			list.textContent = '';
			if (title) {
				title.textContent = 'Kecamatan';
			}
			if (note) {
				note.textContent = '';
			}
			renderFigures([], null);
			renderShare([], null);
			return;
		}
		var row = dataFor(name);
		var top = row && Array.isArray(row.top_codes) ? row.top_codes.slice(0, 10) : [];
		var coded = row ? Number(row.coded) || 0 : 0;
		panel.hidden = false;
		/* Badan kartu selalu mulai dari atas: mengganti kecamatan tanpa
		 * mengembalikan guliran akan memperlihatkan daftar kode kecamatan
		 * sebelumnya di bagian tengah kartu. */
		var body = panel.querySelector('.doclinc-map-card-body');
		if (body) {
			body.scrollTop = 0;
		}
		if (wasHidden && typeof panel.focus === 'function') {
			panel.focus({ preventScroll: true });
		}
		revealSelected(name);
		if (title) {
			/* Judul menyebut kecamatannya, bukan jumlah baris: berapa kode
			 * yang tampil sudah dikatakan subjudul, dan judul tidak pernah
			 * menjanjikan sepuluh baris untuk kecamatan yang baru punya tiga. */
			title.textContent = 'Kecamatan ' + name;
		}
		if (note) {
			if (!row) {
				note.textContent = 'Kecamatan ini belum punya baris data pada periode terpilih.';
			} else if (top.length === 0) {
				note.textContent = 'Belum ada diagnosis yang sudah dikode di kecamatan ini pada periode terpilih.';
			} else {
				note.textContent = formatNumber(top.length) + ' kode ICD-10 teratas dari '
					+ formatNumber(coded) + ' diagnosis sudah dikode di kecamatan ini.';
			}
		}
		renderFigures(top, row);
		renderShare(top, row);
		list.textContent = '';
		if (top.length === 0) {
			var empty = document.createElement('li');
			empty.className = 'doclinc-map-selection-empty';
			empty.textContent = 'Tidak ada diagnosis yang sudah dikode untuk ditampilkan.';
			list.appendChild(empty);
			return;
		}
		var max = Number(top[0].entries) || 0;
		top.forEach(function (entry, index) {
			var item = document.createElement('li');
			item.className = 'doclinc-map-selection-item';

			var rank = document.createElement('span');
			rank.className = 'doclinc-map-selection-rank';
			rank.textContent = String(index + 1) + '.';

			var code = document.createElement('span');
			code.className = 'doclinc-map-selection-code';
			/* Hue di baris hanya ada di mode "Penyakit terbanyak", tempat hue
			 * itu sama dengan warna poligon kecamatan ini di peta. Di mode
			 * jumlah, baris memakai satu warna supaya tidak terbaca sebagai
			 * kelas nilai yang berbeda-beda. */
			if (state.mode === 'dominant') {
				var swatch = document.createElement('span');
				swatch.className = 'doclinc-map-selection-swatch';
				swatch.setAttribute('aria-hidden', 'true');
				swatch.style.background = codeColour(String(entry.code || ''));
				code.appendChild(swatch);
			}
			var codeText = document.createTextNode(String(entry.code || ''));
			code.appendChild(codeText);

			var label = document.createElement('span');
			label.className = 'doclinc-map-selection-label';
			label.textContent = String(entry.label || '') || 'Tanpa label';

			var value = document.createElement('span');
			value.className = 'doclinc-map-selection-value';
			value.textContent = formatNumber(entry.entries);

			var bar = document.createElement('span');
			bar.className = 'doclinc-map-selection-bar';
			bar.style.inlineSize = (max > 0 ? Math.round((Number(entry.entries || 0) / max) * 100) : 0) + '%';
			if (state.mode === 'dominant') {
				bar.style.background = codeColour(String(entry.code || ''));
			}

			item.appendChild(rank);
			item.appendChild(code);
			item.appendChild(label);
			item.appendChild(value);
			item.appendChild(bar);
			list.appendChild(item);
		});
	}

	/* Lapisan poligon untuk satu nama kecamatan. Dipakai untuk mengembalikan
	 * fokus dan untuk menggeser peta, keduanya bekerja pada elemen/geometri
	 * poligon - bukan pada daftar di samping peta yang sudah tidak ada. */
	function layerFor(name) {
		for (var i = 0; i < layers.length; i++) {
			if (layers[i].doclincName === name) {
				return layers[i];
			}
		}
		return null;
	}

	/* Fokus kembali ke poligon yang barusan dipilih. Tanpa ini, menutup kartu
	 * dengan Escape meninggalkan fokus di dalam kartu yang sudah tersembunyi,
	 * dan pengguna papan ketik harus menekan Tab dari awal halaman. */
	function focusDistrict(name) {
		var layer = layerFor(name);
		var element = layer ? layer.getElement() : null;
		if (element && typeof element.focus === 'function') {
			element.focus({ preventScroll: true });
		}
	}

	/* Geser peta sedikit supaya poligon yang dipilih tidak tertutup kartu.
	 * Hanya di layar lebar: di ponsel kartu menutupi hampir seluruh peta, dan
	 * menggeser poligon ke sisa ruang hanya akan mendorongnya keluar pandangan.
	 * `panInside` tidak melakukan apa pun bila poligonnya sudah terlihat, jadi
	 * aman dipanggil setiap kali kartu digambar ulang. */
	function revealSelected(name) {
		var layer = layerFor(name);
		var panel = document.getElementById('doclincMapSelection');
		if (!layer || !panel || typeof map.panInside !== 'function') {
			return;
		}
		if (window.matchMedia && window.matchMedia('(max-width: 767.98px)').matches) {
			return;
		}
		var gutter = panel.offsetWidth + 24;
		if (gutter * 2 >= map.getSize().x) {
			return;
		}
		map.panInside(layer.getBounds().getCenter(), {
			paddingTopLeft: [16, 16],
			paddingBottomRight: [gutter, 16],
			animate: !reducedMotion,
			duration: 0.35
		});
	}

	/**
	 * Pilih / batalkan pilihan kecamatan. Mengklik kecamatan yang sama dua kali
	 * mengembalikan peta ke keadaan semula - itu cara termurah untuk membatalkan
	 * tanpa mencari tombol.
	 */
	function toggleSelection(name) {
		var next = state.selected === name ? '' : name;
		state.selected = next;
		applyFrame(state.code);
		renderSelection(next);
	}

	/**
	 * Tutup kartu dan kembalikan peta ke keadaan tanpa pilihan. `restoreFocus`
	 * hanya dipakai jalur papan ketik (Escape) dan tombol tutup: pada klik area
	 * kosong, fokus memang sudah pindah ke peta, jadi memindahkannya lagi ke
	 * poligon lama akan mengagetkan.
	 */
	function clearSelection(options) {
		var previous = state.selected;
		if (previous === '') {
			return;
		}
		state.selected = '';
		applyFrame(state.code);
		renderSelection('');
		if (options && options.restoreFocus) {
			focusDistrict(previous);
		}
	}

	function animateTo(code) {
		state.code = code;
		state.frame = 1;
		applyFrame(code);
		setStatus(code);
	}

	function buildLayers(geojson) {
		var geoLayer = L.geoJSON(geojson, {
			style: function () {
				return { color: fillToken, weight: 1, fillColor: fillTokens[0], fillOpacity: 1 };
			},
			onEachFeature: function (feature, layer) {
				var name = (feature.properties && feature.properties.kecamatan) || '';
				layer.doclincName = name;
				layer.doclincTooltip = layer.bindTooltip(labelFor(name), {
					direction: 'top',
					sticky: true,
					className: 'doclinc-map-label',
					opacity: 1
				});
				layer.on('click', function (event) {
					/* Klik poligon tidak boleh diteruskan ke peta: peta
					 * membatalkan pilihan, jadi tanpa ini klik akan memilih lalu
					 * langsung membatalkan pada event yang sama.
					 *
					 * Yang dihentikan harus event LEAFLET, bukan event DOM:
					 * L.DomEvent.stopPropagation(event DOM) hanya menghentikan
					 * perambahan DOM, sedangkan Leaflet tetap meneruskan event
					 * yang sama ke target berikutnya (peta) - penanda
					 * `originalEvent._stopped` hanya dipasang bila argumennya
					 * event Leaflet. Tanpa ini kartu terbuka dan tertutup lagi
					 * dalam satu klik. */
					L.DomEvent.stopPropagation(event);
					toggleSelection(name);
				});
				layers.push(layer);
			}
		});
		geoLayer.addTo(map);

		/* View harus dipasang SEBELUM atribut per poligon: Leaflet menunda
		 * menambahkan layer ke peta sampai peta punya view, dan selama itu
		 * layer.getElement() bernilai null. Tanpa urutan ini, atribut aksesibilitas
		 * dipasang pada elemen yang belum ada dan hilang tanpa galat. */
		map.fitBounds(geoLayer.getBounds(), { padding: [18, 18] });
		map.setMaxBounds(geoLayer.getBounds().pad(0.35));

		/* Leaflet tidak membuat path SVG dapat difokus. Tanpa tabindex dan
		 * penangan tombol, peta ini hanya bisa dipakai dengan tetikus - dan
		 * seluruh isinya (warna + daftar 10 penyakit) tidak terjangkau papan
		 * ketik. Atribut `aria-pressed` diisi ulang oleh applyFrame(). */
		layers.forEach(function (layer) {
			var element = layer.getElement();
			if (!element) {
				return;
			}
			element.setAttribute('tabindex', '0');
			element.setAttribute('role', 'button');
			element.setAttribute('aria-pressed', 'false');
			element.setAttribute('aria-label', 'Kecamatan ' + layer.doclincName + '. Tekan Enter untuk infografik kecamatan.');
			element.addEventListener('keydown', function (event) {
				if (event.key === 'Enter' || event.key === ' ' || event.key === 'Spacebar') {
					event.preventDefault();
					toggleSelection(layer.doclincName);
				}
			});
		});

		/* Klik di area kosong peta = tutup kartu, sama seperti tombol tutup
		 * di kepala kartu. Fokus tidak dikembalikan ke poligon lama: klik itu
		 * memang sedang memindahkan perhatian ke peta. */
		map.on('click', clearSelection);

		map.fitBounds(geoLayer.getBounds(), { padding: [18, 18] });
		map.setMaxBounds(geoLayer.getBounds().pad(0.35));
		cityBounds = geoLayer.getBounds();
	}

	var order = [''].concat(codes.map(function (row) {
		return String(row.code);
	}));
	var cursor = 0;

	function syncButton() {
		if (!playButton) {
			return;
		}
		var label = playButton.querySelector('span');
		var icon = playButton.querySelector('i');
		playButton.setAttribute('aria-pressed', state.playing ? 'true' : 'false');
		if (label) {
			label.textContent = state.playing ? 'Jeda' : 'Presentasi';
		}
		if (icon) {
			icon.className = state.playing ? 'fas fa-pause' : 'fas fa-play';
		}
	}

	function stop() {
		if (state.timer !== null) {
			window.clearInterval(state.timer);
			state.timer = null;
		}
		state.playing = false;
		syncButton();
	}

	/**
	 * Ganti bahasa warna peta. Mode "dominant" menghentikan putar-otomatis:
	 * putaran itu mengganti kode yang dipilih, pertanyaan yang tidak dipakai
	 * mode ini - tombol yang berhenti sendiri tanpa sebab lebih membingungkan
	 * daripada tombol yang memang tidak berjalan.
	 */
	function setMode(mode) {
		var next = mode === 'dominant' ? 'dominant' : 'count';
		if (next === state.mode) {
			return;
		}
		if (next === 'dominant') {
			stop();
		}
		state.mode = next;
		if (modeSelect) {
			modeSelect.value = next;
		}
		applyFrame(state.code);
		setStatus(state.code);
		renderSelection(state.selected);
	}

	function show(code) {
		cursor = order.indexOf(code);
		if (cursor < 0) {
			cursor = 0;
		}
		if (select) {
			select.value = code;
		}
		animateTo(code);
	}

	function advance() {
		cursor = (cursor + 1) % order.length;
		var code = order[cursor];
		if (select) {
			select.value = code;
		}
		animateTo(code);
	}

	function play() {
		/* Putar selalu berarti "satu kode demi satu kode". Pindah ke mode jumlah
		 * secara eksplisit lebih jujur daripada tombol yang tidak melakukan
		 * apa pun karena mode lain sedang aktif. */
		if (state.mode === 'dominant') {
			setMode('count');
		}
		/* Tanpa gerak otomatis bila pengguna meminta gerak dikurangi: tombol tetap
		 * berguna sebagai "kode berikutnya", tetapi tidak berjalan sendiri. */
		if (reducedMotion) {
			advance();
			return;
		}
		if (state.playing) {
			stop();
			return;
		}
		state.playing = true;
		syncButton();
		advance();
		state.timer = window.setInterval(advance, Math.max(900, stepMs));
	}

	if (playButton) {
		playButton.addEventListener('click', play);
	}

	/* ----------------------------------------------------------- layar penuh
	 * Peta bisa dibuka memenuhi layar karena delapan kecamatan Cilegon sulit
	 * dibaca di dalam peta setinggi ~620px. Dua jalur untuk satu tombol:
	 *
	 *   1. Fullscreen API pada KANVAS peta, bukan pada bingkai peta: legenda,
	 *      status, dan atribusi ada di dalam kanvas, dan membesarkan bingkai
	 *      saja akan menyembunyikan arti warnanya.
	 *   2. Kelas `is-expanded` bila API tidak ada atau permintaannya ditolak
	 *      (Safari iOS, iframe tanpa izin fullscreen). Hasilnya sama di layar,
	 *      hanya bilah peramban tetap terlihat.
	 *
	 * Leaflet menyimpan ukuran kontainer saat peta dibuat. Tanpa
	 * invalidateSize(), poligon tetap menempati ukuran lama di pojok kanvas
	 * yang sudah membesar - peta terlihat "setengah kosong", bukan penuh.
	 */
	var canvas = document.querySelector('.doclinc-map-canvas');
	var fullscreenButton = document.getElementById('doclincMapFullscreen');
	var exitButton = document.getElementById('doclincMapExit');
	var expanded = false;

	function fullscreenElement() {
		return document.fullscreenElement || document.webkitFullscreenElement || null;
	}

	function isExpanded() {
		return expanded || (canvas !== null && fullscreenElement() === canvas);
	}

	/* Satu tombol, dua label. `aria-pressed` mengikuti keadaan yang sama supaya
	 * pembaca layar tidak perlu menebak dari ikon; label ikut berubah karena
	 * tombol yang sama juga yang mengeluarkan peta dari layar penuh. Tombol
	 * "Keluar layar penuh" di dalam peta tidak perlu `aria-pressed`: ia aksi
	 * sesaat, bukan sakelar. */
	function syncFullscreen() {
		var on = isExpanded();
		if (fullscreenButton) {
			fullscreenButton.setAttribute('aria-pressed', on ? 'true' : 'false');
			var label = fullscreenButton.querySelector('span');
			var icon = fullscreenButton.querySelector('i');
			if (label) {
				label.textContent = on ? 'Keluar layar penuh' : 'Layar penuh';
			}
			if (icon) {
				icon.className = on ? 'fas fa-compress' : 'fas fa-expand';
			}
		}
	}

	function fitCity() {
		if (cityBounds) {
			map.fitBounds(cityBounds, { padding: [18, 18], animate: false });
		}
	}

	function resizeMap() {
		map.invalidateSize({ animate: false });
		/* Dipaskan ulang ke seluruh kota: peta yang sama pada kotak yang lebih
		 * besar menyisakan tepi kosong di mana-mana, padahal tujuan layar penuh
		 * justru membuat poligonnya lebih besar. */
		fitCity();
	}

	/* Ukuran baru terbaca dua kali: segera setelah tata letak berubah, dan lagi
	 * setelah transisi layar penuh peramban selesai - selama transisi itu
	 * kontainer tumbuh sedikit demi sedikit. */
	function requestResize() {
		window.requestAnimationFrame(resizeMap);
		window.setTimeout(resizeMap, 240);
	}

	function collapseExpanded() {
		if (!expanded) {
			return;
		}
		expanded = false;
		if (canvas) {
			canvas.classList.remove('is-expanded');
		}
		document.documentElement.classList.remove('doclinc-map-expanded');
		syncFullscreen();
		requestResize();
		if (fullscreenButton) {
			fullscreenButton.focus({ preventScroll: true });
		}
	}

	function expandWithClass() {
		if (!canvas) {
			return;
		}
		expanded = true;
		canvas.classList.add('is-expanded');
		/* Gulir halaman dikunci; tanpa ini latar belakang ikut bergulir di balik
		 * peta yang menutupi seluruh layar. */
		document.documentElement.classList.add('doclinc-map-expanded');
		syncFullscreen();
		requestResize();
		if (exitButton) {
			exitButton.focus({ preventScroll: true });
		}
	}

	function exitFullscreen() {
		if (expanded) {
			collapseExpanded();
			return;
		}
		if (fullscreenElement() !== canvas) {
			return;
		}
		if (typeof document.exitFullscreen === 'function') {
			document.exitFullscreen();
		} else if (typeof document.webkitExitFullscreen === 'function') {
			document.webkitExitFullscreen();
		}
	}

	function enterFullscreen() {
		if (!canvas) {
			return;
		}
		var request = null;
		try {
			request = canvas.requestFullscreen
				? canvas.requestFullscreen({ navigationUI: 'hide' })
				: (canvas.webkitRequestFullscreen ? canvas.webkitRequestFullscreen() : null);
		} catch (error) {
			request = null;
		}
		if (request && typeof request.catch === 'function') {
			/* Permintaan bisa ditolak (mis. iframe tanpa allow="fullscreen").
			 * Jangan biarkan tombolnya mati: pakai jalur kelas. */
			request.catch(expandWithClass);
		} else if (!request) {
			expandWithClass();
		}
	}

	/* Dipanggil peramban setiap kali elemen layar penuh berganti - termasuk
	 * saat pengguna keluar dengan Escape. Fokus dipindahkan ke tombol yang
	 * terlihat: setelah kanvas menjadi layar penuh, tombol di kepala panel
	 * berada di luar pandangan. */
	function onFullscreenChange() {
		var on = isExpanded();
		syncFullscreen();
		requestResize();
		var target = on ? exitButton : fullscreenButton;
		if (target && typeof target.focus === 'function') {
			target.focus({ preventScroll: true });
		}
	}

	if (fullscreenButton) {
		fullscreenButton.hidden = false;
		fullscreenButton.addEventListener('click', function () {
			if (isExpanded()) {
				exitFullscreen();
			} else {
				enterFullscreen();
			}
		});
	}

	if (exitButton) {
		exitButton.addEventListener('click', exitFullscreen);
	}

	['fullscreenchange', 'webkitfullscreenchange'].forEach(function (name) {
		document.addEventListener(name, onFullscreenChange);
	});

	/* Rotasi layar ponsel atau jendela yang diubah saat peta besar: ukuran
	 * kontainer berubah tanpa fullscreenchange, jadi peta perlu dikabari. */
	window.addEventListener('resize', function () {
		if (isExpanded()) {
			requestResize();
		}
	});

	if (modeSelect) {
		modeSelect.addEventListener('change', function () {
			setMode(String(modeSelect.value || 'count'));
		});
	}

	if (select) {
		select.addEventListener('change', function () {
			stop();
			/* Memilih kode berarti bertanya tentang kode itu: warna peta kembali
			 * ke mode jumlah, supaya pilihan di toolbar tidak tampak tidak
			 * berpengaruh saat mode "Penyakit terbanyak" sedang aktif. */
			setMode('count');
			show(String(select.value || ''));
		});
	}

	var clearButton = document.getElementById('doclincMapClear');
	if (clearButton) {
		clearButton.addEventListener('click', function () {
			clearSelection({ restoreFocus: true });
		});
	}

	/* Escape menutup kartu dari mana pun fokus berada; tanpa ini satu-satunya
	 * jalan keluar adalah tombol yang bisa saja tergulir keluar dari pandangan
	 * di dalam badan kartu. Fokus kembali ke poligon supaya pengguna papan
	 * ketik tidak kehilangan tempatnya.
	 *
	 * Di jalur layar penuh cadangan (tanpa Fullscreen API) Escape juga keluar
	 * dari peta besar - peramban tidak menyediakannya di sana, dan tanpa ini
	 * papan ketik tidak punya jalan pulang sama sekali. Kartu ditutup lebih
	 * dulu: Escape berulang berarti "tutup lapisan teratas". */
	document.addEventListener('keydown', function (event) {
		if (event.key !== 'Escape') {
			return;
		}
		if (state.selected !== '') {
			clearSelection({ restoreFocus: true });
			return;
		}
		if (expanded) {
			collapseExpanded();
		}
	});

	function load() {
		if (!config.geojsonUrl) {
			return;
		}
		window.fetch(config.geojsonUrl, { credentials: 'same-origin' })
			.then(function (response) {
				if (!response.ok) {
					throw new Error('HTTP ' + response.status);
				}
				return response.json();
			})
			.then(function (geojson) {
				buildLayers(geojson);
				show('');
			})
			.catch(function () {
				if (status) {
					status.textContent = 'Peta tidak bisa dimuat; angka tetap ada di tabel bawah.';
				}
			});
	}

	load();
})();


