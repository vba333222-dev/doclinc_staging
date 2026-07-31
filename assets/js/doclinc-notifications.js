(function (root, factory) {
	if (typeof module === 'object' && module.exports) {
		module.exports = factory();
	} else {
		root.DoclincNotifications = factory();
	}
}(typeof self !== 'undefined' ? self : this, function () {
	'use strict';

	var MAX_SEEN_IDS = 200;
	var PREFERENCE_KEY = 'doclinc.notification.sound.enabled';
	var SEEN_KEY = 'doclinc.notification.sound.seen';

	function positiveId(value) {
		var normalized = String(value === null || typeof value === 'undefined' ? '' : value);
		return /^[1-9][0-9]{0,17}$/.test(normalized) ? normalized : '';
	}

	function snapshotData(payload) {
		if (payload && payload.success === true && payload.data && !Array.isArray(payload.data)) {
			return payload.data;
		}
		if (payload && payload.status === 'success') {
			return payload;
		}
		return null;
	}

	function safeStorage(storage) {
		return storage && typeof storage.getItem === 'function' && typeof storage.setItem === 'function' ? storage : null;
	}

	function NotificationRuntime(options) {
		options = options || {};
		this.config = options.config || {};
		this.RealtimeClient = options.RealtimeClient;
		this.Centrifuge = options.Centrifuge;
		this.fetch = options.fetch;
		this.storage = safeStorage(options.storage);
		this.document = options.document || null;
		this.lifecycleTarget = options.lifecycleTarget || null;
		this.AudioContext = options.AudioContext || null;
		this.ui = options.ui || null;
		this.client = null;
		this.subscriptionHandle = null;
		this.audioContext = null;
		this.soundPrimed = false;
		this.initialSnapshotReceived = false;
		this.soundEnabled = this._readPreference();
		this.seenIds = this._readSeenIds();
		this.soundButton = null;
		this.pagehideHandler = null;
	}

	NotificationRuntime.prototype.start = function () {
		if (this.config.enabled !== true || typeof this.RealtimeClient !== 'function' || !this.ui) {
			return false;
		}
		if (this.client) { return true; }
		this._installSoundControl();
		this.client = new this.RealtimeClient({
			enabled: true,
			websocketUrl: this.config.websocket_url,
			connectionTokenUrl: this.config.connection_token_url,
			subscriptionTokenUrl: this.config.subscription_token_url,
			Centrifuge: this.Centrifuge,
			fetch: this.fetch
		});
		var self = this;
		this.subscriptionHandle = this.client.acquireSubscription(this.config.channel, {
			snapshotUrl: this.config.snapshot_url,
			pollIntervalMs: this.config.poll_interval_ms,
			onSnapshot: function (payload, context) { self.applySnapshot(payload, context); }
		});
		if (!this.client.start()) {
			this.teardown();
			return false;
		}
		this._bindPagehide();
		return true;
	};

	NotificationRuntime.prototype.applySnapshot = function (payload, context) {
		var data = snapshotData(payload);
		if (!data || !Array.isArray(data.notifications)) {
			return false;
		}
		var ids = [];
		for (var i = 0; i < data.notifications.length; i += 1) {
			var id = positiveId(data.notifications[i] && data.notifications[i].notification_id);
			if (id && ids.indexOf(id) === -1) {
				ids.push(id);
			}
		}
		var hasNew = false;
		if (this.initialSnapshotReceived) {
			for (var j = 0; j < ids.length; j += 1) {
				if (this.seenIds.indexOf(ids[j]) === -1) {
					hasNew = true;
				}
			}
		}
		this.initialSnapshotReceived = true;
		for (var k = 0; k < ids.length; k += 1) {
			this._remember(ids[k]);
		}
		this.ui.applySnapshot(data, context || {});
		if (hasNew) {
			this._playChime();
		}
		return true;
	};

	NotificationRuntime.prototype.toggleSound = async function () {
		if (this.soundEnabled && this.soundPrimed) {
			this.soundEnabled = false;
			this._writePreference();
			this._updateSoundControl();
			return false;
		}
		this.soundEnabled = true;
		this.soundPrimed = await this._primeAudio();
		if (!this.soundPrimed) {
			this.soundEnabled = false;
		}
		this._writePreference();
		this._updateSoundControl();
		return this.soundEnabled;
	};

	NotificationRuntime.prototype.teardown = function () {
		this._unbindPagehide();
		if (this.subscriptionHandle && typeof this.subscriptionHandle.release === 'function') {
			this.subscriptionHandle.release();
		}
		this.subscriptionHandle = null;
		if (this.client && typeof this.client.hasSubscriptionOwners === 'function'
			&& !this.client.hasSubscriptionOwners() && typeof this.client.teardown === 'function') {
			this.client.teardown();
		}
		this.client = null;
		if (this.audioContext && typeof this.audioContext.close === 'function') {
			this.audioContext.close();
		}
		this.audioContext = null;
		this.soundPrimed = false;
	};

	NotificationRuntime.prototype._bindPagehide = function () {
		if (this.pagehideHandler || !this.lifecycleTarget || typeof this.lifecycleTarget.addEventListener !== 'function') { return; }
		var self = this;
		this.pagehideHandler = function () { self.teardown(); };
		this.lifecycleTarget.addEventListener('pagehide', this.pagehideHandler);
	};

	NotificationRuntime.prototype._unbindPagehide = function () {
		if (!this.pagehideHandler) { return; }
		if (this.lifecycleTarget && typeof this.lifecycleTarget.removeEventListener === 'function') {
			this.lifecycleTarget.removeEventListener('pagehide', this.pagehideHandler);
		}
		this.pagehideHandler = null;
	};

	NotificationRuntime.prototype._readPreference = function () {
		if (!this.storage) {
			return false;
		}
		try {
			return this.storage.getItem(PREFERENCE_KEY) === '1';
		} catch (error) {
			return false;
		}
	};

	NotificationRuntime.prototype._writePreference = function () {
		if (!this.storage) {
			return;
		}
		try {
			this.storage.setItem(PREFERENCE_KEY, this.soundEnabled ? '1' : '0');
		} catch (error) {
			// Browser storage may be unavailable in private or restricted contexts.
		}
	};

	NotificationRuntime.prototype._readSeenIds = function () {
		if (!this.storage) {
			return [];
		}
		try {
			var parsed = JSON.parse(this.storage.getItem(SEEN_KEY) || '[]');
			if (!Array.isArray(parsed)) {
				return [];
			}
			return parsed.map(positiveId).filter(Boolean).slice(-MAX_SEEN_IDS);
		} catch (error) {
			return [];
		}
	};

	NotificationRuntime.prototype._remember = function (id) {
		if (this.seenIds.indexOf(id) !== -1) {
			return;
		}
		this.seenIds.push(id);
		this.seenIds = this.seenIds.slice(-MAX_SEEN_IDS);
		if (this.storage) {
			try {
				this.storage.setItem(SEEN_KEY, JSON.stringify(this.seenIds));
			} catch (error) {
				// Dedupe continues in memory if persistent storage is unavailable.
			}
		}
	};

	NotificationRuntime.prototype._primeAudio = async function () {
		if (typeof this.AudioContext !== 'function') {
			return false;
		}
		try {
			this.audioContext = this.audioContext || new this.AudioContext();
			if (this.audioContext.state === 'suspended' && typeof this.audioContext.resume === 'function') {
				await this.audioContext.resume();
			}
			return this.audioContext.state === 'running';
		} catch (error) {
			return false;
		}
	};

	NotificationRuntime.prototype._playChime = function () {
		if (!this.soundEnabled || !this.soundPrimed || !this.audioContext || this.audioContext.state !== 'running') {
			return false;
		}
		try {
			var now = this.audioContext.currentTime;
			var gain = this.audioContext.createGain();
			gain.gain.setValueAtTime(0.0001, now);
			gain.gain.exponentialRampToValueAtTime(0.12, now + 0.015);
			gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.42);
			gain.connect(this.audioContext.destination);
			var first = this.audioContext.createOscillator();
			var second = this.audioContext.createOscillator();
			first.type = 'sine';
			second.type = 'sine';
			first.frequency.value = 659.25;
			second.frequency.value = 880;
			first.connect(gain);
			second.connect(gain);
			first.start(now);
			first.stop(now + 0.2);
			second.start(now + 0.16);
			second.stop(now + 0.42);
			return true;
		} catch (error) {
			return false;
		}
	};

	NotificationRuntime.prototype._installSoundControl = function () {
		if (!this.document || typeof this.document.createElement !== 'function') {
			return;
		}
		var header = this.document.querySelector('#offcanvasNotif .offcanvas-header');
		if (!header || this.document.getElementById('doclincNotificationSoundToggle')) {
			return;
		}
		var self = this;
		var button = this.document.createElement('button');
		button.type = 'button';
		button.id = 'doclincNotificationSoundToggle';
		button.className = 'btn btn-sm btn-outline-secondary ms-auto me-2';
		button.addEventListener('click', function () { self.toggleSound(); });
		header.insertBefore(button, header.querySelector('.btn-close'));
		this.soundButton = button;
		this._updateSoundControl();
	};

	NotificationRuntime.prototype._updateSoundControl = function () {
		if (!this.soundButton) {
			return;
		}
		var active = this.soundEnabled && this.soundPrimed;
		this.soundButton.textContent = active ? 'Suara aktif' : 'Aktifkan suara';
		this.soundButton.setAttribute('aria-label', active ? 'Nonaktifkan suara notifikasi' : 'Aktifkan suara notifikasi');
		this.soundButton.setAttribute('aria-pressed', active ? 'true' : 'false');
	};

	function startFromDocument(root, document) {
		var node = document.getElementById('doclincNotificationRealtimeConfig');
		if (!node) {
			return null;
		}
		var config;
		try {
			config = JSON.parse(node.textContent || '{}');
		} catch (error) {
			return null;
		}
		var runtime = new NotificationRuntime({
			config: config,
			RealtimeClient: root.DoclincRealtimeClient,
			Centrifuge: root.Centrifuge,
			fetch: typeof root.fetch === 'function' ? root.fetch.bind(root) : null,
			storage: root.localStorage,
			document: document,
			AudioContext: root.AudioContext || root.webkitAudioContext,
			lifecycleTarget: root,
			ui: root.DoclincNotificationUi
		});
		runtime.start();
		if (runtime.client) {
			root.DoclincRealtimeSharedClient = runtime.client;
		}
		root.DoclincNotificationRuntime = runtime;
		return runtime;
	}

	return {
		NotificationRuntime: NotificationRuntime,
		positiveId: positiveId,
		snapshotData: snapshotData,
		startFromDocument: startFromDocument
	};
}));

(function (root) {
	'use strict';
	if (!root || !root.document || !root.DoclincNotifications) {
		return;
	}
	var start = function () { root.DoclincNotifications.startFromDocument(root, root.document); };
	if (root.document.readyState === 'loading') {
		root.document.addEventListener('DOMContentLoaded', start);
	} else {
		start();
	}
}(typeof window !== 'undefined' ? window : null));
