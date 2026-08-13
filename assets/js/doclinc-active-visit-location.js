(function (root, factory) {
	'use strict';

	var api = factory();
	if (typeof module === 'object' && module.exports) {
		module.exports = api;
	}
	if (root) {
		root.DoclincActiveVisitLocation = api;
	}
})(typeof window !== 'undefined' ? window : globalThis, function () {
	'use strict';

	function create(options) {
		options = options || {};
		var runtime = options.window || (typeof window !== 'undefined' ? window : null);
		var navigatorRef = options.navigator || (runtime && runtime.navigator);
		var requestId = parseInt(options.requestId, 10) || 0;
		var active = options.active === true;
		var updateUrl = String(options.updateUrl || '');
		var eligibilityUrl = String(options.eligibilityUrl || '');
		var eligibilityCheck = typeof options.eligibilityCheck === 'function' ? options.eligibilityCheck : null;
		var revalidateOnStart = options.revalidateOnStart === true;
		var minIntervalMs = Math.max(1000, parseInt(options.minIntervalMs, 10) || 5000);
		var setStatus = typeof options.setStatus === 'function' ? options.setStatus : function () {};
		var onResult = typeof options.onResult === 'function' ? options.onResult : function () {};
		var fetchRef = options.fetch || (runtime && typeof runtime.fetch === 'function' ? runtime.fetch.bind(runtime) : null);
		var watchId = null;
		var permission = null;
		var inFlight = false;
		var lastSentAt = 0;
		var destroyed = false;
		var suspended = false;
		var starting = false;
		var listenersAttached = false;
		var generation = 0;

		function isCurrent(expectedGeneration) {
			return !destroyed && !suspended && expectedGeneration === generation;
		}

		function setCurrentStatus(expectedGeneration, message, isError) {
			if (isCurrent(expectedGeneration)) {
				setStatus(message, isError);
			}
		}

		function removePermissionListener() {
			if (permission && permission.removeEventListener) {
				permission.removeEventListener('change', permissionChanged);
			}
			permission = null;
		}

		function clearWatcher() {
			if (watchId !== null && navigatorRef && navigatorRef.geolocation) {
				navigatorRef.geolocation.clearWatch(watchId);
			}
			watchId = null;
		}

		function invalidateRuntime(nextSuspended) {
			generation += 1;
			suspended = nextSuspended === true;
			starting = false;
			inFlight = false;
			removePermissionListener();
			clearWatcher();
		}

		function suspend() {
			if (destroyed || suspended) return;
			invalidateRuntime(true);
		}

		function stop() {
			if (destroyed) return;
			destroyed = true;
			invalidateRuntime(false);
			if (runtime && runtime.removeEventListener && listenersAttached) {
				runtime.removeEventListener('pagehide', pagehideHandler);
				runtime.removeEventListener('pageshow', pageshowHandler);
			}
			listenersAttached = false;
		}

		function pagehideHandler(event) {
			if (event && event.persisted === true) {
				suspend();
				return;
			}
			stop();
		}

		function pageshowHandler(event) {
			if (event && event.persisted === true) {
				resume();
			}
		}

		function attachLifecycleListeners() {
			if (listenersAttached || !runtime || !runtime.addEventListener) return;
			runtime.addEventListener('pagehide', pagehideHandler);
			runtime.addEventListener('pageshow', pageshowHandler);
			listenersAttached = true;
		}

		function locationValue(value) {
			var parsed = parseFloat(value);
			return Number.isFinite(parsed) ? parsed : '';
		}

		function post(position, expectedGeneration) {
			if (!isCurrent(expectedGeneration) || watchId === null || inFlight || !position || !position.coords || !fetchRef) return;
			var now = Date.now();
			if (now - lastSentAt < minIntervalMs) return;
			lastSentAt = now;
			inFlight = true;
			var body = new URLSearchParams();
			body.set('request_id', String(requestId));
			body.set('latitude', String(position.coords.latitude));
			body.set('longitude', String(position.coords.longitude));
			body.set('accuracy_m', String(locationValue(position.coords.accuracy)));
			body.set('heading', String(locationValue(position.coords.heading)));
			body.set('speed_mps', String(locationValue(position.coords.speed)));
			Promise.resolve().then(function () {
				return fetchRef(updateUrl, {
					method: 'POST',
					headers: {
						'Accept': 'application/json',
						'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
						'X-Requested-With': 'XMLHttpRequest'
					},
					body: body.toString(),
					credentials: 'same-origin'
				});
			}).then(function (response) {
				return response.json().catch(function () { return {}; }).then(function (payload) {
					return { ok: response.ok, status: response.status, payload: payload || {} };
				});
			}).then(function (result) {
				if (!isCurrent(expectedGeneration)) return;
				if (result.ok) {
					try {
						onResult(result.payload);
					} catch (error) {}
				}
				if (result.payload.tracking_active === false
					|| result.payload.visit_status === 'completed'
					|| (result.payload.request_status && result.payload.request_status !== 'Accepted')) {
					stop();
					return;
				}
				if (!result.ok) {
					setCurrentStatus(expectedGeneration, result.status === 403 ? 'Lokasi tidak tersedia.' : 'Lokasi belum dapat diperbarui.', true);
					if ([400, 403, 404, 405].indexOf(result.status) !== -1) stop();
					return;
				}
				setCurrentStatus(expectedGeneration, 'Lokasi dibagikan.', false);
			}).catch(function () {
				setCurrentStatus(expectedGeneration, 'Lokasi belum dapat diperbarui.', true);
			}).finally(function () {
				if (isCurrent(expectedGeneration)) {
					inFlight = false;
				}
			});
		}

		function locationFailed(error, expectedGeneration) {
			if (!isCurrent(expectedGeneration)) return;
			if (error && error.code === 1) {
				setCurrentStatus(expectedGeneration, 'Izinkan akses lokasi di pengaturan browser untuk berbagi posisi.', true);
			} else {
				setCurrentStatus(expectedGeneration, 'Lokasi tidak tersedia.', true);
			}
			clearWatcher();
		}

		function begin(expectedGeneration) {
			if (!isCurrent(expectedGeneration) || watchId !== null || !active || requestId < 1) return;
			if (!navigatorRef || !navigatorRef.geolocation || !fetchRef || updateUrl === '') {
				setCurrentStatus(expectedGeneration, 'Lokasi tidak tersedia.', true);
				return;
			}
			watchId = navigatorRef.geolocation.watchPosition(function (position) {
				post(position, expectedGeneration);
			}, function (error) {
				locationFailed(error, expectedGeneration);
			}, {
				enableHighAccuracy: true,
				maximumAge: 30000,
				timeout: 10000
			});
		}

		function permissionChanged() {
			if (!permission || destroyed || suspended) return;
			if (permission.state === 'granted') {
				begin(generation);
				return;
			}
			generation += 1;
			inFlight = false;
			clearWatcher();
			setCurrentStatus(generation, 'Izinkan akses lokasi di pengaturan browser untuk berbagi posisi.', true);
		}

		function queryPermission(expectedGeneration) {
			if (!isCurrent(expectedGeneration)) return;
			navigatorRef.permissions.query({ name: 'geolocation' }).then(function (result) {
				if (!isCurrent(expectedGeneration) || !result) return;
				permission = result;
				starting = false;
				if (permission.addEventListener) permission.addEventListener('change', permissionChanged);
				permissionChanged();
			}).catch(function () {
				if (!isCurrent(expectedGeneration)) return;
				starting = false;
				setCurrentStatus(expectedGeneration, 'Lokasi tidak tersedia.', true);
			});
		}

		function eligiblePayload(payload) {
			if (payload === true) return true;
			if (!payload || typeof payload !== 'object') return false;
			return parseInt(payload.request_id, 10) === requestId
				&& payload.viewer_can_update === true
				&& payload.tracking_active === true
				&& payload.request_status === 'Accepted'
				&& String(payload.consultation_mode || '').toLowerCase() === 'visit'
				&& ['en_route', 'arrived', 'in_service'].indexOf(String(payload.visit_status || '').toLowerCase()) !== -1;
		}

		function currentEligibility() {
			if (eligibilityCheck) {
				return Promise.resolve().then(function () { return eligibilityCheck(requestId); }).then(eligiblePayload);
			}
			if (!fetchRef || eligibilityUrl === '') return Promise.resolve(false);
			var separator = eligibilityUrl.indexOf('?') === -1 ? '?' : '&';
			return fetchRef(eligibilityUrl + separator + 'request_id=' + encodeURIComponent(requestId), {
				method: 'GET',
				headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
				credentials: 'same-origin'
			}).then(function (response) {
				if (!response.ok) return false;
				return response.json().catch(function () { return null; });
			}).then(eligiblePayload).catch(function () { return false; });
		}

		function activate(revalidate) {
			if (!active || requestId < 1 || destroyed || starting || watchId !== null) return false;
			if (!navigatorRef || !navigatorRef.geolocation) {
				setStatus('Lokasi tidak tersedia.', true);
				return false;
			}
			if (!navigatorRef.permissions || typeof navigatorRef.permissions.query !== 'function') {
				setStatus('Lokasi tidak tersedia.', true);
				return false;
			}
			suspended = false;
			generation += 1;
			var expectedGeneration = generation;
			starting = true;
			if (!revalidate) {
				queryPermission(expectedGeneration);
				return true;
			}
			currentEligibility().then(function (eligible) {
				if (!isCurrent(expectedGeneration)) return;
				if (!eligible) {
					starting = false;
					setCurrentStatus(expectedGeneration, 'Lokasi tidak tersedia.', true);
					return;
				}
				queryPermission(expectedGeneration);
			}).catch(function () {
				if (!isCurrent(expectedGeneration)) return;
				starting = false;
				setCurrentStatus(expectedGeneration, 'Lokasi tidak tersedia.', true);
			});
			return true;
		}

		function resume() {
			if (destroyed || !suspended) return false;
			suspended = false;
			return activate(true);
		}

		function start() {
			if (!active || requestId < 1 || destroyed) return false;
			attachLifecycleListeners();
			return activate(revalidateOnStart);
		}

		return Object.freeze({
			start: start,
			stop: stop,
			isActive: function () { return !destroyed && !suspended && watchId !== null; },
			isInFlight: function () { return inFlight; }
		});
	}

	function createManager(options) {
		options = options || {};
		var createRuntime = typeof options.createRuntime === 'function' ? options.createRuntime : null;
		var current = null;
		var generation = 0;

		function start(requestId) {
			requestId = parseInt(requestId, 10) || 0;
			if (requestId < 1 || !createRuntime) return false;
			if (current && current.requestId === requestId) {
				current.runtime.start();
				return true;
			}
			stop();
			var runtime = createRuntime(requestId);
			if (!runtime || typeof runtime.start !== 'function' || typeof runtime.stop !== 'function') return false;
			generation += 1;
			current = { requestId: requestId, runtime: runtime, generation: generation };
			runtime.start();
			return true;
		}

		function stop(requestId, expectedGeneration) {
			if (!current) return false;
			var parsedRequestId = parseInt(requestId, 10) || 0;
			if (parsedRequestId > 0 && parsedRequestId !== current.requestId) return false;
			if (expectedGeneration !== undefined && parseInt(expectedGeneration, 10) !== current.generation) return false;
			var runtime = current.runtime;
			current = null;
			generation += 1;
			runtime.stop();
			return true;
		}

		return Object.freeze({
			start: start,
			stop: stop,
			requestId: function () { return current ? current.requestId : 0; },
			generation: function () { return current ? current.generation : generation; },
			isActive: function () { return !!(current && current.runtime.isActive && current.runtime.isActive()); }
		});
	}

	return Object.freeze({ create: create, createManager: createManager });
});
