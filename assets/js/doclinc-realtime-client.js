(function (root, factory) {
	if (typeof module === 'object' && module.exports) {
		module.exports = factory(root);
	} else {
		root.DoclincRealtimeClient = factory(root);
	}
}(typeof self !== 'undefined' ? self : this, function (root) {
	'use strict';

	var DEFAULT_POLL_INTERVAL = 30000;
	var MIN_POLL_INTERVAL = 5000;
	var MAX_POLL_INTERVAL = 120000;
	var MAX_EVENT_IDS = 500;

	function safeRelativeUrl(value) {
		return typeof value === 'string'
			&& value.length > 0
			&& value.length <= 500
			&& value.charAt(0) === '/'
			&& value.slice(0, 2) !== '//'
			&& value.indexOf('\\') === -1
			&& !/[\x00-\x20\x7f]/.test(value);
	}

	function validChannel(value) {
		if (typeof value !== 'string' || value.length > 128 || /[\x00-\x20\x7f]/.test(value)) {
			return false;
		}
		if (/^(user|request):[1-9][0-9]{0,17}$/.test(value)) {
			return true;
		}
		var match = /^puskesmas:([A-Za-z0-9][A-Za-z0-9._-]{0,99}):ops$/.exec(value);
		return !!match && match[1].toUpperCase() !== 'DEFAULT' && match[1].indexOf('..') === -1;
	}

	function RealtimeClient(options) {
		options = options || {};
		this.enabled = options.enabled === true;
		this.websocketUrl = typeof options.websocketUrl === 'string' ? options.websocketUrl : '';
		this.connectionTokenUrl = options.connectionTokenUrl || '/realtime/connection-token';
		this.subscriptionTokenUrl = options.subscriptionTokenUrl || '/realtime/subscription-token';
		this.fetch = options.fetch || (typeof fetch === 'function' ? fetch.bind(root) : null);
		this.Centrifuge = options.Centrifuge || (root && root.Centrifuge);
		this.UnauthorizedError = options.UnauthorizedError || (root && root.UnauthorizedError);
		this.setTimer = options.setTimeout || setTimeout;
		this.clearTimer = options.clearTimeout || clearTimeout;
		this.onError = typeof options.onError === 'function' ? options.onError : function () {};
		this.client = null;
		this.connected = false;
		this.started = false;
		this.destroyed = false;
		this.entries = new Map();
		this.eventIds = new Map();
	}

	RealtimeClient.prototype.start = function () {
		if (this.destroyed || this.started) {
			return false;
		}
		this.started = true;
		if (!this.enabled) {
			return false;
		}
		this._startPolling();
		if (!this.fetch || typeof this.Centrifuge !== 'function'
			|| !safeRelativeUrl(this.connectionTokenUrl)
			|| !safeRelativeUrl(this.subscriptionTokenUrl)
			|| !/^wss:\/\/[^\s/?#]+(?::[0-9]{1,5})?\/connection\/websocket$/.test(this.websocketUrl)) {
			this._safeError('client_configuration_invalid');
			return false;
		}

		var self = this;
		this.client = new this.Centrifuge(this.websocketUrl, {
			getToken: function () { return self._connectionToken(); },
			minReconnectDelay: 500,
			maxReconnectDelay: 10000
		});
		if (typeof this.client.on === 'function') {
			this.client.on('connected', function () {
				self.connected = true;
				self._stopPolling();
			});
			this.client.on('disconnected', function () {
				self.connected = false;
				self._startPolling();
			});
		}
		this.entries.forEach(function (entry) { self._attach(entry); });
		this.client.connect();
		return true;
	};

	RealtimeClient.prototype.subscribe = function (channel, options) {
		options = options || {};
		if (this.destroyed || !validChannel(channel) || !safeRelativeUrl(options.snapshotUrl)) {
			throw new Error('subscription_configuration_invalid');
		}
		if (this.entries.has(channel)) {
			return this.entries.get(channel).subscription;
		}
		var interval = Number(options.pollIntervalMs || DEFAULT_POLL_INTERVAL);
		if (!Number.isFinite(interval) || interval < MIN_POLL_INTERVAL || interval > MAX_POLL_INTERVAL) {
			throw new Error('poll_interval_invalid');
		}
		var entry = {
			channel: channel,
			snapshotUrl: options.snapshotUrl,
			onSnapshot: typeof options.onSnapshot === 'function' ? options.onSnapshot : function () {},
			pollIntervalMs: interval,
			timer: null,
			refreshing: false,
			refreshPending: false,
			pendingReason: '',
			subscription: null
		};
		this.entries.set(channel, entry);
		if (this.client) {
			this._attach(entry);
		}
		if (this.started && this.enabled && !this.connected) {
			this._schedulePoll(entry, 0);
		}
		return entry.subscription;
	};

	RealtimeClient.prototype.unsubscribe = function (channel) {
		var entry = this.entries.get(channel);
		if (!entry) {
			return;
		}
		if (entry.timer !== null) {
			this.clearTimer(entry.timer);
		}
		if (entry.subscription && typeof entry.subscription.unsubscribe === 'function') {
			entry.subscription.unsubscribe();
		}
		if (this.client && entry.subscription && typeof this.client.removeSubscription === 'function') {
			this.client.removeSubscription(entry.subscription);
		}
		this.entries.delete(channel);
	};

	RealtimeClient.prototype.teardown = function () {
		if (this.destroyed) {
			return;
		}
		var channels = Array.from(this.entries.keys());
		for (var i = 0; i < channels.length; i += 1) {
			this.unsubscribe(channels[i]);
		}
		if (this.client && typeof this.client.disconnect === 'function') {
			this.client.disconnect();
		}
		this.eventIds.clear();
		this.client = null;
		this.connected = false;
		this.destroyed = true;
	};

	RealtimeClient.prototype._attach = function (entry) {
		if (entry.subscription || !this.client || typeof this.client.newSubscription !== 'function') {
			return;
		}
		var self = this;
		entry.subscription = this.client.newSubscription(entry.channel, {
			getToken: function () { return self._subscriptionToken(entry.channel); }
		});
		entry.subscription.on('publication', function (context) {
			self._publication(entry, context && context.data);
		});
		entry.subscription.on('subscribed', function (context) {
			if (context && context.recovered === false) {
				self._refreshSnapshot(entry, 'recovery_unavailable');
			}
		});
		entry.subscription.subscribe();
	};

	RealtimeClient.prototype._connectionToken = function () {
		return this._requestToken(this.connectionTokenUrl, { method: 'GET' });
	};

	RealtimeClient.prototype._subscriptionToken = function (channel) {
		return this._requestToken(this.subscriptionTokenUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ channel: channel })
		});
	};

	RealtimeClient.prototype._requestToken = async function (url, options) {
		var response;
		try {
			response = await this.fetch(url, Object.assign({
				credentials: 'same-origin',
				cache: 'no-store',
				headers: Object.assign({ 'Accept': 'application/json' }, options.headers || {})
			}, options));
		} catch (error) {
			throw new Error('token_request_unavailable');
		}
		if (response.status === 401 || response.status === 403) {
			if (typeof this.UnauthorizedError === 'function') {
				throw new this.UnauthorizedError('token_denied');
			}
			throw new Error('token_denied');
		}
		if (!response.ok) {
			throw new Error('token_request_unavailable');
		}
		var body;
		try {
			body = await response.json();
		} catch (error) {
			throw new Error('token_response_invalid');
		}
		if (!body || body.success !== true || !body.data || typeof body.data.token !== 'string' || body.data.token === '') {
			throw new Error('token_response_invalid');
		}
		return body.data.token;
	};

	RealtimeClient.prototype._publication = function (entry, data) {
		if (!data || typeof data !== 'object' || Array.isArray(data)) {
			return;
		}
		var eventId = data.event_id;
		if ((typeof eventId !== 'string' && typeof eventId !== 'number')
			|| String(eventId).length < 1
			|| String(eventId).length > 100
			|| /[\x00-\x20\x7f]/.test(String(eventId))) {
			return;
		}
		eventId = String(eventId);
		if (this.eventIds.has(eventId)) {
			return;
		}
		this.eventIds.set(eventId, true);
		while (this.eventIds.size > MAX_EVENT_IDS) {
			this.eventIds.delete(this.eventIds.keys().next().value);
		}
		this._refreshSnapshot(entry, 'invalidation');
	};

	RealtimeClient.prototype._refreshSnapshot = async function (entry, reason) {
		if (this.destroyed) {
			return;
		}
		if (entry.refreshing) {
			entry.refreshPending = true;
			entry.pendingReason = reason;
			return;
		}
		entry.refreshing = true;
		try {
			var response = await this.fetch(entry.snapshotUrl, {
				method: 'GET',
				credentials: 'same-origin',
				cache: 'no-store',
				headers: { 'Accept': 'application/json' }
			});
			if (!response.ok) {
				throw new Error('snapshot_unavailable');
			}
			var data = await response.json();
			entry.onSnapshot(data, { channel: entry.channel, reason: reason });
		} catch (error) {
			this._safeError('snapshot_unavailable');
		} finally {
			entry.refreshing = false;
			if (entry.refreshPending && !this.destroyed) {
				var pendingReason = entry.pendingReason || 'invalidation';
				entry.refreshPending = false;
				entry.pendingReason = '';
				this._refreshSnapshot(entry, pendingReason);
			}
		}
	};

	RealtimeClient.prototype._schedulePoll = function (entry, delay) {
		if (this.destroyed || this.connected || entry.timer !== null) {
			return;
		}
		var self = this;
		entry.timer = this.setTimer(async function () {
			entry.timer = null;
			await self._refreshSnapshot(entry, 'poll');
			self._schedulePoll(entry, entry.pollIntervalMs);
		}, delay);
	};

	RealtimeClient.prototype._startPolling = function () {
		var self = this;
		this.entries.forEach(function (entry) { self._schedulePoll(entry, 0); });
	};

	RealtimeClient.prototype._stopPolling = function () {
		var self = this;
		this.entries.forEach(function (entry) {
			if (entry.timer !== null) {
				self.clearTimer(entry.timer);
				entry.timer = null;
			}
		});
	};

	RealtimeClient.prototype._safeError = function (code) {
		try {
			this.onError(code);
		} catch (error) {
			// Consumer errors must not interrupt reconnect or fallback polling.
		}
	};

	return RealtimeClient;
}));
