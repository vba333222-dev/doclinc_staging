(function (root, factory) {
	'use strict';

	var api = factory();
	if (typeof module === 'object' && module.exports) {
		module.exports = api;
	}
	if (root) {
		root.DoclincChatParticipant = api;
	}
})(typeof window !== 'undefined' ? window : globalThis, function () {
	'use strict';

	function create(options) {
		options = options || {};
		var nameElements = Array.isArray(options.nameElements) ? options.nameElements.filter(Boolean) : [];
		var imageElements = Array.isArray(options.imageElements) ? options.imageElements.filter(Boolean) : [];
		var fallbackName = String(options.fallbackName || 'Tenaga kesehatan');
		var fallbackPhotoUrl = String(options.fallbackPhotoUrl || '');
		var isSafePhotoUrl = typeof options.isSafePhotoUrl === 'function' ? options.isSafePhotoUrl : function () { return false; };

		function update(context) {
			context = context && typeof context === 'object' ? context : {};
			var name = typeof context.name === 'string' ? context.name.trim() : '';
			var neutral = context.neutral === true || name === '';
			if (neutral) name = fallbackName;
			var photoUrl = !neutral && isSafePhotoUrl(context.photo_url) ? context.photo_url : fallbackPhotoUrl;

			nameElements.forEach(function (element) {
				element.textContent = name;
			});
			imageElements.forEach(function (image) {
				image.src = photoUrl;
				image.alt = 'Foto ' + name;
			});
			return { name: name, photoUrl: photoUrl, neutral: neutral };
		}

		return Object.freeze({ update: update });
	}

	function createPoller(options) {
		options = options || {};
		var request = typeof options.request === 'function' ? options.request : function () { return Promise.resolve(null); };
		var apply = typeof options.apply === 'function' ? options.apply : function () {};
		var onError = typeof options.onError === 'function' ? options.onError : function () {};
		var singleFlight = options.singleFlight !== false;
		var latestIssuedSequence = 0;
		var latestAcceptedSequence = 0;
		var inFlightCount = 0;
		var stopped = false;

		function poll() {
			if (stopped || (singleFlight && inFlightCount > 0)) {
				return Promise.resolve({ started: false, applied: false });
			}

			var sequence = ++latestIssuedSequence;
			inFlightCount += 1;
			return Promise.resolve()
				.then(function () { return request(sequence); })
				.then(function (result) {
					if (stopped || sequence !== latestIssuedSequence || sequence < latestAcceptedSequence) {
						return { started: true, applied: false };
					}
					latestAcceptedSequence = sequence;
					apply(result, sequence);
					return { started: true, applied: true };
				})
				.catch(function (error) {
					if (!stopped && sequence === latestIssuedSequence) {
						onError(error, sequence);
					}
					return { started: true, applied: false, failed: true };
				})
				.finally(function () {
					inFlightCount = Math.max(0, inFlightCount - 1);
				});
		}

		function stop() {
			if (stopped) return;
			stopped = true;
			latestIssuedSequence += 1;
		}

		return Object.freeze({
			poll: poll,
			stop: stop,
			isInFlight: function () { return inFlightCount > 0; },
			latestAcceptedSequence: function () { return latestAcceptedSequence; }
		});
	}

	function createMessageRegistry() {
		var seen = Object.create(null);

		function accept(value) {
			var messageId = parseInt(value, 10);
			if (!Number.isFinite(messageId) || messageId < 1) return true;
			if (seen[messageId] === true) return false;
			seen[messageId] = true;
			return true;
		}

		return Object.freeze({ accept: accept });
	}

	return Object.freeze({ create: create, createPoller: createPoller, createMessageRegistry: createMessageRegistry });
});
