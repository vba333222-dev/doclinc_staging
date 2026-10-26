/**
 * Peta sebaran penyakit per kecamatan Kota Cilegon.
 *
 * Cara kerjanya: bingkai peta digambar dari GeoJSON batas kecamatan yang ada di
 * repo (assets/geojson/kecamatan-cilegon.geojson), lalu tiap kecamatan diberi
 * warna dan gelembung sesuai jumlah diagnosis terkode untuk kode yang sedang
 * dipilih. Tombol "Putar" memindahkan pilihan itu dari satu kode ke kode
 * berikutnya, sehingga sebarannya terlihat bergerak seperti infografis penyakit.
 *
 * Aturan yang dipegang, sama seperti grafik batang di halaman ini:
 *   - Angka diambil dari bootstrap JSON yang disiapkan server. Berkas ini tidak
 *     menghitung ulang dan tidak mengarang angka; kecamatan tanpa data bernilai 0.
 *   - Angka yang sama tersedia sebagai tabel di dalam <details>, jadi halaman
 *     tetap terbaca tanpa JavaScript dan tanpa peta.
 *   - Warna diambil dari token CSS (--dl-ref-*), tidak ada hex di sini.
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
	var surfaceToken = token('--dl-ref-surface', '#ffffff');
	var inkToken = token('--dl-ref-ink', '#1c2b25');

	/* Token dibaca sekali saat panel digambar. Tema panel bisa berganti tanpa
	 * memuat ulang halaman, jadi nilainya dibaca ulang saat atribut `data-theme`
	 * berubah - kalau tidak, peta tetap memakai warna tema sebelumnya. */
	function refreshTokens() {
		fillToken = token('--dl-ref-green-600', fillToken);
		deepToken = token('--dl-ref-green-900', deepToken);
		surfaceToken = token('--dl-ref-surface', surfaceToken);
		inkToken = token('--dl-ref-ink', inkToken);
		layers.forEach(function (layer) {
			layer.setStyle({ color: fillToken, fillColor: fillToken });
		});
		bubbles.forEach(function (bubble) {
			bubble.setStyle({ color: surfaceToken, fillColor: inkToken });
		});
		applyFrame(state.code);
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

	function valueFor(name, code) {
		var row = dataFor(name);
		if (!row) {
			return 0;
		}
		if (!code) {
			/* Semua kode: pakai jumlah diagnosis terkode seluruh kode. */
			return Number(row.coded) || 0;
		}
		var value = row.by_code ? row.by_code[code] : 0;
		return Number(value) || 0;
	}

	function maxFor(code) {
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

	/* Pusat gelembung diambil dari cincin terluas, bukan dari pusat kotak
	 * pembatas: kecamatan di pesisir punya pulau kecil yang bisa menarik titik
	 * tengahnya ke laut. */
	function ringArea(ring) {
		var sum = 0;
		for (var i = 0; i < ring.length - 1; i++) {
			sum += ring[i][0] * ring[i + 1][1] - ring[i + 1][0] * ring[i][1];
		}
		return Math.abs(sum) / 2;
	}

	function ringCenter(ring) {
		var twiceArea = 0;
		var x = 0;
		var y = 0;
		for (var i = 0; i < ring.length - 1; i++) {
			var cross = ring[i][0] * ring[i + 1][1] - ring[i + 1][0] * ring[i][1];
			twiceArea += cross;
			x += (ring[i][0] + ring[i + 1][0]) * cross;
			y += (ring[i][1] + ring[i + 1][1]) * cross;
		}
		if (Math.abs(twiceArea) < 1e-12) {
			return [ring[0][1], ring[0][0]];
		}
		return [y / (3 * twiceArea), x / (3 * twiceArea)];
	}

	function centerOf(feature) {
		var geometry = feature.geometry || {};
		var polygons = geometry.type === 'MultiPolygon' ? geometry.coordinates : [geometry.coordinates];
		var best = null;
		var bestArea = -1;
		polygons.forEach(function (polygon) {
			var outer = polygon && polygon[0];
			if (!outer || outer.length < 4) {
				return;
			}
			var area = ringArea(outer);
			if (area > bestArea) {
				bestArea = area;
				best = outer;
			}
		});
		return best ? ringCenter(best) : [0, 0];
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
	var bubbles = [];
	var state = { code: '', frame: 0, playing: false, timer: null };
	var frameId = null;

	function ratioFor(value, max) {
		if (!max || value <= 0) {
			return 0;
		}
		return Math.min(1, value / max);
	}

	function labelFor(name) {
		var row = dataFor(name);
		var value = valueFor(name, state.code);
		var heading = '<strong>' + name + '</strong>';
		if (!row) {
			return heading + '<br><span>Tanpa data</span>';
		}
		return heading + '<br><span>' + value.toLocaleString('id-ID') + ' diagnosis</span>';
	}

	/* Peringkat di samping peta memuat angka yang sama dengan warna dan
	 * gelembung. Ia ada karena label di atas poligon saling menutupi pada
	 * kecamatan kecil - dan angka yang tertutup lebih buruk daripada tidak ada
	 * angka sama sekali. */
	function renderRanking(code) {
		var list = document.getElementById('doclincMapRanking');
		var title = document.getElementById('doclincMapRankingTitle');
		if (!list) {
			return;
		}
		var max = maxFor(code);
		var sorted = rows.slice().sort(function (a, b) {
			return valueFor(b.kecamatan, code) - valueFor(a.kecamatan, code);
		});
		if (title) {
			title.textContent = code ? 'Kecamatan · ' + code : 'Kecamatan · semua kode';
		}
		list.textContent = '';
		sorted.forEach(function (row) {
			var value = valueFor(row.kecamatan, code);
			var item = document.createElement('li');
			item.className = 'doclinc-map-ranking-item';
			var bar = document.createElement('span');
			bar.className = 'doclinc-map-ranking-bar';
			bar.style.inlineSize = (max > 0 ? Math.round((value / max) * 100) : 0) + '%';
			var name = document.createElement('span');
			name.className = 'doclinc-map-ranking-name';
			name.textContent = row.kecamatan;
			var count = document.createElement('span');
			count.className = 'doclinc-map-ranking-value';
			count.textContent = value.toLocaleString('id-ID');
			item.appendChild(name);
			item.appendChild(count);
			item.appendChild(bar);
			list.appendChild(item);
		});
	}

	function setStatus(code) {
		if (!status) {
			return;
		}
		if (!code) {
			status.textContent = 'Semua kode · ' + rows.reduce(function (sum, row) {
				return sum + (Number(row.entries) || 0);
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

	function applyFrame(code) {
		var max = maxFor(code);
		layers.forEach(function (layer) {
			var name = layer.doclincName;
			var value = valueFor(name, code);
			var ratio = ratioFor(value, max);
			var fillOpacity = reducedMotion ? Math.max(0.16, ratio * 0.84) : (0.16 + ratio * 0.72);
			layer.setStyle({
				fillOpacity: fillOpacity,
				weight: ratio > 0.66 ? 1.6 : 1,
				color: ratio > 0.66 ? deepToken : fillToken
			});
			if (layer.doclincTooltip) {
				layer.setTooltipContent(labelFor(name));
			}
		});
		bubbles.forEach(function (bubble) {
			var value = valueFor(bubble.doclincName, code);
			var ratio = ratioFor(value, max);
			bubble.setStyle({ opacity: value > 0 ? 1 : 0, fillOpacity: value > 0 ? 0.85 : 0 });
			bubble.setRadius(value > 0 ? 5 + 26 * Math.sqrt(ratio) : 0);
		});
	}

	function animateTo(code) {
		if (frameId !== null) {
			window.cancelAnimationFrame(frameId);
			frameId = null;
		}
		state.code = code;
		if (reducedMotion) {
			state.frame = 1;
			applyFrame(code);
			setStatus(code);
			renderRanking(code);
			return;
		}
		state.frame = 0;
		var startedAt = null;
		var duration = 620;
		function tick(now) {
			if (startedAt === null) {
				startedAt = now;
			}
			var progress = Math.min(1, (now - startedAt) / duration);
			/* ease-out: cepat di awal supaya perubahan kode terasa langsung. */
			var eased = 1 - Math.pow(1 - progress, 3);
			applyFrameProgress(code, eased);
			if (progress < 1) {
				frameId = window.requestAnimationFrame(tick);
			} else {
				state.frame = 1;
				frameId = null;
				applyFrame(code);
				setStatus(code);
				renderRanking(code);
			}
		}
		state.frame = 0;
		frameId = window.requestAnimationFrame(tick);
	}

	/* Selama animasi, opasitas dan radius dihitung dari keadaan sebelumnya ke
	 * keadaan baru. Angka yang dipakai tetap angka kode tujuan; yang bergerak
	 * hanya tampilannya, jadi tidak ada nilai yang dikarang di tengah jalan. */
	function applyFrameProgress(code, progress) {
		var max = maxFor(code);
		layers.forEach(function (layer) {
			var name = layer.doclincName;
			var ratio = ratioFor(valueFor(name, code), max);
			var previous = Number(layer.options.fillOpacity) || 0.16;
			var target = 0.16 + ratio * 0.72;
			layer.setStyle({ fillOpacity: previous + (target - previous) * progress });
		});
		bubbles.forEach(function (bubble) {
			var name = bubble.doclincName;
			var ratio = ratioFor(valueFor(name, code), max);
			var target = valueFor(name, code) > 0 ? 5 + 26 * Math.sqrt(ratio) : 0;
			var previous = bubble.getRadius();
			bubble.setRadius(previous + (target - previous) * progress);
			if (target > 0) {
				bubble.setStyle({ opacity: 1, fillOpacity: 0.85 });
			}
		});
	}

	function buildLayers(geojson) {
		var geoLayer = L.geoJSON(geojson, {
			style: function () {
				return { color: fillToken, weight: 1, fillColor: fillToken, fillOpacity: 0.16 };
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
				layers.push(layer);

				/* Gelembung memakai tinta panel dengan garis permukaan: warna ini
				 * kontras terhadap poligon hijau di kedua tema, sedangkan hijau tua
				 * di atas hijau (atau di tema gelap) menghilang. */
				var bubble = L.circleMarker(centerOf(feature), {
					radius: 0,
					color: surfaceToken,
					weight: 2,
					fillColor: inkToken,
					fillOpacity: 0.85,
					opacity: 1
				});
				bubble.doclincName = name;
				bubble.addTo(map);
				bubbles.push(bubble);
			}
		});
		geoLayer.addTo(map);
		map.fitBounds(geoLayer.getBounds(), { padding: [18, 18] });
		map.setMaxBounds(geoLayer.getBounds().pad(0.35));
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
			label.textContent = state.playing ? 'Jeda' : 'Putar';
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

	if (select) {
		select.addEventListener('change', function () {
			stop();
			show(String(select.value || ''));
		});
	}

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


