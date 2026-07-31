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
		this.UnauthorizedError = options.UnauthorizedError
			|| (this.Centrifuge && this.Centrifuge.UnauthorizedError)
			|| (root && root.UnauthorizedError);
		this.setTimer = options.setTimeout || setTimeout;
		this.clearTimer = options.clearTimeout || clearTimeout;
		this.onError = typeof options.onError === 'function' ? options.onError : function () {};
		this.client = null;
		this.connected = false;
		this.started = false;
		this.destroyed = false;
		this.entries = new Map();
		this.nextSubscriptionOwnerId = 1;
		this.eventIds = new Map();
		this.observers = new Map();
		this.nextObserverId = 1;
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
				self._notifyObservers('connected');
			});
			this.client.on('disconnected', function () {
				self.connected = false;
				self._startPolling();
				self._notifyObservers('disconnected');
			});
		}
		this.entries.forEach(function (entry) { self._attach(entry); });
		this.client.connect();
		return true;
	};

	RealtimeClient.prototype.observe = function (channel, callbacks) {
		if (this.destroyed || !validChannel(channel)) {
			throw new Error('observer_configuration_invalid');
		}
		var id = this.nextObserverId++;
		this.observers.set(id, { channel: channel, callbacks: callbacks || {} });
		return id;
	};

	RealtimeClient.prototype.unobserve = function (id) {
		this.observers.delete(id);
	};

	RealtimeClient.prototype.acquireSubscription = function (channel, options) {
		options = options || {};
		var hasSnapshot = typeof options.snapshotUrl !== 'undefined';
		if (this.destroyed || !validChannel(channel) || (hasSnapshot && !safeRelativeUrl(options.snapshotUrl))) {
			throw new Error('subscription_configuration_invalid');
		}
		var interval = hasSnapshot ? Number(options.pollIntervalMs || DEFAULT_POLL_INTERVAL) : DEFAULT_POLL_INTERVAL;
		if (hasSnapshot && (!Number.isFinite(interval) || interval < MIN_POLL_INTERVAL || interval > MAX_POLL_INTERVAL)) {
			throw new Error('poll_interval_invalid');
		}
		var entry = this.entries.get(channel);
		var createdEntry = false;
		if (!entry) {
			entry = { channel: channel, subscription: null, owners: new Map(), legacyOwner: null };
			this.entries.set(channel, entry);
			createdEntry = true;
		}
		var ownerId = this.nextSubscriptionOwnerId++;
		var owner = {
			id: ownerId,
			channel: channel,
			snapshotUrl: hasSnapshot ? options.snapshotUrl : null,
			onSnapshot: typeof options.onSnapshot === 'function' ? options.onSnapshot : function () {},
			pollIntervalMs: interval,
			timer: null,
			refreshing: false,
			refreshPending: false,
			pendingReason: '',
			released: false
		};
		entry.owners.set(ownerId, owner);
		try {
			if (this.client) {
				this._attach(entry);
			}
			if (hasSnapshot && this.started && this.enabled && !this.connected) {
				this._schedulePoll(owner, 0);
			}
		} catch (error) {
			entry.owners.delete(ownerId);
			if (createdEntry || entry.owners.size === 0) {
				this._removeEntry(entry);
			}
			throw error;
		}
		var self = this;
		var released = false;
		return {
			channel: channel,
			ownerId: ownerId,
			get subscription() { return entry.subscription; },
			release: function () {
				if (released) { return false; }
				released = true;
				return self._releaseSubscriptionOwner(channel, ownerId);
			}
		};
	};

	RealtimeClient.prototype.subscribe = function (channel, options) {
		var entry = this.entries.get(channel);
		if (entry && entry.legacyOwner) {
			return entry.subscription;
		}
		var handle = this.acquireSubscription(channel, options);
		entry = this.entries.get(channel);
		entry.legacyOwner = handle;
		return entry.subscription;
	};

	RealtimeClient.prototype.unsubscribe = function (channel) {
		var entry = this.entries.get(channel);
		if (!entry || !entry.legacyOwner) {
			return;
		}
		var handle = entry.legacyOwner;
		entry.legacyOwner = null;
		handle.release();
	};

	RealtimeClient.prototype.hasSubscriptionOwners = function () {
		var active = false;
		this.entries.forEach(function (entry) {
			if (entry.owners.size > 0) { active = true; }
		});
		return active;
	};

	RealtimeClient.prototype.teardown = function () {
		if (this.destroyed) {
			return;
		}
		var self = this;
		Array.from(this.entries.values()).forEach(function (entry) { self._removeEntry(entry); });
		if (this.client && typeof this.client.disconnect === 'function') {
			this.client.disconnect();
		}
		this.eventIds.clear();
		this.observers.clear();
		this.client = null;
		this.connected = false;
		this.destroyed = true;
	};

	RealtimeClient.prototype._attach = function (entry) {
		if (entry.subscription || !this.client || typeof this.client.newSubscription !== 'function') {
			return;
		}
		var self = this;
		try {
			entry.subscription = this.client.newSubscription(entry.channel, {
				getToken: function () { return self._subscriptionToken(entry.channel); }
			});
			entry.subscription.on('publication', function (context) {
				self._publication(entry, context && context.data);
			});
			entry.subscription.on('subscribed', function (context) {
				if (context && context.recovered === false) {
					self._notifyObservers('recovery', entry.channel);
					self._refreshEntrySnapshots(entry, 'recovery_unavailable');
				}
			});
			entry.subscription.subscribe();
		} catch (error) {
			this._detachSubscription(entry);
			throw new Error('subscription_acquisition_failed');
		}
	};

	RealtimeClient.prototype._releaseSubscriptionOwner = function (channel, ownerId) {
		var entry = this.entries.get(channel);
		if (!entry || !entry.owners.has(ownerId)) { return false; }
		var owner = entry.owners.get(ownerId);
		owner.released = true;
		if (owner.timer !== null) { this.clearTimer(owner.timer); }
		entry.owners.delete(ownerId);
		if (entry.owners.size === 0) { this._removeEntry(entry); }
		return true;
	};

	RealtimeClient.prototype._detachSubscription = function (entry) {
		if (!entry.subscription) { return; }
		if (typeof entry.subscription.unsubscribe === 'function') { entry.subscription.unsubscribe(); }
		if (this.client && typeof this.client.removeSubscription === 'function') {
			this.client.removeSubscription(entry.subscription);
		}
		entry.subscription = null;
	};

	RealtimeClient.prototype._removeEntry = function (entry) {
		var self = this;
		entry.owners.forEach(function (owner) {
			if (owner.timer !== null) { self.clearTimer(owner.timer); }
		});
		entry.owners.clear();
		entry.legacyOwner = null;
		this._detachSubscription(entry);
		this.entries.delete(entry.channel);
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
		this._notifyObservers('invalidation', entry.channel, data);
		this._refreshEntrySnapshots(entry, 'invalidation');
	};

	RealtimeClient.prototype._notifyObservers = function (type, channel, data) {
		this.observers.forEach(function (observer) {
			if (channel && observer.channel !== channel) {
				return;
			}
			var callback = observer.callbacks && observer.callbacks[type];
			if (typeof callback === 'function') {
				try { callback(data); } catch (error) { /* Consumer isolation is intentional. */ }
			}
		});
	};

	RealtimeClient.prototype._refreshEntrySnapshots = function (entry, reason) {
		var self = this;
		entry.owners.forEach(function (owner) {
			if (owner.snapshotUrl) { self._refreshSnapshotOwner(owner, entry.channel, reason); }
		});
	};

	RealtimeClient.prototype._refreshSnapshotOwner = async function (owner, channel, reason) {
		if (this.destroyed || owner.released) {
			return;
		}
		if (owner.refreshing) {
			owner.refreshPending = true;
			owner.pendingReason = reason;
			return;
		}
		owner.refreshing = true;
		try {
			var response = await this.fetch(owner.snapshotUrl, {
				method: 'GET',
				credentials: 'same-origin',
				cache: 'no-store',
				headers: { 'Accept': 'application/json' }
			});
			if (!response.ok) {
				throw new Error('snapshot_unavailable');
			}
			var data = await response.json();
			if (!this.destroyed && !owner.released) {
				owner.onSnapshot(data, { channel: channel, reason: reason });
			}
		} catch (error) {
			this._safeError('snapshot_unavailable');
		} finally {
			owner.refreshing = false;
			if (owner.refreshPending && !this.destroyed && !owner.released) {
				var pendingReason = owner.pendingReason || 'invalidation';
				owner.refreshPending = false;
				owner.pendingReason = '';
				this._refreshSnapshotOwner(owner, channel, pendingReason);
			}
		}
	};

	RealtimeClient.prototype._schedulePoll = function (owner, delay) {
		if (this.destroyed || owner.released || this.connected || !owner.snapshotUrl || owner.timer !== null) {
			return;
		}
		var self = this;
		owner.timer = this.setTimer(async function () {
			owner.timer = null;
			await self._refreshSnapshotOwner(owner, owner.channel, 'poll');
			if (!owner.released) { self._schedulePoll(owner, owner.pollIntervalMs); }
		}, delay);
	};

	RealtimeClient.prototype._startPolling = function () {
		var self = this;
		this.entries.forEach(function (entry) {
			entry.owners.forEach(function (owner) { self._schedulePoll(owner, 0); });
		});
	};

	RealtimeClient.prototype._stopPolling = function () {
		var self = this;
		this.entries.forEach(function (entry) {
			entry.owners.forEach(function (owner) {
				if (owner.timer !== null) {
					self.clearTimer(owner.timer);
					owner.timer = null;
				}
			});
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
