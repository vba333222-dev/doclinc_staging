'use strict';
/* Uji perilaku penangan sesi berakhir (assets/js/doclinc-session-guard.js).
 *
 * Diuji tanpa peramban: pabriknya disuntik root tiruan, jadi yang diperiksa
 * adalah perilaku yang dilihat pengguna - pesan apa yang muncul, ke mana
 * halaman dialihkan, dan apakah kanal realtime benar-benar ditutup. */

const path = require('path');
const factory = require(path.join(__dirname, '..', '..', '..', 'assets', 'js', 'doclinc-session-guard.js'));

let passed = 0;
let failed = 0;
function expect(condition, label) {
	if (condition) { passed++; process.stdout.write('PASS ' + label + '\n'); return; }
	failed++; process.stderr.write('FAIL ' + label + '\n');
}
function flush() { return new Promise(resolve => setImmediate(resolve)); }

function makeRoot(overrides) {
	overrides = overrides || {};
	const root = {
		__teardown: [],
		__timers: [],
		__assigned: null,
		__alerted: null,
		__swal: null,
		__nodes: {},
		location: { pathname: '/home_nakes', assign: function (url) { root.__assigned = url; } },
		document: { getElementById: function (id) { return root.__nodes[id] || null; } },
		setTimeout: function (fn, ms) { root.__timers.push({ fn: fn, ms: ms }); return root.__timers.length; },
		alert: function (message) { root.__alerted = message; },
		/* SweetAlert2 diekspor sebagai fungsi yang juga punya .fire. */
		Swal: Object.assign(function () {}, {
			fire: function (options) {
				root.__swal = options;
				return { then: function (callback) { callback(); } };
			}
		})
	};
	Object.keys(overrides).forEach(function (key) { root[key] = overrides[key]; });
	root.__loginUrl = root.__loginUrl || 'http://localhost:8100/login';
	root.__nodes['doclincSessionGuardConfig'] = { textContent: JSON.stringify({ login_url: root.__loginUrl }) };
	return root;
}

function runtimeSpy(root, name) {
	const holder = { calls: 0 };
	root[name] = { teardown: function () { holder.calls++; } };
	return holder;
}

/* --- T1: payload lain tidak boleh memicu apa pun -------------------------- */
{
	const root = makeRoot();
	const notification = runtimeSpy(root, 'DoclincNotificationRuntime');
	const guard = factory(root);
	guard.handle({ success: false, safe_error_code: 'access_denied' });
	expect(notification.calls === 0 && root.__assigned === null && root.__swal === null, 'T1 payload lain diabaikan');
}

/* --- T2: sesi berakhir karena perangkat lain ------------------------------ */
{
	const root = makeRoot();
	const notification = runtimeSpy(root, 'DoclincNotificationRuntime');
	const request = runtimeSpy(root, 'DoclincRequestRuntime');
	const shared = runtimeSpy(root, 'DoclincRealtimeSharedClient');
	const guard = factory(root);
	guard.handle({ safe_error_code: 'session_expired', reason: 'other_device' });
	expect(notification.calls === 1 && request.calls === 1 && shared.calls === 1, 'T2 kanal realtime & polling ditutup');
	expect(root.__swal && root.__swal.icon === 'warning', 'T2 modal peringatan ditampilkan');
	expect(root.__swal && /perangkat lain/.test(root.__swal.text), 'T2 pesan menyebut perangkat lain');
	expect(root.__swal && /ganti kata sandi/.test(root.__swal.text), 'T2 pesan menyarankan ganti kata sandi');
	expect(root.__assigned === 'http://localhost:8100/login?session=expired&reason=other_device', 'T2 dialihkan ke login dengan sebab');
}

/* --- T3: tidak ditangani dua kali ---------------------------------------- */
{
	const root = makeRoot();
	const notification = runtimeSpy(root, 'DoclincNotificationRuntime');
	const guard = factory(root);
	guard.handle({ safe_error_code: 'session_expired', reason: 'other_device' });
	guard.handle({ safe_error_code: 'session_expired', reason: 'other_device' });
	expect(notification.calls === 1, 'T3 penanganan tidak berulang');
}

/* --- T4: sebab tidak dikenal tetap punya pesan --------------------------- */
{
	const root = makeRoot();

/* --- T5: jalur jQuery ---------------------------------------------------- */
{
	const handlers = [];
	const root = makeRoot({
		jQuery: function () {
			return { ajaxError: function (fn) { handlers.push(fn); } };
		}
	});
	const notification = runtimeSpy(root, 'DoclincNotificationRuntime');
	factory(root);
	expect(handlers.length === 1, 'T5 handler ajaxError terpasang');
	handlers[0]({}, { status: 200, responseText: '{"ok":true}' });
	expect(notification.calls === 0, 'T5 respons 200 diabaikan');
	handlers[0]({}, { status: 401, responseText: '{"success":false,"safe_error_code":"session_expired","reason":"other_device"}' });
	expect(notification.calls === 1 && root.__assigned !== null, 'T5 respons 401 ditangani');
}

/* --- T6/T7: jalur fetch, lalu T8-T10 ------------------------------------ */
{
	const bodies = {
		'/revoked': { status: 401, body: { safe_error_code: 'session_expired', reason: 'other_device' } },
		'/ok': { status: 200, body: { ok: true } }
	};
	const root = makeRoot({
		fetch: function (url) {
			const entry = bodies[url];
			return Promise.resolve({
				status: entry.status,
				clone: function () { return { text: function () { return Promise.resolve(JSON.stringify(entry.body)); } }; }
			});
		}
	});
	const notification = runtimeSpy(root, 'DoclincNotificationRuntime');
	factory(root);
	(async function () {
		const revoked = await root.fetch('/revoked');
		await flush();
		expect(revoked.status === 401, 'T6 respons tetap utuh untuk pemanggil');
		expect(notification.calls === 1 && root.__assigned !== null, 'T6 fetch 401 ditangani');
		const ok = await root.fetch('/ok');
		await flush();
		expect(ok.status === 200 && notification.calls === 1, 'T7 fetch 200 tidak mengubah apa pun');

		const custom = makeRoot({ __loginUrl: 'http://contoh.test/doclinc/login' });
		factory(custom).handle({ safe_error_code: 'session_expired', reason: 'signed_out' });
		expect(custom.__assigned === 'http://contoh.test/doclinc/login?session=expired&reason=signed_out', 'T8 login_url mengikuti konfigurasi');
		expect(custom.__swal && /sudah diakhiri/.test(custom.__swal.text), 'T8 pesan keluar sendiri');

		const broken = makeRoot();
		broken.DoclincRealtimeSharedClient = { teardown: function () { throw new Error('kanal_rusak'); } };
		factory(broken).handle({ safe_error_code: 'session_expired', reason: 'other_device' });
		expect(broken.__assigned !== null, 'T9 teardown gagal tidak menghalangi pengalihan');

		const plain = makeRoot({ Swal: undefined });
		factory(plain).handle({ safe_error_code: 'session_expired', reason: 'other_device' });
		expect(plain.__alerted !== null && plain.__assigned !== null, 'T10 fallback alert + pengalihan');

		process.stdout.write('SESSION_GUARD_CLIENT_PASSED=' + passed + '\nSESSION_GUARD_CLIENT_FAILED=' + failed + '\n');
		process.exit(failed === 0 ? 0 : 1);
	})();
}

	const guard = factory(root);
	guard.handle({ safe_error_code: 'session_expired' });
	expect(root.__swal && /Sesi Anda berakhir/.test(root.__swal.text), 'T4 pesan generik dipakai');
	expect(root.__assigned === 'http://localhost:8100/login?session=expired&reason=expired', 'T4 sebab default = expired');
}
