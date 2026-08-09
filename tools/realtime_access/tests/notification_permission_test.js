'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const createBrowserNotification = require('../../../assets/js/doclinc-browser-notification.js');
const notifications = require('../../../assets/js/doclinc-notifications.js');

class InAppRealtimeClient {
	constructor() { this.connected = false; }
	acquireSubscription() { return { release() { return true; } }; }
	start() { return true; }
	hasSubscriptionOwners() { return false; }
	teardown() {}
}

function notificationApi(permission, result) {
	let requested = 0;
	let created = 0;
	function NotificationApi() { created += 1; }
	NotificationApi.permission = permission;
	NotificationApi.requestPermission = function () {
		requested += 1;
		NotificationApi.permission = result || permission;
		return Promise.resolve(NotificationApi.permission);
	};
	return { NotificationApi, requested: () => requested, created: () => created };
}

function notificationCase(permission) {
	const apiState = notificationApi(permission);
	let inAppUpdates = 0;
	const browserNotification = createBrowserNotification({ Notification: apiState.NotificationApi });
	const runtime = new notifications.NotificationRuntime({
		config: { enabled: true, channel: 'user:101', snapshot_url: '/notifications/snapshot', poll_interval_ms: 30000 },
		RealtimeClient: InAppRealtimeClient,
		ui: { applySnapshot() { inAppUpdates += 1; } },
		onNewNotifications(items) {
			if (items.length > 0) { browserNotification.showIfGranted('Pesan baru', { body: 'Pesan tersedia' }); }
		}
	});
	const started = runtime.start();
	runtime.applySnapshot({ success: true, data: { notifications: [{ notification_id: 1 }] } });
	runtime.applySnapshot({ success: true, data: { notifications: [{ notification_id: 1 }, { notification_id: 2 }] } });
	runtime.teardown();
	return { started, requested: apiState.requested(), browserNotifications: apiState.created(), inAppUpdates };
}

function optInFixture(permission, result) {
	const apiState = notificationApi(permission, result);
	const button = {
		disabled: false,
		hidden: false,
		handlers: {},
		addEventListener(name, handler) { this.handlers[name] = handler; }
	};
	const status = { textContent: '' };
	const container = {
		hidden: true,
		querySelector(selector) {
			if (selector === '[data-browser-notification-enable]') return button;
			if (selector === '[data-browser-notification-status]') return status;
			return null;
		}
	};
	const browserNotification = createBrowserNotification({ Notification: apiState.NotificationApi });
	browserNotification.bindContainer(container);
	return { apiState, browserNotification, button, status, container };
}

let passed = 0;
function check(condition, label) { assert.ok(condition, label); passed += 1; }
async function flushPermissionResult() { await Promise.resolve(); await Promise.resolve(); }

(async () => {
	const defaultCase = notificationCase('default');
	check(defaultCase.started && defaultCase.requested === 0, 'startup_default_does_not_request_permission');
	check(defaultCase.inAppUpdates === 2 && defaultCase.browserNotifications === 0,
		'message_arrival_default_keeps_in_app_updates_without_prompt_or_popup');

	const defaultOptIn = optInFixture('default', 'granted');
	check(defaultOptIn.apiState.requested() === 0 && defaultOptIn.button.hidden === false,
		'default_permission_exposes_opt_in_without_requesting');
	defaultOptIn.button.handlers.click();
	check(defaultOptIn.apiState.requested() === 1, 'explicit_click_requests_permission_exactly_once');
	await flushPermissionResult();
	check(defaultOptIn.button.hidden === true && defaultOptIn.status.textContent === 'Notifikasi perangkat aktif.',
		'granted_result_updates_opt_in_state');

	const deniedOptIn = optInFixture('default', 'denied');
	deniedOptIn.button.handlers.click();
	await flushPermissionResult();
	check(deniedOptIn.apiState.requested() === 1 && deniedOptIn.button.hidden === true,
		'denied_result_hides_enable_action');
	deniedOptIn.button.handlers.click();
	deniedOptIn.browserNotification.bindContainer(deniedOptIn.container);
	check(deniedOptIn.apiState.requested() === 1, 'denied_permission_has_no_automatic_or_click_retry');

	const deniedCase = notificationCase('denied');
	check(deniedCase.started && deniedCase.requested === 0 && deniedCase.browserNotifications === 0,
		'subsequent_denied_startup_does_not_prompt_or_create_popup');
	check(deniedCase.inAppUpdates === 2, 'denied_permission_preserves_in_app_badge_list_path');

	const grantedCase = notificationCase('granted');
	check(grantedCase.started && grantedCase.requested === 0 && grantedCase.browserNotifications === 1,
		'granted_permission_creates_browser_notification_without_prompt');

	const unavailable = createBrowserNotification({});
	const unavailableButton = { addEventListener() {}, hidden: false, disabled: false };
	const unavailableStatus = { textContent: '' };
	const unavailableContainer = {
		hidden: false,
		querySelector(selector) {
			return selector === '[data-browser-notification-enable]' ? unavailableButton : unavailableStatus;
		}
	};
	check(unavailable.showIfGranted('Pesan baru') === false
		&& unavailable.bindContainer(unavailableContainer) === true && unavailableContainer.hidden === true,
		'notification_api_unavailable_has_no_exception_or_popup');

	const repoRoot = path.resolve(__dirname, '../../..');
	const runtimeViews = [
		'application/modules/home_nakes/views/home_nakes_v.php',
		'application/modules/konsultasi_nakes/views/chat.php',
		'application/modules/konsultasi/views/chat.php',
		'application/modules/chat/views/chat.php'
	];
	const runtimeSources = runtimeViews.map((file) => fs.readFileSync(path.join(repoRoot, file), 'utf8'));
	const homeView = fs.readFileSync(path.join(repoRoot, 'application/modules/home/views/home_v.php'), 'utf8');
	const helperSource = fs.readFileSync(path.join(repoRoot, 'assets/js/doclinc-browser-notification.js'), 'utf8');
	const clickHandler = helperSource.slice(helperSource.indexOf("button.addEventListener('click'"), helperSource.indexOf("render(container);\n\t\treturn true;"));
	check(runtimeSources.every((source) => !source.includes('requestPermission')),
		'runtime_views_contain_no_automatic_permission_request');
	check((helperSource.match(/NotificationApi\.requestPermission\(\)/g) || []).length === 1
		&& clickHandler.includes('requestFromUserGesture()')
		&& !clickHandler.includes('setTimeout'), 'single_permission_call_is_reachable_only_from_direct_click_handler');
	check(homeView.includes('data-browser-notification-optin')
		&& runtimeSources[0].includes('data-browser-notification-optin')
		&& homeView.includes('Aktifkan notifikasi perangkat')
		&& runtimeSources[0].includes('Aktifkan notifikasi perangkat'),
		'warga_and_nakes_notification_panels_expose_explicit_opt_in');

	process.stdout.write(`NOTIFICATION_PERMISSION_TEST_PASSED=${passed}\n`);
	process.stdout.write('NOTIFICATION_PERMISSION_TEST_FAILED=0\n');
})().catch((error) => {
	process.stderr.write(`NOTIFICATION_PERMISSION_TEST_FAILED_SAFE=${error.name}\n`);
	process.exitCode = 1;
});
