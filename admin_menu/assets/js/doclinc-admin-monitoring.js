/**
 * Dasbor Monitoring Layanan Kesehatan - grafik dan perilaku filter.
 *
 * Memakai Chart.js 3.6.2 yang sudah dimuat header panel admin. Berkas ini tidak
 * memuat library apa pun dan tidak menambah dependensi.
 *
 * Aturan yang dipegang:
 *   - Grafik adalah pelengkap. Tabel di bawah setiap grafik memuat angka yang
 *     sama dan tetap terbaca tanpa JavaScript.
 *   - Warna diambil dari token CSS (`--dl-ref-*`) lewat getComputedStyle, jadi
 *     tidak ada hex di berkas ini dan tema gelap ikut terbaca.
 *   - prefers-reduced-motion dihormati: animasi grafik dimatikan.
 *   - Tidak ada data pasien yang ditulis ke konsol.
 *   - Filter tetap bekerja tanpa JavaScript: "Terapkan filter" adalah tombol
 *     submit biasa.
 */
(function () {
  'use strict';

  var bootstrapElement = document.getElementById('doclincMonitoringBootstrap');
  var config = {};
  if (bootstrapElement) {
    try {
      config = JSON.parse(bootstrapElement.textContent || '{}');
    } catch (error) {
      config = {};
    }
  }

  function token(name) {
    var value = window.getComputedStyle(document.documentElement).getPropertyValue(name);
    return (value || '').trim();
  }

  /* Token dibaca dari dalam `.dl-mon` lebih dulu supaya pemetaan tema gelap di
     berkas CSS ikut terpakai. */
  function rootToken(name) {
    var scope = document.querySelector('.dl-mon') || document.documentElement;
    var value = window.getComputedStyle(scope).getPropertyValue(name);
    return (value || '').trim();
  }

  function reducedMotion() {
    return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  }

  function hasSeries(values) {
    return Object.prototype.toString.call(values) === '[object Array]' && values.length > 0;
  }

  function initFilter() {
    var form = document.getElementById('dlMonFilter');
    if (!form) {
      return;
    }

    var period = form.querySelector('[data-dl-mon-periode]');
    var customInputs = form.querySelectorAll('[data-dl-mon-custom]');

    /* Mengubah daftar pilihan langsung menerapkan filter; tanpa JavaScript pun
       filter tetap dapat diterapkan lewat tombol submit. */
    if (period) {
      period.addEventListener('change', function () {
        form.submit();
      });
    }

    /* Mengisi tanggal menandakan pengguna memang ingin rentang khusus, jadi
       pilihannya dipindahkan tanpa perlu diatur manual lebih dulu. */
    Array.prototype.forEach.call(customInputs, function (input) {
      input.addEventListener('change', function () {
        if (period && period.value !== 'kustom') {
          period.value = 'kustom';
        }
      });
    });
  }

  function drawTrendChart() {
    var canvas = document.getElementById(config.trendCanvasId || '');
    if (!canvas || typeof window.Chart !== 'function') {
      return;
    }
    if (!hasSeries(config.labels) || !hasSeries(config.masuk)) {
      return;
    }
    if (canvas.dataset.doclincChartReady === '1') {
      return;
    }
    canvas.dataset.doclincChartReady = '1';

    var ink = rootToken('--dl-ref-ink') || token('--dl-ref-ink');
    var muted = rootToken('--dl-ref-muted') || token('--dl-ref-muted');
    var grid = rootToken('--dl-ref-line') || token('--dl-ref-line');
    var incoming = token('--dl-ref-green-600') || ink;
    var completed = token('--dl-ref-green-500') || ink;
    var still = reducedMotion();

    new window.Chart(canvas.getContext('2d'), {
      type: 'line',
      data: {
        labels: config.labels,
        datasets: [
          {
            label: 'Permintaan masuk',
            data: config.masuk,
            borderColor: incoming,
            backgroundColor: incoming,
            borderWidth: 2,
            pointRadius: 2,
            pointHoverRadius: 4,
            tension: 0.2
          },
          {
            label: 'Layanan selesai',
            data: config.selesai,
            borderColor: completed,
            backgroundColor: completed,
            borderWidth: 2,
            borderDash: [5, 4],
            pointRadius: 2,
            pointHoverRadius: 4,
            tension: 0.2
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: still ? false : { duration: 300 },
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: {
            display: true,
            position: 'top',
            align: 'start',
            labels: {
              color: ink,
              boxWidth: 14,
              boxHeight: 2,
              font: { size: 12 }
            }
          },
          tooltip: {
            callbacks: {
              title: function (items) {
                var index = items.length ? items[0].dataIndex : 0;
                var range = (config.rangeLabels || [])[index];
                return range || (config.labels || [])[index] || '';
              },
              label: function (item) {
                return item.dataset.label + ': ' + item.formattedValue;
              }
            }
          }
        },
        scales: {
          x: {
            grid: { display: false },
            ticks: { color: muted, maxRotation: 0, autoSkip: true, font: { size: 11 } },
            border: { color: grid }
          },
          y: {
            beginAtZero: true,
            grid: { color: grid },
            ticks: { color: muted, precision: 0, font: { size: 11 } },
            border: { display: false }
          }
        }
      }
    });
  }

  function drawFacilityChart() {
    var canvas = document.getElementById(config.facilityCanvasId || '');
    if (!canvas || typeof window.Chart !== 'function') {
      return;
    }
    var facilities = hasSeries(config.facilities) ? config.facilities : [];
    if (!facilities.length) {
      return;
    }
    if (canvas.dataset.doclincChartReady === '1') {
      return;
    }
    canvas.dataset.doclincChartReady = '1';

    var ink = rootToken('--dl-ref-ink') || token('--dl-ref-ink');
    var muted = rootToken('--dl-ref-muted') || token('--dl-ref-muted');
    var grid = rootToken('--dl-ref-line') || token('--dl-ref-line');
    var barColor = token('--dl-ref-green-600') || ink;
    var still = reducedMotion();
    var labels = [];
    var values = [];

    /* Urutan dikirim menurun karena Chart.js 3 pada sumbu kategori horizontal
       menaruh kategori pertama di paling atas - sama seperti urutan tabel. */
    facilities.forEach(function (row) {
      labels.push(row.label);
      values.push(row.value);
    });

    new window.Chart(canvas.getContext('2d'), {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [
          {
            label: 'Permintaan masuk',
            data: values,
            backgroundColor: barColor,
            borderWidth: 0,
            maxBarThickness: 18
          }
        ]
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        animation: still ? false : { duration: 300 },
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: function (item) {
                return 'Permintaan masuk: ' + item.formattedValue;
              }
            }
          }
        },
        scales: {
          x: {
            beginAtZero: true,
            grid: { color: grid },
            ticks: { color: muted, precision: 0, font: { size: 11 } },
            border: { display: false }
          },
          y: {
            grid: { display: false },
            ticks: { color: ink, autoSkip: false, font: { size: 11 } },
            border: { color: grid }
          }
        }
      }
    });
  }

  /* --------------------------------------------------------------------------
     Pembaruan berkala.

     Hanya nilai yang cepat berubah yang diambil: beban berjalan, jumlah
     pengecualian, dan kesegaran data. Agregat periode - tren, perbandingan
     fasilitas, mutu data - tidak ikut disegarkan, karena angka itu berubah
     lambat dan mengirimnya setiap 30 detik hanya menambah beban tanpa
     menambah keputusan.

     Sidik jari keadaan dibandingkan lebih dulu, jadi halaman tidak disentuh
     sama sekali bila tidak ada yang berubah: tidak ada kedip, tidak ada gerak,
     tidak ada pekerjaan sia-sia.
     -------------------------------------------------------------------------- */
  function initLive() {
    var status = document.querySelector('[data-dl-mon-live-status]');
    var url = typeof config.liveUrl === 'string' ? config.liveUrl : '';
    if (!url || typeof window.fetch !== 'function') {
      return;
    }

    var interval = parseInt(config.liveIntervalMs, 10);
    if (!(interval > 0)) {
      interval = 30000;
    }
    if (config.puskesmas) {
      url += (url.indexOf('?') === -1 ? '?' : '&') + 'puskesmas=' + encodeURIComponent(config.puskesmas);
    }

    var timer = null;
    var lastToken = '';
    var inFlight = false;

    function pad(value) {
      return (value < 10 ? '0' : '') + value;
    }

    function clock() {
      var now = new Date();
      return pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds());
    }

    /* Angka ribuan memakai pemisah yang sama dengan number_format() di sisi
       server, supaya nilai hasil penyegaran tidak tampak berbeda formatnya. */
    function group(value) {
      return String(value).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    function stamp(stale) {
      Array.prototype.forEach.call(document.querySelectorAll('[data-dl-mon-live="jam"]'), function (node) {
        node.textContent = clock();
      });
      if (status) {
        status.classList.toggle('is-stale', stale === true);
      }
    }

    function apply(data) {
      var beban = data.beban || {};
      var eksepsi = data.eksepsi || {};
      var values = {
        pending: beban.pending,
        accepted: beban.accepted,
        aktif: beban.aktif,
        kumulatif_selesai: beban.kumulatif_selesai,
        kumulatif_batal: beban.kumulatif_batal,
        kumulatif_masuk: beban.kumulatif_masuk,
        eksepsi_kritis: eksepsi.kritis,
        eksepsi_peringatan: eksepsi.peringatan,
        eksepsi_informasi: eksepsi.informasi,
        eksepsi_total: eksepsi.total
      };
      Object.keys(values).forEach(function (key) {
        if (typeof values[key] !== 'number') {
          return;
        }
        Array.prototype.forEach.call(document.querySelectorAll('[data-dl-mon-live="' + key + '"]'), function (node) {
          node.textContent = group(values[key]);
        });
      });
    }

    function tick() {
      if (inFlight || document.visibilityState === 'hidden') {
        return;
      }
      inFlight = true;
      window.fetch(url, {
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { Accept: 'application/json' }
      })
        .then(function (response) {
          if (!response.ok) {
            throw new Error('http');
          }
          return response.json();
        })
        .then(function (payload) {
          if (!payload || payload.success !== true || !payload.data) {
            throw new Error('payload');
          }
          if (payload.data.sumber_tersedia !== true) {
            return;
          }
          if (payload.data.sidik !== lastToken) {
            lastToken = payload.data.sidik;
            apply(payload.data);
          }
          stamp(false);
        })
        .catch(function () {
          /* Gagal menyegarkan bukan alasan menyembunyikan angka lama. Yang
             dilakukan hanya menandai bahwa pemeriksaan terakhir tidak berhasil,
             supaya pembaca tidak mengira angkanya baru. */
          stamp(true);
        })
        .then(function () {
          inFlight = false;
        });
    }

    function start() {
      if (timer === null) {
        timer = window.setInterval(tick, interval);
      }
    }

    function stop() {
      if (timer !== null) {
        window.clearInterval(timer);
        timer = null;
      }
    }

    /* Tab yang tidak terlihat tidak perlu menyegarkan apa pun, dan browser
       memang menahan timer di latar belakang. Menyegarkan tepat saat tab kembali
       membuat angkanya benar tanpa membebani server selama tidak ada yang
       menonton. */
    document.addEventListener('visibilitychange', function () {
      if (document.visibilityState === 'hidden') {
        stop();
        return;
      }
      tick();
      start();
    });

    stamp(false);
    start();
  }

  /* --------------------------------------------------------------------------
     Penandaan tindak lanjut.

     Formulirnya sudah berfungsi tanpa JavaScript; yang ditambahkan di sini
     hanya menghindari muat ulang halaman. Setelah berhasil, barisnya
     diperbarui di tempat - tanpa memuat ulang, tanpa kehilangan posisi gulir.
     -------------------------------------------------------------------------- */
  function initFollowup() {
    var forms = document.querySelectorAll('[data-dl-mon-followup]');
    if (!forms.length || typeof window.fetch !== 'function') {
      return;
    }

    function csrfField() {
      var name = document.querySelector('meta[name="csrf-token-name"]');
      var hash = document.querySelector('meta[name="csrf-token-hash"]');
      if (!name || !hash) {
        return null;
      }
      return { name: name.getAttribute('content'), hash: hash.getAttribute('content') };
    }

    function setState(form, marked) {
      var button = form.querySelector('button[type="submit"]');
      var icon = button ? button.querySelector('i') : null;
      var label = 'Tandai sudah ditindaklanjuti';

      form.dataset.dlMonFollowup = marked ? 'unmark' : 'mark';
      form.setAttribute('action', (marked ? config.followupUnmarkUrl : config.followupMarkUrl) || form.getAttribute('action'));
      if (!button) {
        return;
      }
      button.classList.toggle('btn-outline-secondary', marked);
      button.classList.toggle('btn-outline-primary', !marked);
      if (icon) {
        icon.classList.toggle('fa-undo', marked);
        icon.classList.toggle('fa-check', !marked);
      }
      /* Label tombol adalah simpul teks terakhir di dalamnya. */
      Array.prototype.forEach.call(button.childNodes, function (node) {
        if (node.nodeType === 3 && node.nodeValue.replace(/\s/g, '') !== '') {
          node.nodeValue = marked ? ' Batalkan tanda' : ' ' + label;
        }
      });
    }

    Array.prototype.forEach.call(forms, function (form) {
      form.addEventListener('submit', function (event) {
        if (form.dataset.dlMonFollowupBusy === '1') {
          event.preventDefault();
          return;
        }
        event.preventDefault();
        form.dataset.dlMonFollowupBusy = '1';

        var button = form.querySelector('button[type="submit"]');
        if (button) {
          button.disabled = true;
        }

        var body = new window.FormData(form);
        var csrf = csrfField();
        if (csrf && !body.has(csrf.name)) {
          body.append(csrf.name, csrf.hash);
        }

        window.fetch(form.getAttribute('action'), {
          method: 'POST',
          body: body,
          credentials: 'same-origin',
          headers: { Accept: 'application/json' }
        })
          .then(function (response) {
            return response.json().catch(function () { return null; });
          })
          .then(function (payload) {
            if (!payload || payload.success !== true) {
              throw new Error('tidak_berhasil');
            }
            setState(form, form.dataset.dlMonFollowup === 'mark');
          })
          .catch(function () {
            /* Dibiarkan kembali ke keadaan semula supaya formulir biasa tetap
               menjadi jalan keluar bila permintaan gagal. */
            if (button) {
              button.disabled = false;
            }
          })
          .then(function () {
            form.dataset.dlMonFollowupBusy = '0';
          });
      });
    });
  }

  function init() {
    drawTrendChart();
    drawFacilityChart();
    initFilter();
    initLive();
    initFollowup();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  window.doclincMonitoringInitFilter = initFilter;
  window.doclincMonitoringInitLive = initLive;
})();
