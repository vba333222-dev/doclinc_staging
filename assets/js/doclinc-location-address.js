(function (window) {
	'use strict';

	var DEFAULT_TIMEOUT_MS = 6000;
	var DEFAULT_MIN_INTERVAL_MS = 2500;
	var DEFAULT_CACHE_TTL_MS = 10 * 60 * 1000;
	var DEFAULT_CACHE_LIMIT = 24;

	function finiteCoordinate(value, minimum, maximum) {
		var number = Number(value);
		return Number.isFinite(number) && number >= minimum && number <= maximum ? number : null;
	}

	function normalizeLocation(location) {
		var latitude = finiteCoordinate(location && (location.lat !== undefined ? location.lat : location.latitude), -90, 90);
		var longitude = finiteCoordinate(location && (location.lng !== undefined ? location.lng : location.longitude), -180, 180);
		return latitude === null || longitude === null ? null : { lat: latitude, lng: longitude };
	}

	function safeText(value, maximumLength) {
		var text = String(value || '').replace(/\s+/g, ' ').trim();
		if (!text || text.length > maximumLength || /[\u0000-\u001f\u007f]/.test(text)) return '';
		return text;
	}

	function unavailable(locationFound) {
		return {
			available: false,
			location_found: !!locationFound,
			address: locationFound ? 'Alamat belum dapat dikenali' : 'Lokasi belum ditemukan',
			locality: locationFound ? 'Lokasi perangkat ditemukan' : 'Periksa izin lokasi perangkat',
			provider: ''
		};
	}

	function mapboxLocality(feature) {
		if (!feature) return '';
		var candidates = [feature].concat(Array.isArray(feature.context) ? feature.context : []);
		var prefixes = ['place.', 'locality.', 'district.', 'region.'];
		for (var prefixIndex = 0; prefixIndex < prefixes.length; prefixIndex += 1) {
			for (var index = 0; index < candidates.length; index += 1) {
				if (String(candidates[index] && candidates[index].id || '').indexOf(prefixes[prefixIndex]) === 0) {
					return safeText(candidates[index].text, 120);
				}
			}
		}
		return '';
	}

	function fromMapbox(payload) {
		var feature = payload && Array.isArray(payload.features) && payload.features.length ? payload.features[0] : null;
		var address = safeText(feature && (feature.place_name || feature.text), 500);
		if (!address) return unavailable(true);
		return {
			available: true,
			location_found: true,
			address: address,
			locality: mapboxLocality(feature),
			provider: 'mapbox'
		};
	}

	function googleLocality(result) {
		var components = result && Array.isArray(result.address_components) ? result.address_components : [];
		var wanted = ['administrative_area_level_4', 'administrative_area_level_3', 'locality', 'administrative_area_level_2'];
		for (var typeIndex = 0; typeIndex < wanted.length; typeIndex += 1) {
			for (var index = 0; index < components.length; index += 1) {
				if (Array.isArray(components[index].types) && components[index].types.indexOf(wanted[typeIndex]) !== -1) {
					return safeText(components[index].long_name, 120);
				}
			}
		}
		return '';
	}

	function fromGoogle(results, status) {
		var result = status === 'OK' && Array.isArray(results) && results.length ? results[0] : null;
		var address = safeText(result && result.formatted_address, 500);
		if (!address) return unavailable(true);
		return {
			available: true,
			location_found: true,
			address: address,
			locality: googleLocality(result),
			provider: 'google'
		};
	}

	function mapUrl(location) {
		var normalized = normalizeLocation(location);
		if (!normalized) return '';
		return 'https://www.openstreetmap.org/?mlat=' + encodeURIComponent(normalized.lat) +
			'&mlon=' + encodeURIComponent(normalized.lng) + '#map=18/' +
			encodeURIComponent(normalized.lat) + '/' + encodeURIComponent(normalized.lng);
	}

	function create(options) {
		options = options || {};
		var provider = String(options.provider || '').toLowerCase();
		var mapboxToken = String(options.mapboxToken || '').trim();
		var fetchImpl = options.fetch || window.fetch;
		var googleMaps = options.googleMaps || null;
		var now = options.now || Date.now;
		var delay = options.delay || function (callback, milliseconds) { return window.setTimeout(callback, milliseconds); };
		var cancelDelay = options.cancelDelay || function (timer) { window.clearTimeout(timer); };
		var timeoutMs = Math.max(1000, Number(options.timeoutMs) || DEFAULT_TIMEOUT_MS);
		var minIntervalMs = Math.max(0, Number(options.minIntervalMs) || DEFAULT_MIN_INTERVAL_MS);
		var cacheTtlMs = Math.max(1000, Number(options.cacheTtlMs) || DEFAULT_CACHE_TTL_MS);
		var cacheLimit = Math.max(1, Number(options.cacheLimit) || DEFAULT_CACHE_LIMIT);
		var cache = new Map();
		var pending = new Map();
		var lastStartedAt = 0;

		function cacheKey(location) {
			return location.lat.toFixed(5) + ',' + location.lng.toFixed(5);
		}

		function remember(key, value) {
			cache.delete(key);
			cache.set(key, { value: value, expires_at: now() + cacheTtlMs });
			while (cache.size > cacheLimit) cache.delete(cache.keys().next().value);
			return value;
		}

		function cached(key) {
			var entry = cache.get(key);
			if (!entry) return null;
			if (entry.expires_at <= now()) {
				cache.delete(key);
				return null;
			}
			cache.delete(key);
			cache.set(key, entry);
			return entry.value;
		}

		function mapboxRequest(location) {
			if (provider !== 'mapbox' || !mapboxToken || typeof fetchImpl !== 'function') {
				return Promise.resolve(unavailable(true));
			}
			var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
			var url = 'https://api.mapbox.com/geocoding/v5/mapbox.places/' +
				encodeURIComponent(location.lng + ',' + location.lat) +
				'.json?limit=1&language=id&types=address,poi,neighborhood,locality,place&access_token=' +
				encodeURIComponent(mapboxToken);
			var timer = delay(function () { if (controller) controller.abort(); }, timeoutMs);
			return fetchImpl(url, {
				method: 'GET',
				headers: { 'Accept': 'application/json' },
				signal: controller ? controller.signal : undefined,
				credentials: 'omit',
				referrerPolicy: 'no-referrer'
			}).then(function (response) {
				if (!response || !response.ok) throw new Error('reverse_geocode_unavailable');
				return response.json();
			}).then(fromMapbox).catch(function () {
				return unavailable(true);
			}).finally(function () {
				cancelDelay(timer);
			});
		}

		function googleRequest(location) {
			if (provider !== 'google' || !googleMaps || typeof googleMaps.Geocoder !== 'function') {
				return Promise.resolve(unavailable(true));
			}
			return new Promise(function (resolve) {
				var settled = false;
				var timer = delay(function () {
					if (!settled) {
						settled = true;
						resolve(unavailable(true));
					}
				}, timeoutMs);
				try {
					(new googleMaps.Geocoder()).geocode({ location: location }, function (results, status) {
						if (settled) return;
						settled = true;
						cancelDelay(timer);
						resolve(fromGoogle(results, status));
					});
				} catch (error) {
					if (!settled) {
						settled = true;
						cancelDelay(timer);
						resolve(unavailable(true));
					}
				}
			});
		}

		function execute(location) {
			lastStartedAt = now();
			return provider === 'google' ? googleRequest(location) : mapboxRequest(location);
		}

		function resolve(location) {
			var normalized = normalizeLocation(location);
			if (!normalized) return Promise.resolve(unavailable(false));
			var key = cacheKey(normalized);
			var hit = cached(key);
			if (hit) return Promise.resolve(hit);
			if (pending.has(key)) return pending.get(key);
			var waitMs = Math.max(0, minIntervalMs - (now() - lastStartedAt));
			var promise = new Promise(function (resolvePromise) {
				delay(function () {
					execute(normalized).then(function (result) {
						resolvePromise(remember(key, result));
					});
				}, waitMs);
			}).finally(function () {
				pending.delete(key);
			});
			pending.set(key, promise);
			return promise;
		}

		return Object.freeze({ resolve: resolve });
	}

	window.DoclincLocationAddress = Object.freeze({
		create: create,
		fromMapbox: fromMapbox,
		fromGoogle: fromGoogle,
		normalizeLocation: normalizeLocation,
		mapUrl: mapUrl,
		unavailable: unavailable
	});
})(window);
