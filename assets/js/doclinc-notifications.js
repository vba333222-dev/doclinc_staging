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
		this.createAudio = typeof options.createAudio === 'function' ? options.createAudio : null;
		this.onNewNotifications = typeof options.onNewNotifications === 'function' ? options.onNewNotifications : null;
		this.ui = options.ui || null;
		this.client = null;
		this.subscriptionHandle = null;
		this.audioContext = null;
		this.audioElement = null;
		this.soundPrimed = false;
		this.initialSnapshotReceived = false;
		this.soundEnabled = this._readPreference();
		this.seenIds = this._readSeenIds();
		this.soundButton = null;
		this.pagehideHandler = null;
		this.audioUnlockHandler = null;
	}

	NotificationRuntime.prototype.start = function () {
		if (this.config.enabled !== true || typeof this.RealtimeClient !== 'function' || !this.ui) {
			return false;
		}
		if (this.client) { return true; }
		this._installSoundControl();
		this._bindAudioUnlock();
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
		var newNotifications = [];
		if (this.initialSnapshotReceived) {
			for (var j = 0; j < ids.length; j += 1) {
				if (this.seenIds.indexOf(ids[j]) === -1) {
					for (var itemIndex = 0; itemIndex < data.notifications.length; itemIndex += 1) {
						if (positiveId(data.notifications[itemIndex] && data.notifications[itemIndex].notification_id) === ids[j]) {
							newNotifications.push(data.notifications[itemIndex]);
							break;
						}
					}
				}
			}
		}
		this.initialSnapshotReceived = true;
		for (var k = 0; k < ids.length; k += 1) {
			this._remember(ids[k]);
		}
		this.ui.applySnapshot(data, context || {});
		if (newNotifications.length > 0) {
			this._playChime();
			if (this.onNewNotifications) {
				this.onNewNotifications(newNotifications.slice());
			}
		}
		return true;
	};

	NotificationRuntime.prototype.toggleSound = async function () {
		if (this.soundEnabled) {
			this.soundEnabled = false;
			this._stopAudioElement();
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
		this._unbindAudioUnlock();
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
		this._stopAudioElement();
		this.audioElement = null;
		this.soundPrimed = false;
	};

	NotificationRuntime.prototype._bindAudioUnlock = function () {
		if (this.audioUnlockHandler || !this.lifecycleTarget || typeof this.lifecycleTarget.addEventListener !== 'function') { return; }
		var self = this;
		this.audioUnlockHandler = function () {
			if (self.soundEnabled && !self.soundPrimed) {
				self._primeAudio().then(function (primed) {
					self.soundPrimed = primed;
					self._updateSoundControl();
				});
			}
		};
		['pointerdown', 'touchstart', 'keydown'].forEach(function (eventName) {
			self.lifecycleTarget.addEventListener(eventName, self.audioUnlockHandler, true);
		});
	};

	NotificationRuntime.prototype._unbindAudioUnlock = function () {
		if (!this.audioUnlockHandler || !this.lifecycleTarget || typeof this.lifecycleTarget.removeEventListener !== 'function') { return; }
		var self = this;
		['pointerdown', 'touchstart', 'keydown'].forEach(function (eventName) {
			self.lifecycleTarget.removeEventListener(eventName, self.audioUnlockHandler, true);
		});
		this.audioUnlockHandler = null;
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
			return true;
		}
		try {
			return this.storage.getItem(PREFERENCE_KEY) !== '0';
		} catch (error) {
			return true;
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
		var mediaPrimed = false;
		try {
			var media = this._ensureAudioElement();
			if (media && typeof media.play === 'function') {
				media.muted = true;
				var playResult = media.play();
				if (playResult && typeof playResult.then === 'function') {
					await playResult;
				}
				this._stopAudioElement();
				media.muted = false;
				mediaPrimed = true;
			}
		} catch (error) {
			this._stopAudioElement();
		}
		var contextPrimed = false;
		if (typeof this.AudioContext === 'function') {
			try {
				this.audioContext = this.audioContext || new this.AudioContext();
				if (this.audioContext.state === 'suspended' && typeof this.audioContext.resume === 'function') {
					await this.audioContext.resume();
				}
				contextPrimed = this.audioContext.state === 'running';
			} catch (error) {
				contextPrimed = false;
			}
		}
		return mediaPrimed || contextPrimed;
	};

	NotificationRuntime.prototype._playChime = function () {
		if (!this.soundEnabled || !this.soundPrimed) {
			return false;
		}
		var media = this._ensureAudioElement();
		if (media && typeof media.play === 'function') {
			try {
				media.muted = false;
				media.volume = 0.78;
				media.currentTime = 0;
				var self = this;
				var playResult = media.play();
				if (playResult && typeof playResult.catch === 'function') {
					playResult.catch(function () { self._playFallbackChime(); });
				}
				return true;
			} catch (error) {
				return this._playFallbackChime();
			}
		}
		return this._playFallbackChime();
	};

	NotificationRuntime.prototype._ensureAudioElement = function () {
		if (this.audioElement || !this.createAudio || typeof this.config.sound_url !== 'string' || this.config.sound_url.trim() === '') {
			return this.audioElement;
		}
		try {
			this.audioElement = this.createAudio(this.config.sound_url);
			if (this.audioElement) {
				this.audioElement.preload = 'auto';
				this.audioElement.volume = 0.78;
			}
		} catch (error) {
			this.audioElement = null;
		}
		return this.audioElement;
	};

	NotificationRuntime.prototype._stopAudioElement = function () {
		if (!this.audioElement) { return; }
		try {
			if (typeof this.audioElement.pause === 'function') { this.audioElement.pause(); }
			this.audioElement.currentTime = 0;
		} catch (error) {}
	};

	NotificationRuntime.prototype._playFallbackChime = function () {
		if (!this.audioContext || this.audioContext.state !== 'running') {
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
		var active = this.soundEnabled;
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
			createAudio: typeof root.Audio === 'function' ? function (url) { return new root.Audio(url); } : null,
			lifecycleTarget: root,
			onNewNotifications: function (items) {
				if (typeof root.dispatchEvent === 'function' && typeof root.CustomEvent === 'function') {
					root.dispatchEvent(new root.CustomEvent('doclinc:notifications:new', { detail: { notifications: items } }));
				}
			},
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
