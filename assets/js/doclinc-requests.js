(function (root, factory) {
	if (typeof module === 'object' && module.exports) {
		module.exports = factory();
	} else {
		root.DoclincRequests = factory();
	}
}(typeof self !== 'undefined' ? self : this, function () {
	'use strict';

	function RequestRuntime(options) {
		options = options || {};
		this.config = options.config || {};
		this.RealtimeClient = options.RealtimeClient;
		this.Centrifuge = options.Centrifuge;
		this.fetch = options.fetch;
		this.sharedClient = options.sharedClient || null;
		this.lifecycleTarget = options.lifecycleTarget || null;
		this.client = null;
		this.ownsClient = false;
		this.observerId = null;
		this.subscriptionHandle = null;
		this.timer = null;
		this.refreshing = false;
		this.refreshPending = false;
		this.fingerprint = '';
		this.destroyed = false;
		this.pagehideHandler = null;
		this.setTimer = options.setTimeout || setTimeout;
		this.clearTimer = options.clearTimeout || clearTimeout;
		this.onChange = options.onChange || function () {
			if (root && root.location && typeof root.location.reload === 'function') {
				root.location.reload();
			}
		};
	}

	RequestRuntime.prototype.start = function () {
		if (this.config.enabled !== true || typeof this.RealtimeClient !== 'function' || typeof this.fetch !== 'function') {
			return false;
		}
		if (this.client) { return true; }
		var self = this;
		if (this.sharedClient && typeof this.sharedClient.observe === 'function'
			&& typeof this.sharedClient.acquireSubscription === 'function') {
			this.client = this.sharedClient;
			try {
				this.subscriptionHandle = this.client.acquireSubscription(this.config.channel);
				this.observerId = this.client.observe(this.config.channel, {
				invalidation: function (event) {
					if (event && typeof event.event_type === 'string' && event.event_type.indexOf('request.') === 0) {
						self.refresh('invalidation');
					}
				},
				recovery: function () { self.refresh('recovery_unavailable'); },
				connected: function () { self._stopPolling(); },
				disconnected: function () { self._schedulePoll(0); }
				});
			} catch (error) {
				if (this.subscriptionHandle && typeof this.subscriptionHandle.release === 'function') {
					this.subscriptionHandle.release();
				}
				this.subscriptionHandle = null;
				this.client = null;
				return false;
			}
			this.refresh('initial');
			if (!this.client.connected) {
				this._schedulePoll(this.config.poll_interval_ms);
			}
			this._bindPagehide();
			return true;
		}
		this.client = new this.RealtimeClient({
			enabled: true,
			websocketUrl: this.config.websocket_url,
			connectionTokenUrl: this.config.connection_token_url,
			subscriptionTokenUrl: this.config.subscription_token_url,
			Centrifuge: this.Centrifuge,
			fetch: this.fetch
		});
		this.ownsClient = true;
		try {
			this.subscriptionHandle = this.client.acquireSubscription(this.config.channel);
			this.observerId = this.client.observe(this.config.channel, {
				invalidation: function (event) {
					if (event && typeof event.event_type === 'string' && event.event_type.indexOf('request.') === 0) {
						self.refresh('invalidation');
					}
				},
				recovery: function () { self.refresh('recovery_unavailable'); },
				connected: function () { self._stopPolling(); },
				disconnected: function () { self._schedulePoll(0); }
			});
			if (!this.client.start()) { throw new Error('client_start_failed'); }
			this.refresh('initial');
			this._bindPagehide();
			return true;
		} catch (error) {
			this.teardown();
			return false;
		}
	};

	RequestRuntime.prototype.refresh = async function () {
		if (this.destroyed) { return; }
		if (this.refreshing) { this.refreshPending = true; return; }
		this.refreshing = true;
		try {
			var response = await this.fetch(this.config.snapshot_url, {
				method: 'GET', credentials: 'same-origin', cache: 'no-store', headers: { 'Accept': 'application/json' }
			});
			if (response.ok) {
				var payload = await response.json();
				if (!this.destroyed) { this.applySnapshot(payload); }
			}
		} catch (error) {
			// Polling remains available after transient snapshot failures.
		} finally {
			this.refreshing = false;
			if (this.refreshPending && !this.destroyed) {
				this.refreshPending = false;
				this.refresh();
			}
		}
	};

	RequestRuntime.prototype.applySnapshot = function (payload) {
		var data = payload && payload.success === true && payload.data && !Array.isArray(payload.data) ? payload.data : null;
		if (!data || typeof data.fingerprint !== 'string' || !Array.isArray(data.requests)) { return false; }
		if (this.fingerprint === '') { this.fingerprint = data.fingerprint; return true; }
		if (this.fingerprint !== data.fingerprint) {
			this.fingerprint = data.fingerprint;
			this.onChange(data);
		}
		return true;
	};

	RequestRuntime.prototype._schedulePoll = function (delay) {
		if (this.destroyed || this.timer !== null || (this.client && this.client.connected)) { return; }
		var self = this;
		this.timer = this.setTimer(async function () {
			self.timer = null;
			await self.refresh('poll');
			self._schedulePoll(self.config.poll_interval_ms);
		}, Number(delay) || 0);
	};

	RequestRuntime.prototype._stopPolling = function () {
		if (this.timer !== null) { this.clearTimer(this.timer); this.timer = null; }
	};

	RequestRuntime.prototype.teardown = function () {
		this._unbindPagehide();
		this.destroyed = true;
		this._stopPolling();
		if (this.client && this.observerId !== null && typeof this.client.unobserve === 'function') {
			this.client.unobserve(this.observerId);
		}
		this.observerId = null;
		if (this.subscriptionHandle && typeof this.subscriptionHandle.release === 'function') {
			this.subscriptionHandle.release();
		}
		this.subscriptionHandle = null;
		if (this.client && typeof this.client.hasSubscriptionOwners === 'function'
			&& !this.client.hasSubscriptionOwners() && typeof this.client.teardown === 'function') { this.client.teardown(); }
		this.client = null;
	};

	RequestRuntime.prototype._bindPagehide = function () {
		if (this.pagehideHandler || !this.lifecycleTarget || typeof this.lifecycleTarget.addEventListener !== 'function') { return; }
		var self = this;
		this.pagehideHandler = function () { self.teardown(); };
		this.lifecycleTarget.addEventListener('pagehide', this.pagehideHandler);
	};

	RequestRuntime.prototype._unbindPagehide = function () {
		if (!this.pagehideHandler) { return; }
		if (this.lifecycleTarget && typeof this.lifecycleTarget.removeEventListener === 'function') {
			this.lifecycleTarget.removeEventListener('pagehide', this.pagehideHandler);
		}
		this.pagehideHandler = null;
	};

	function startFromDocument(root, document) {
		var node = document.getElementById('doclincRequestRealtimeConfig');
		if (!node) { return null; }
		var config;
		try { config = JSON.parse(node.textContent || '{}'); } catch (error) { return null; }
		var runtime = new RequestRuntime({
			config: config,
			RealtimeClient: root.DoclincRealtimeClient,
			Centrifuge: root.Centrifuge,
			fetch: typeof root.fetch === 'function' ? root.fetch.bind(root) : null,
			sharedClient: root.DoclincRealtimeSharedClient || null,
			lifecycleTarget: root
		});
		runtime.start();
		if (runtime.client && !root.DoclincRealtimeSharedClient) { root.DoclincRealtimeSharedClient = runtime.client; }
		root.DoclincRequestRuntime = runtime;
		return runtime;
	}

	return { RequestRuntime: RequestRuntime, startFromDocument: startFromDocument };
}));

(function (root) {
	'use strict';
	if (!root || !root.document || !root.DoclincRequests) { return; }
	var start = function () { root.DoclincRequests.startFromDocument(root, root.document); };
	if (root.document.readyState === 'loading') { root.document.addEventListener('DOMContentLoaded', start); } else { start(); }
}(typeof window !== 'undefined' ? window : null));
