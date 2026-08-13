'use strict';

const locationClient = require('../../assets/js/doclinc-active-visit-location.js');
const formGuard = require('../../assets/js/doclinc-clinical-form-guard.js');
const chatParticipantClient = require('../../assets/js/doclinc-chat-participant.js');

let passed = 0;
let failed = 0;

function expect(condition, name) {
  if (condition) {
    passed += 1;
    process.stdout.write(`PASS ${name}\n`);
    return;
  }
  failed += 1;
  process.stdout.write(`FAIL ${name}\n`);
}

function deferred() {
  let resolve;
  let reject;
  const promise = new Promise((done, fail) => { resolve = done; reject = fail; });
  return { promise, resolve, reject };
}

function tick() {
  return new Promise((resolve) => setImmediate(resolve));
}

async function flush(count = 4) {
  for (let index = 0; index < count; index += 1) await tick();
}

function locationFixture(permissionState, active = true) {
  const listeners = {};
  const permissionListeners = {};
  const statuses = [];
  const posts = [];
  let watchSuccess = null;
  let watchFailure = null;
  let watchCount = 0;
  let clearCount = 0;
  const permission = {
    state: permissionState,
    addEventListener(type, listener) { permissionListeners[type] = listener; },
    removeEventListener(type) { delete permissionListeners[type]; },
  };
  const runtime = {
    addEventListener(type, listener) { listeners[type] = listener; },
    removeEventListener(type) { delete listeners[type]; },
  };
  const navigator = {
    permissions: { query: async () => permission },
    geolocation: {
      watchPosition(success, failure) {
        watchCount += 1;
        watchSuccess = success;
        watchFailure = failure;
        return 91;
      },
      clearWatch() { clearCount += 1; },
    },
  };
  const client = locationClient.create({
    window: runtime,
    navigator,
    requestId: 42,
    active,
    updateUrl: '/home_nakes/update_visit_location',
    minIntervalMs: 1000,
    fetch(url, options) {
      posts.push({ url, options });
      return Promise.resolve({ ok: true, status: 200, json: async () => ({ status: 'success', tracking_active: true }) });
    },
    setStatus(message, isError) { statuses.push({ message, isError }); },
  });
  return {
    client, permission, permissionListeners, listeners, statuses, posts,
    success(position) { watchSuccess(position); },
    failure(error) { watchFailure(error); },
    watchCount() { return watchCount; },
    clearCount() { return clearCount; },
  };
}

function locationLifecycleFixture(options = {}) {
  const listeners = {};
  const statuses = [];
  const posts = [];
  const watchers = new Map();
  const cleared = [];
  const postRequests = [];
  let nextWatchId = 1;
  const requestId = options.requestId || 42;
  let eligibility = options.eligibility === undefined ? {
    request_id: requestId,
    viewer_can_update: true,
    tracking_active: true,
    request_status: 'Accepted',
    consultation_mode: 'visit',
    visit_status: 'en_route',
  } : options.eligibility;
  let eligibilityChecks = 0;
  const permission = {
    state: options.permissionState || 'granted',
    addEventListener() {},
    removeEventListener() {},
  };
  const runtime = {
    addEventListener(type, listener) { listeners[type] = listener; },
    removeEventListener(type, listener) {
      if (listeners[type] === listener) delete listeners[type];
    },
  };
  const navigator = {
    permissions: { query: async () => permission },
    geolocation: {
      watchPosition(success, failure) {
        const id = nextWatchId;
        nextWatchId += 1;
        watchers.set(id, { success, failure, active: true });
        return id;
      },
      clearWatch(id) {
        cleared.push(id);
        if (watchers.has(id)) watchers.get(id).active = false;
      },
    },
  };
  const client = locationClient.create({
    window: runtime,
    navigator,
    requestId,
    active: options.active !== false,
    updateUrl: '/home_nakes/update_visit_location',
    eligibilityCheck() {
      eligibilityChecks += 1;
      return eligibility;
    },
    minIntervalMs: 1000,
    fetch(url, fetchOptions) {
      posts.push({ url, options: fetchOptions });
      if (postRequests.length > 0) return postRequests.shift().promise;
      return Promise.resolve({ ok: true, status: 200, json: async () => ({ status: 'success', tracking_active: true }) });
    },
    setStatus(message, isError) { statuses.push({ message, isError }); },
  });
  return {
    client,
    listeners,
    statuses,
    posts,
    watchers,
    cleared,
    postRequests,
    setEligibility(value) { eligibility = value; },
    eligibilityChecks() { return eligibilityChecks; },
    activeWatcherCount() { return Array.from(watchers.values()).filter((watcher) => watcher.active).length; },
    latestWatcher() { return watchers.get(nextWatchId - 1); },
  };
}

function dashboardLocationFixture(options = {}) {
  const listeners = {};
  const watchers = new Map();
  const posts = [];
  const mutations = [];
  const postRequests = [];
  const permissionRequests = new Map();
  const eligibility = new Map();
  let nextWatchId = 1;

  function eligible(requestId, overrides = {}) {
    return Object.assign({
      request_id: requestId,
      viewer_can_update: true,
      tracking_active: true,
      request_status: 'Accepted',
      consultation_mode: 'visit',
      visit_status: 'en_route',
    }, overrides);
  }

  const runtime = {
    addEventListener(type, listener) { listeners[type] = listener; },
    removeEventListener(type, listener) {
      if (listeners[type] === listener) delete listeners[type];
    },
  };

  const manager = locationClient.createManager({
    createRuntime(requestId) {
      const navigator = {
        permissions: options.noPermissions ? undefined : {
          query() {
            if (permissionRequests.has(requestId)) return permissionRequests.get(requestId).promise;
            return Promise.resolve({ state: 'granted', addEventListener() {}, removeEventListener() {} });
          },
        },
        geolocation: {
          watchPosition(success, failure) {
            const id = nextWatchId;
            nextWatchId += 1;
            watchers.set(id, { id, requestId, success, failure, active: true });
            return id;
          },
          clearWatch(id) {
            if (watchers.has(id)) watchers.get(id).active = false;
          },
        },
      };
      return locationClient.create({
        window: runtime,
        navigator,
        requestId,
        active: true,
        revalidateOnStart: true,
        updateUrl: '/home_nakes/update_visit_location',
        eligibilityCheck() { return eligibility.has(requestId) ? eligibility.get(requestId) : eligible(requestId); },
        minIntervalMs: 1000,
        fetch(url, fetchOptions) {
          posts.push({ requestId, url, options: fetchOptions });
          if (postRequests.length > 0) return postRequests.shift().promise;
          return Promise.resolve({ ok: true, status: 200, json: async () => ({ status: 'success', tracking_active: true }) });
        },
        onResult(payload) { mutations.push({ type: 'result', requestId, payload }); },
        setStatus(message, isError) { mutations.push({ type: 'status', requestId, message, isError }); },
      });
    },
  });

  return {
    manager,
    listeners,
    watchers,
    posts,
    mutations,
    postRequests,
    permissionRequests,
    eligible,
    setEligibility(requestId, value) { eligibility.set(requestId, value); },
    dispatch(type, event) { if (listeners[type]) listeners[type](event); },
    activeWatcherCount() { return Array.from(watchers.values()).filter((watcher) => watcher.active).length; },
    latestWatcher() { return watchers.get(nextWatchId - 1); },
  };
}

function fakeForm() {
  const listeners = {};
  return {
    listeners,
    addEventListener(type, listener) { listeners[type] = listener; },
    removeEventListener(type) { delete listeners[type]; },
  };
}

function fakeWindow() {
  const listeners = {};
  return {
    listeners,
    addEventListener(type, listener) { listeners[type] = listener; },
    removeEventListener(type) { delete listeners[type]; },
  };
}

(async () => {
  const granted = locationFixture('granted');
  expect(granted.client.start() === true, 'active_visit_permission_check_started');
  await tick();
  expect(granted.watchCount() === 1 && granted.client.isActive(), 'active_visit_granted_starts_automatically');
  granted.success({ coords: { latitude: -6.1, longitude: 106.1, accuracy: 5, heading: null, speed: null } });
  await tick();
  await tick();
  expect(granted.posts.length === 1 && granted.posts[0].options.body.includes('request_id=42'), 'location_post_is_bound_to_server_rendered_request');

  const inactive = locationFixture('granted', false);
  expect(inactive.client.start() === false && inactive.watchCount() === 0, 'non_visit_or_completed_runtime_stays_inactive');

  const denied = locationFixture('denied');
  denied.client.start();
  await tick();
  expect(denied.watchCount() === 0 && denied.statuses.some((item) => item.message.includes('pengaturan browser')), 'denied_permission_is_nonblocking_without_prompt');

  const prompt = locationFixture('prompt');
  prompt.client.start();
  await tick();
  expect(prompt.watchCount() === 0 && prompt.statuses.some((item) => item.message.includes('pengaturan browser')), 'prompt_permission_waits_without_passive_browser_prompt');

  const noPermissions = locationFixture('granted');
  delete noPermissions.client;
  let unavailableWatchCount = 0;
  const unavailableClient = locationClient.create({
	window: { addEventListener() {}, removeEventListener() {} },
	navigator: { geolocation: { watchPosition() { unavailableWatchCount += 1; } } },
	requestId: 42,
	active: true,
	updateUrl: '/home_nakes/update_visit_location',
	fetch: async () => ({ ok: true, status: 200, json: async () => ({}) }),
	setStatus(message, isError) { noPermissions.statuses.push({ message, isError }); },
  });
  expect(unavailableClient.start() === false && unavailableWatchCount === 0 && noPermissions.statuses.some((item) => item.message === 'Lokasi tidak tersedia.'), 'missing_permissions_api_degrades_without_passive_prompt');

  const pendingPost = deferred();
  let overlappingPosts = 0;
  let overlapSuccess = null;
  const overlapClient = locationClient.create({
    window: { addEventListener() {}, removeEventListener() {} },
    navigator: {
      permissions: { query: async () => ({ state: 'granted', addEventListener() {}, removeEventListener() {} }) },
      geolocation: { watchPosition(success) { overlapSuccess = success; return 1; }, clearWatch() {} },
    },
    requestId: 77,
    active: true,
    updateUrl: '/home_nakes/update_visit_location',
    minIntervalMs: 1000,
    fetch() { overlappingPosts += 1; return pendingPost.promise; },
  });
  overlapClient.start();
  await tick();
  const position = { coords: { latitude: -6.2, longitude: 106.2 } };
  overlapSuccess(position);
  overlapSuccess(position);
  await tick();
  expect(overlappingPosts === 1 && overlapClient.isInFlight(), 'duplicate_location_request_is_suppressed');
  pendingPost.resolve({ ok: true, status: 200, json: async () => ({ status: 'success' }) });
  await tick();
  await tick();

  const ended = locationFixture('granted');
  ended.client.start();
  await tick();
  ended.listeners.pagehide({ persisted: false });
  expect(ended.clearCount() === 1 && !ended.client.isActive(), 'pagehide_stops_location_runtime');

  const stale = locationFixture('granted');
  stale.client.start();
  await tick();
  stale.success({ coords: { latitude: -6.3, longitude: 106.3 } });
  await tick();
  await tick();
  expect(stale.posts.length === 1 && stale.posts[0].options.body.includes('request_id=42'), 'location_runtime_cannot_switch_to_another_request');

  const bfcache = locationLifecycleFixture();
  bfcache.client.start();
  await flush();
  expect(bfcache.activeWatcherCount() === 1, 'location_bfcache_fixture_starts_one_watcher');
  bfcache.listeners.pagehide({ persisted: true });
  expect(bfcache.activeWatcherCount() === 0 && !bfcache.client.isActive(), 'persisted_pagehide_suspends_location_watcher');
  bfcache.listeners.pageshow({ persisted: true });
  await flush();
  expect(bfcache.eligibilityChecks() === 1 && bfcache.activeWatcherCount() === 1 && bfcache.watchers.size === 2, 'persisted_pageshow_revalidates_and_restarts_once');
  bfcache.listeners.pageshow({ persisted: true });
  await flush();
  expect(bfcache.activeWatcherCount() === 1 && bfcache.watchers.size === 2, 'repeated_persisted_pageshow_does_not_duplicate_watcher');

  const completedBeforeRestore = locationLifecycleFixture();
  completedBeforeRestore.client.start();
  await flush();
  completedBeforeRestore.listeners.pagehide({ persisted: true });
  completedBeforeRestore.setEligibility(false);
  completedBeforeRestore.listeners.pageshow({ persisted: true });
  await flush();
  expect(completedBeforeRestore.eligibilityChecks() === 1 && completedBeforeRestore.activeWatcherCount() === 0, 'completed_visit_does_not_restart_after_bfcache');

  const wrongRequestRestore = locationLifecycleFixture({
    eligibility: {
      request_id: 99,
      viewer_can_update: true,
      tracking_active: true,
      request_status: 'Accepted',
      consultation_mode: 'visit',
      visit_status: 'en_route',
    },
  });
  wrongRequestRestore.client.start();
  await flush();
  wrongRequestRestore.listeners.pagehide({ persisted: true });
  wrongRequestRestore.listeners.pageshow({ persisted: true });
  await flush();
  expect(wrongRequestRestore.activeWatcherCount() === 0, 'bfcache_restore_rejects_eligibility_for_another_request');

  const nonVisitRestore = locationLifecycleFixture({
    eligibility: {
      request_id: 42,
      viewer_can_update: true,
      tracking_active: true,
      request_status: 'Accepted',
      consultation_mode: 'non_visit',
      visit_status: 'not_started',
    },
  });
  nonVisitRestore.client.start();
  await flush();
  nonVisitRestore.listeners.pagehide({ persisted: true });
  nonVisitRestore.listeners.pageshow({ persisted: true });
  await flush();
  expect(nonVisitRestore.activeWatcherCount() === 0, 'non_visit_does_not_restart_after_bfcache');

  const staleSuccess = locationLifecycleFixture();
  const staleSuccessRequest = deferred();
  staleSuccess.postRequests.push(staleSuccessRequest);
  staleSuccess.client.start();
  await flush();
  staleSuccess.latestWatcher().success({ coords: { latitude: -6.4, longitude: 106.4 } });
  await tick();
  staleSuccess.listeners.pagehide({ persisted: true });
  const staleSuccessStatusCount = staleSuccess.statuses.length;
  staleSuccessRequest.resolve({ ok: true, status: 200, json: async () => ({ status: 'success', tracking_active: true }) });
  await flush();
  expect(staleSuccess.statuses.length === staleSuccessStatusCount && !staleSuccess.statuses.some((item) => item.message === 'Lokasi dibagikan.'), 'stopped_runtime_ignores_stale_location_success');

  const staleFailure = locationLifecycleFixture();
  const staleFailureRequest = deferred();
  staleFailure.postRequests.push(staleFailureRequest);
  staleFailure.client.start();
  await flush();
  staleFailure.latestWatcher().success({ coords: { latitude: -6.5, longitude: 106.5 } });
  await tick();
  staleFailure.listeners.pagehide({ persisted: true });
  staleFailure.listeners.pageshow({ persisted: true });
  await flush();
  const restartedStatusCount = staleFailure.statuses.length;
  staleFailureRequest.reject(new Error('offline'));
  await flush();
  expect(staleFailure.client.isActive() && staleFailure.activeWatcherCount() === 1 && staleFailure.statuses.length === restartedStatusCount, 'restarted_runtime_ignores_stale_location_failure');

  const normalNavigation = locationLifecycleFixture();
  normalNavigation.client.start();
  await flush();
  normalNavigation.listeners.pagehide({ persisted: false });
  const normalWatchCount = normalNavigation.watchers.size;
  if (normalNavigation.listeners.pageshow) normalNavigation.listeners.pageshow({ persisted: true });
  await flush();
  expect(!normalNavigation.client.isActive() && normalNavigation.watchers.size === normalWatchCount, 'normal_pagehide_permanently_cleans_location_runtime');

  const dashboardBfcache = dashboardLocationFixture();
  dashboardBfcache.manager.start(42);
  await flush();
  const dashboardOldWatcher = dashboardBfcache.latestWatcher();
  expect(dashboardBfcache.activeWatcherCount() === 1, 'dashboard_location_manager_starts_one_watcher');
  dashboardBfcache.dispatch('pagehide', { persisted: true });
  expect(dashboardBfcache.activeWatcherCount() === 0, 'dashboard_persisted_pagehide_invalidates_old_watcher');
  dashboardBfcache.dispatch('pageshow', { persisted: true });
  await flush();
  expect(dashboardBfcache.activeWatcherCount() === 1 && dashboardBfcache.watchers.size === 2, 'dashboard_persisted_pageshow_revalidates_and_restarts_once');
  dashboardBfcache.dispatch('pageshow', { persisted: true });
  await flush();
  expect(dashboardBfcache.activeWatcherCount() === 1 && dashboardBfcache.watchers.size === 2, 'dashboard_repeated_pageshow_keeps_single_watcher');
  const oldWatcherPostCount = dashboardBfcache.posts.length;
  dashboardOldWatcher.success({ coords: { latitude: -6.6, longitude: 106.6 } });
  await flush();
  expect(dashboardBfcache.posts.length === oldWatcherPostCount, 'dashboard_old_geolocation_callback_is_inert_after_restore');

  const dashboardStaleSuccess = dashboardLocationFixture();
  const dashboardOldSuccessRequest = deferred();
  dashboardStaleSuccess.postRequests.push(dashboardOldSuccessRequest);
  dashboardStaleSuccess.manager.start(42);
  await flush();
  dashboardStaleSuccess.latestWatcher().success({ coords: { latitude: -6.7, longitude: 106.7 } });
  await tick();
  dashboardStaleSuccess.dispatch('pagehide', { persisted: true });
  dashboardStaleSuccess.dispatch('pageshow', { persisted: true });
  await flush();
  const dashboardSuccessMutationCount = dashboardStaleSuccess.mutations.length;
  dashboardOldSuccessRequest.resolve({ ok: true, status: 200, json: async () => ({ status: 'success', tracking_active: true }) });
  await flush();
  expect(dashboardStaleSuccess.mutations.length === dashboardSuccessMutationCount && dashboardStaleSuccess.activeWatcherCount() === 1, 'dashboard_stale_ajax_success_cannot_mutate_restored_runtime');

  const dashboardStaleFailure = dashboardLocationFixture();
  const dashboardOldFailureRequest = deferred();
  dashboardStaleFailure.postRequests.push(dashboardOldFailureRequest);
  dashboardStaleFailure.manager.start(42);
  await flush();
  dashboardStaleFailure.latestWatcher().success({ coords: { latitude: -6.8, longitude: 106.8 } });
  await tick();
  dashboardStaleFailure.dispatch('pagehide', { persisted: true });
  dashboardStaleFailure.dispatch('pageshow', { persisted: true });
  await flush();
  const dashboardFailureMutationCount = dashboardStaleFailure.mutations.length;
  dashboardOldFailureRequest.reject(new Error('offline'));
  await flush();
  expect(dashboardStaleFailure.mutations.length === dashboardFailureMutationCount && dashboardStaleFailure.activeWatcherCount() === 1, 'dashboard_stale_ajax_failure_cannot_mutate_restored_runtime');

  const dashboardStalePermission = dashboardLocationFixture();
  const oldPermissionRequest = deferred();
  dashboardStalePermission.permissionRequests.set(42, oldPermissionRequest);
  dashboardStalePermission.manager.start(42);
  await flush();
  dashboardStalePermission.manager.start(43);
  await flush();
  oldPermissionRequest.resolve({ state: 'granted', addEventListener() {}, removeEventListener() {} });
  await flush();
  expect(dashboardStalePermission.activeWatcherCount() === 1 && dashboardStalePermission.latestWatcher().requestId === 43, 'dashboard_stale_permission_result_cannot_restart_old_request');

  const dashboardRequestSwitch = dashboardLocationFixture();
  dashboardRequestSwitch.manager.start(42);
  await flush();
  const requestAWatcher = dashboardRequestSwitch.latestWatcher();
  dashboardRequestSwitch.manager.start(43);
  await flush();
  const requestSwitchPostCount = dashboardRequestSwitch.posts.length;
  requestAWatcher.success({ coords: { latitude: -6.9, longitude: 106.9 } });
  await flush();
  expect(dashboardRequestSwitch.posts.length === requestSwitchPostCount && dashboardRequestSwitch.latestWatcher().requestId === 43, 'dashboard_request_switch_ignores_old_request_coordinates');

  const dashboardOldStop = dashboardLocationFixture();
  dashboardOldStop.manager.start(42);
  await flush();
  const oldManagerGeneration = dashboardOldStop.manager.generation();
  dashboardOldStop.manager.stop(42, oldManagerGeneration);
  dashboardOldStop.manager.start(42);
  await flush();
  expect(dashboardOldStop.manager.stop(42, oldManagerGeneration) === false && dashboardOldStop.activeWatcherCount() === 1, 'dashboard_old_generation_cannot_stop_new_watcher');

  const dashboardCompleted = dashboardLocationFixture();
  dashboardCompleted.setEligibility(42, dashboardCompleted.eligible(42, { tracking_active: false, visit_status: 'completed' }));
  dashboardCompleted.manager.start(42);
  await flush();
  expect(dashboardCompleted.activeWatcherCount() === 0, 'dashboard_completed_visit_never_starts_watcher');

  const dashboardNonVisit = dashboardLocationFixture();
  dashboardNonVisit.setEligibility(42, dashboardNonVisit.eligible(42, { consultation_mode: 'non_visit', visit_status: 'not_started' }));
  dashboardNonVisit.manager.start(42);
  await flush();
  expect(dashboardNonVisit.activeWatcherCount() === 0, 'dashboard_non_visit_never_starts_watcher');

  const dashboardNoPermissions = dashboardLocationFixture({ noPermissions: true });
  dashboardNoPermissions.manager.start(42);
  await flush();
  expect(dashboardNoPermissions.activeWatcherCount() === 0 && dashboardNoPermissions.mutations.some((item) => item.message === 'Lokasi tidak tersedia.'), 'dashboard_missing_permissions_api_remains_passive');

  const partnerName = { textContent: '' };
  const callName = { textContent: '' };
  const partnerImage = { src: '', alt: '' };
  const callImage = { src: '', alt: '' };
  const participant = chatParticipantClient.create({
	nameElements: [partnerName, callName],
	imageElements: [partnerImage, callImage],
	fallbackName: 'Tenaga kesehatan',
	fallbackPhotoUrl: '/assets/doclinc_ui/chat/doctor-placeholder.jpg',
	isSafePhotoUrl(value) { return /^\/profile\/photo\/[1-9][0-9]*$/.test(value); },
  });
  participant.update({ name: 'Dokter A', photo_url: '/profile/photo/101', neutral: false });
  expect(partnerName.textContent === 'Dokter A' && callName.textContent === 'Dokter A' && partnerImage.src === '/profile/photo/101', 'initial_server_participant_updates_thread_context');
  participant.update({ name: 'Dokter B', photo_url: '/profile/photo/102', neutral: false });
  expect(partnerName.textContent === 'Dokter B' && partnerImage.src === '/profile/photo/102', 'polling_can_replace_clinician_context');
  participant.update({ name: 'Tenaga kesehatan', photo_url: '', neutral: true });
  expect(partnerName.textContent === 'Tenaga kesehatan' && partnerImage.src.includes('doctor-placeholder.jpg') && partnerImage.alt === 'Foto Tenaga kesehatan', 'multiple_clinicians_reset_to_neutral_context');

  const chatResponses = [deferred(), deferred()];
  let chatRequestIndex = 0;
  let chatState = { participant: '', messages: [] };
  const orderedChat = chatParticipantClient.createPoller({
    singleFlight: false,
    request() { return chatResponses[chatRequestIndex++].promise; },
    apply(data) { chatState = { participant: data.participant, messages: data.messages.slice() }; },
  });
  const olderChatPoll = orderedChat.poll();
  const newerChatPoll = orderedChat.poll();
  await tick();
  chatResponses[1].resolve({ participant: 'Dokter B', messages: ['pesan-baru'] });
  await newerChatPoll;
  chatResponses[0].resolve({ participant: 'Dokter A', messages: ['pesan-lama'] });
  await olderChatPoll;
  expect(chatState.participant === 'Dokter B', 'out_of_order_chat_response_cannot_restore_old_participant');
  expect(chatState.messages.length === 1 && chatState.messages[0] === 'pesan-baru', 'out_of_order_chat_response_cannot_restore_old_messages');

  const neutralResponses = [deferred(), deferred()];
  let neutralRequestIndex = 0;
  let neutralState = '';
  const neutralChat = chatParticipantClient.createPoller({
    singleFlight: false,
    request() { return neutralResponses[neutralRequestIndex++].promise; },
    apply(data) { neutralState = data.participant; },
  });
  const oldNamedPoll = neutralChat.poll();
  const newNeutralPoll = neutralChat.poll();
  await tick();
  neutralResponses[1].resolve({ participant: 'Tenaga kesehatan' });
  await newNeutralPoll;
  neutralResponses[0].resolve({ participant: 'Dokter A' });
  await oldNamedPoll;
  expect(neutralState === 'Tenaga kesehatan', 'stale_chat_response_cannot_replace_neutral_multi_clinician_context');

  const singleFlightResponse = deferred();
  let singleFlightRequests = 0;
  const singleFlightChat = chatParticipantClient.createPoller({
    request() { singleFlightRequests += 1; return singleFlightResponse.promise; },
    apply() {},
  });
  const activeChatPoll = singleFlightChat.poll();
  await tick();
  const skippedChatPoll = await singleFlightChat.poll();
  expect(singleFlightRequests === 1 && skippedChatPoll.started === false && singleFlightChat.isInFlight(), 'chat_polling_is_single_flight');
  singleFlightResponse.resolve({});
  await activeChatPoll;

  const retryResponses = [deferred(), deferred()];
  let retryRequestIndex = 0;
  let retryState = 'Dokter terakhir';
  const retryChat = chatParticipantClient.createPoller({
    request() { return retryResponses[retryRequestIndex++].promise; },
    apply(data) { retryState = data.participant; },
  });
  const failedChatPoll = retryChat.poll();
  await tick();
  retryResponses[0].reject(new Error('offline'));
  await failedChatPoll;
  expect(retryState === 'Dokter terakhir' && !retryChat.isInFlight(), 'chat_poll_failure_preserves_last_known_state_and_clears_inflight');
  const retriedChatPoll = retryChat.poll();
  await tick();
  retryResponses[1].resolve({ participant: 'Dokter setelah tersambung' });
  await retriedChatPoll;
  expect(retryState === 'Dokter setelah tersambung', 'chat_poll_can_continue_after_failure');

  const chatMessages = chatParticipantClient.createMessageRegistry();
  expect(chatMessages.accept(501) && !chatMessages.accept(501) && chatMessages.accept(502), 'chat_message_registry_suppresses_send_poll_duplicates');

  const form = fakeForm();
  const runtime = fakeWindow();
  const values = { diagnosis: 'Tetap di formulir' };
  const dirtyChanges = [];
  const guard = formGuard.create({ window: runtime, form, onDirtyChange(value) { dirtyChanges.push(value); } });
  expect(guard.start() === true, 'clinical_guard_starts');
  form.listeners.input({ target: { type: 'text', value: values.diagnosis } });
  expect(guard.isDirty() && dirtyChanges[0] === true, 'clinical_input_sets_dirty_state');
  const unloadEvent = { prevented: false, preventDefault() { this.prevented = true; }, returnValue: null };
  runtime.listeners.beforeunload(unloadEvent);
  expect(unloadEvent.prevented && unloadEvent.returnValue === '', 'dirty_form_enables_beforeunload_protection');
  expect(guard.beginSubmission() && !guard.beginSubmission(), 'clinical_double_submit_is_blocked');
  guard.submissionFailed();
  expect(!guard.isSubmitting() && guard.isDirty() && values.diagnosis === 'Tetap di formulir', 'failed_save_retains_values_and_allows_retry');
  expect(guard.beginSubmission(), 'failed_save_can_retry');
  guard.submissionSucceeded();
  expect(!guard.isDirty() && !guard.isSubmitting() && dirtyChanges.at(-1) === false, 'successful_save_clears_dirty_state');

  const guardSource = require('fs').readFileSync(require('path').join(__dirname, '../../assets/js/doclinc-clinical-form-guard.js'), 'utf8');
  expect(!/localStorage|sessionStorage|indexedDB|document\.cookie|location\.search/i.test(guardSource), 'clinical_guard_has_no_browser_persistence');

  process.stdout.write(`PHASE7_CLIENT_PASS=${passed}\n`);
  process.stdout.write(`PHASE7_CLIENT_FAIL=${failed}\n`);
  process.exit(failed === 0 ? 0 : 1);
})().catch((error) => {
  process.stderr.write(`${error && error.stack ? error.stack : error}\n`);
  process.exit(1);
});
