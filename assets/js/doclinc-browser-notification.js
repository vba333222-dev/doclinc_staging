(function (root, factory) {
	if (typeof module === 'object' && module.exports) {
		module.exports = factory;
	} else {
		var api = factory(root);
		root.DoclincBrowserNotification = api;
		var bind = function () { api.bindDocument(root.document); };
		if (root.document && root.document.readyState === 'loading') {
			root.document.addEventListener('DOMContentLoaded', bind);
		} else if (root.document) {
			bind();
		}
	}
}(typeof self !== 'undefined' ? self : (typeof globalThis !== 'undefined' ? globalThis : this), function (root) {
	'use strict';

	function permissionState(override) {
		var NotificationApi = root && root.Notification;
		if (typeof NotificationApi !== 'function') {
			return 'unavailable';
		}
		var state = typeof override === 'string' ? override : NotificationApi.permission;
		return state === 'granted' || state === 'denied' ? state : 'default';
	}

	function showIfGranted(title, options) {
		var NotificationApi = root && root.Notification;
		if (permissionState() !== 'granted') {
			return false;
		}
		try {
			new NotificationApi(title, options || {});
			return true;
		} catch (error) {
			return false;
		}
	}

	function requestFromUserGesture() {
		var NotificationApi = root && root.Notification;
		if (permissionState() !== 'default' || typeof NotificationApi.requestPermission !== 'function') {
			return Promise.resolve(permissionState());
		}
		try {
			return Promise.resolve(NotificationApi.requestPermission()).then(function (state) {
				return permissionState(state);
			});
		} catch (error) {
			return Promise.reject(error);
		}
	}

	function render(container, stateOverride) {
		var state = permissionState(stateOverride);
		var button = container.querySelector('[data-browser-notification-enable]');
		var status = container.querySelector('[data-browser-notification-status]');
		container.hidden = state === 'unavailable';
		if (!button || !status || state === 'unavailable') {
			return state;
		}
		button.disabled = false;
		button.hidden = state !== 'default';
		if (state === 'granted') {
			status.textContent = 'Notifikasi perangkat aktif.';
		} else if (state === 'denied') {
			status.textContent = 'Notifikasi perangkat dinonaktifkan di browser.';
		} else {
			status.textContent = 'Dapatkan pemberitahuan meski aplikasi tidak sedang dibuka.';
		}
		return state;
	}

	function bindContainer(container) {
		if (!container || container.__doclincNotificationBound) {
			return false;
		}
		var button = container.querySelector('[data-browser-notification-enable]');
		if (!button || typeof button.addEventListener !== 'function') {
			return false;
		}
		container.__doclincNotificationBound = true;
		button.addEventListener('click', function () {
			if (permissionState() !== 'default') {
				render(container);
				return;
			}
			button.disabled = true;
			requestFromUserGesture().then(function (state) {
				render(container, state);
			}).catch(function () {
				render(container);
			});
		});
		render(container);
		return true;
	}

	function bindDocument(documentRef) {
		if (!documentRef || typeof documentRef.querySelectorAll !== 'function') {
			return 0;
		}
		var containers = documentRef.querySelectorAll('[data-browser-notification-optin]');
		var bound = 0;
		for (var index = 0; index < containers.length; index += 1) {
			if (bindContainer(containers[index])) { bound += 1; }
		}
		return bound;
	}

	return Object.freeze({
		permissionState: permissionState,
		showIfGranted: showIfGranted,
		requestFromUserGesture: requestFromUserGesture,
		bindContainer: bindContainer,
		bindDocument: bindDocument
	});
}));
