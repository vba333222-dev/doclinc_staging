(function(window, document) {
	'use strict';

	const config = window.DoclincIncomingCallWatcher || {};
	if (window.__doclincIncomingCallWatcherStarted || !config.enabled) {
		return;
	}
	window.__doclincIncomingCallWatcherStarted = true;

	const pollUrl = config.incomingUrl || '';
	const rejectUrl = config.rejectUrl || '';
	const chatUrl = config.chatUrl || '';
	const pollMs = Number(config.pollMs || 3000);
	let timer = null;
	let retryTimer = null;
	let failures = 0;
	let currentCall = null;
	let overlay = null;
	let callerEl = null;
	let typeEl = null;
	let contextEl = null;
	let messageEl = null;

	function safeText(value, fallback) {
		value = typeof value === 'string' ? value.trim() : '';
		return value || fallback || '';
	}

	function callTypeLabel(callType) {
		return callType === 'audio' ? 'Panggilan suara' : 'Panggilan video';
	}

	function createOverlay() {
		if (overlay) {
			return overlay;
		}

		const style = document.createElement('style');
		style.textContent = [
			'.doclinc-global-call{position:fixed;inset:0;z-index:2147483000;display:none;align-items:flex-start;justify-content:center;padding:18px;background:rgba(4,10,18,.42);font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;}',
			'.doclinc-global-call.is-visible{display:flex;}',
			'.doclinc-global-call-card{width:min(420px,100%);margin-top:max(18px,env(safe-area-inset-top));border-radius:26px;background:linear-gradient(145deg,#10233c,#07111f 62%,#0c2f27);box-shadow:0 28px 80px rgba(0,0,0,.42);color:#fff;padding:20px;border:1px solid rgba(255,255,255,.12);}',
			'.doclinc-global-call-kicker{font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#97f1d2;font-weight:700;margin-bottom:10px;}',
			'.doclinc-global-call-main{display:flex;gap:14px;align-items:center;}',
			'.doclinc-global-call-avatar{width:58px;height:58px;border-radius:50%;display:grid;place-items:center;background:rgba(255,255,255,.13);font-size:25px;font-weight:800;flex:0 0 auto;}',
			'.doclinc-global-call-name{font-size:19px;font-weight:800;line-height:1.15;margin:0 0 4px;}',
			'.doclinc-global-call-type{font-size:14px;color:#d8e7f7;margin:0;}',
			'.doclinc-global-call-context{font-size:12px;color:#aebed1;margin:5px 0 0;}',
			'.doclinc-global-call-message{min-height:18px;margin:14px 0 0;font-size:13px;color:#d6efe6;}',
			'.doclinc-global-call-actions{display:flex;gap:12px;margin-top:18px;}',
			'.doclinc-global-call-btn{border:0;border-radius:999px;min-height:48px;flex:1;font-size:15px;font-weight:800;color:#fff;cursor:pointer;}',
			'.doclinc-global-call-btn.answer{background:#18b76b;}',
			'.doclinc-global-call-btn.reject{background:#ef4444;}',
			'@media (max-width:430px){.doclinc-global-call{padding:12px}.doclinc-global-call-card{border-radius:22px;padding:18px}.doclinc-global-call-actions{gap:10px}.doclinc-global-call-btn{min-height:50px}}'
		].join('');
		document.head.appendChild(style);

		overlay = document.createElement('div');
		overlay.className = 'doclinc-global-call';
		overlay.setAttribute('role', 'dialog');
		overlay.setAttribute('aria-live', 'polite');
		overlay.innerHTML = [
			'<div class="doclinc-global-call-card">',
			'<div class="doclinc-global-call-kicker">Panggilan masuk</div>',
			'<div class="doclinc-global-call-main">',
			'<div class="doclinc-global-call-avatar" aria-hidden="true">D</div>',
			'<div>',
			'<p class="doclinc-global-call-name"></p>',
			'<p class="doclinc-global-call-type"></p>',
			'<p class="doclinc-global-call-context"></p>',
			'</div>',
			'</div>',
			'<div class="doclinc-global-call-message"></div>',
			'<div class="doclinc-global-call-actions">',
			'<button type="button" class="doclinc-global-call-btn reject">Tolak</button>',
			'<button type="button" class="doclinc-global-call-btn answer">Jawab</button>',
			'</div>',
			'</div>'
		].join('');
		document.body.appendChild(overlay);

		callerEl = overlay.querySelector('.doclinc-global-call-name');
		typeEl = overlay.querySelector('.doclinc-global-call-type');
		contextEl = overlay.querySelector('.doclinc-global-call-context');
		messageEl = overlay.querySelector('.doclinc-global-call-message');
		overlay.querySelector('.answer').addEventListener('click', answerCall);
		overlay.querySelector('.reject').addEventListener('click', rejectCall);
		return overlay;
	}

	function showIncoming(call) {
		if (!call || !call.call_id || !call.request_id) {
			return;
		}
		createOverlay();
		currentCall = call;
		callerEl.textContent = safeText(call.caller_name, 'Nakes Doclinc');
		typeEl.textContent = callTypeLabel(call.call_type);
		contextEl.textContent = safeText(call.queue_label || call.queue_code, 'Konsultasi aktif');
		messageEl.textContent = '';
		overlay.classList.add('is-visible');
	}

	function hideIncoming() {
		currentCall = null;
		if (overlay) {
			overlay.classList.remove('is-visible');
		}
	}

	function postForm(url, data) {
		const formData = new FormData();
		Object.keys(data || {}).forEach(function(key) {
			formData.append(key, data[key]);
		});
		return fetch(url, {
			method: 'POST',
			body: formData,
			credentials: 'same-origin',
			headers: {
				'X-Requested-With': 'XMLHttpRequest'
			}
		}).then(function(response) {
			return response.json().catch(function() {
				return {};
			});
		});
	}

	function answerCall() {
		if (!currentCall || !currentCall.call_id || !currentCall.request_id || !chatUrl) {
			return;
		}
		const target = new URL(chatUrl, window.location.origin);
		target.searchParams.set('request_id', currentCall.request_id);
		target.searchParams.set('answer_call', currentCall.call_id);
		window.location.href = target.toString();
	}

	function rejectCall() {
		const callId = currentCall && currentCall.call_id;
		hideIncoming();
		if (!callId || !rejectUrl) {
			return;
		}
		postForm(rejectUrl, {
			call_id: callId
		}).catch(function() {});
	}

	function poll() {
		if (!pollUrl) {
			return;
		}
		if (document.visibilityState && document.visibilityState !== 'visible') {
			return;
		}
		fetch(pollUrl, {
			credentials: 'same-origin',
			headers: {
				'X-Requested-With': 'XMLHttpRequest'
			}
		}).then(function(response) {
			if (!response.ok) {
				throw new Error('poll failed');
			}
			return response.json().catch(function() {
				return {};
			});
		}).then(function(response) {
			failures = 0;
			if (response && response.success && response.has_incoming) {
				showIncoming(response);
			} else {
				hideIncoming();
			}
		}).catch(function() {
			failures += 1;
			if (failures >= 5) {
				stop();
				retryTimer = window.setTimeout(function() {
					failures = 0;
					start();
				}, 30000);
			}
		});
	}

	function start() {
		if (!pollUrl || timer) {
			return;
		}
		poll();
		timer = window.setInterval(poll, pollMs);
	}

	function stop() {
		if (timer) {
			window.clearInterval(timer);
		}
		timer = null;
	}

	document.addEventListener('visibilitychange', function() {
		if (document.visibilityState === 'visible') {
			poll();
		}
	});
	window.addEventListener('beforeunload', function() {
		stop();
		if (retryTimer) {
			window.clearTimeout(retryTimer);
		}
	});

	// TODO: true background incoming call needs native app push notification/FCM integration.
	start();
})(window, document);
