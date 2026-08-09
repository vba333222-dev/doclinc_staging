(function(root, factory) {
    var api = factory(root);
    if (typeof module === 'object' && module.exports) {
        module.exports = api;
    } else {
        root.DoclincPuskesmasOperations = api;
        if (root.document) {
            root.document.addEventListener('DOMContentLoaded', function() {
                api.start(root.DoclincPuskesmasOperationsConfig || {});
            });
        }
    }
})(typeof window !== 'undefined' ? window : globalThis, function(root) {
    'use strict';

    var runtime = null;

    function positiveInteger(value, fallback, minimum) {
        var parsed = parseInt(value, 10);
        return Number.isFinite(parsed) && parsed >= minimum ? parsed : fallback;
    }

    function normalizedConfig(raw) {
        raw = raw && typeof raw === 'object' ? raw : {};
        return {
            enabled: raw.enabled === true,
            snapshotUrl: typeof raw.snapshotUrl === 'string' ? raw.snapshotUrl : '',
            pageUrl: typeof raw.pageUrl === 'string' ? raw.pageUrl : '',
            pollIntervalMs: positiveInteger(raw.pollIntervalMs, 30000, 15000),
            containerId: typeof raw.containerId === 'string' && raw.containerId ? raw.containerId : 'doclincPuskesmasOperations'
        };
    }

    function clearNode(node) {
        while (node && node.firstChild) {
            node.removeChild(node.firstChild);
        }
    }

    function element(documentRef, tag, className, text) {
        var node = documentRef.createElement(tag);
        if (className) node.className = className;
        if (typeof text === 'string') node.textContent = text;
        return node;
    }

    function stateLabel(state, count) {
        var labels = {
            available: 'Siap menerima tugas',
            busy: 'Menangani ' + String(count || 0) + ' tugas',
            attention: 'Offline · ' + String(count || 0) + ' tugas aktif',
            offline: 'Offline'
        };
        return labels[state] || labels.offline;
    }

    function presenceLabel(row) {
        row = row && typeof row === 'object' ? row : {};
        if (row.is_online === true) return 'Aktif sekarang';
        if (row.last_seen_age_seconds === null || row.last_seen_age_seconds === '' || typeof row.last_seen_age_seconds === 'undefined') {
            return 'Belum pernah aktif';
        }
        var age = Number(row.last_seen_age_seconds);
        if (!Number.isFinite(age) || age < 0) return 'Belum pernah aktif';
        if (age < 60) return 'Terlihat kurang dari 1 menit lalu';
        if (age < 3600) return 'Terlihat ' + String(Math.floor(age / 60)) + ' menit lalu';
        if (age < 86400) return 'Terlihat ' + String(Math.floor(age / 3600)) + ' jam lalu';
        return 'Terlihat lebih dari 1 hari lalu';
    }

    function safeData(raw) {
        raw = raw && typeof raw === 'object' ? raw : {};
        return {
            summary: raw.summary && typeof raw.summary === 'object' ? raw.summary : {},
            staff: Array.isArray(raw.staff) ? raw.staff : [],
            exceptions: Array.isArray(raw.exceptions) ? raw.exceptions : [],
            generated_at_epoch: positiveInteger(raw.generated_at_epoch, 0, 1)
        };
    }

    function section(documentRef, title, meta) {
        var wrapper = element(documentRef, 'section', 'dl-nakes-card dl-dashboard-section');
        var heading = element(documentRef, 'div', 'dl-operations-section-title');
        heading.appendChild(element(documentRef, 'strong', '', title));
        heading.appendChild(element(documentRef, 'span', '', meta));
        wrapper.appendChild(heading);
        return wrapper;
    }

    function render(container, raw) {
        if (!container || !container.ownerDocument) return false;
        var data = safeData(raw);
        var documentRef = container.ownerDocument;
        var summary = data.summary;
        clearNode(container);
        container.setAttribute('aria-busy', 'false');

        var metrics = element(documentRef, 'div', 'dl-operations-summary');
        [
            ['Menunggu diterima', summary.pending_requests],
			['Belum ada penanggung jawab', summary.unassigned_requests],
            ['Nakes siap', summary.available_staff],
            ['Tugas berjalan', summary.accepted_requests]
        ].forEach(function(item) {
            var metric = element(documentRef, 'div', 'dl-operations-metric');
            metric.appendChild(element(documentRef, 'span', '', item[0]));
            metric.appendChild(element(documentRef, 'strong', '', String(Number(item[1]) || 0)));
            metrics.appendChild(metric);
        });
        container.appendChild(metrics);

        var staffSection = section(documentRef, 'Beban tugas Nakes', String(data.staff.length) + ' Nakes aktif terhubung');
        var staffList = element(documentRef, 'div', 'dl-operations-staff');
        if (!data.staff.length) {
            staffList.appendChild(element(documentRef, 'div', 'dl-operations-empty', 'Belum ada akun Nakes personal aktif yang terhubung.'));
        } else {
            data.staff.forEach(function(row) {
                var state = ['available', 'busy', 'attention', 'offline'].indexOf(row.workload_state) >= 0 ? row.workload_state : 'offline';
                var item = element(documentRef, 'div', 'dl-operations-staff-item');
                item.setAttribute('data-staff-id', String(row.staff_id || ''));
                var identity = element(documentRef, 'div', 'dl-operations-staff-identity');
                identity.appendChild(element(documentRef, 'strong', '', String(row.display_name || 'Nama belum diisi')));
                identity.appendChild(element(documentRef, 'span', '', String(row.profession || 'Profesi belum diisi') + ' · ' + presenceLabel(row)));
                item.appendChild(identity);
                item.appendChild(element(documentRef, 'span', 'dl-operations-state is-' + state, stateLabel(state, row.active_request_count)));
                staffList.appendChild(item);
            });
        }
        staffSection.appendChild(staffList);
        container.appendChild(staffSection);

        var exceptionSection = section(documentRef, 'Perlu perhatian', String(data.exceptions.length) + ' item operasional');
        var exceptionList = element(documentRef, 'div', 'dl-operations-exceptions');
        if (!data.exceptions.length) {
			exceptionList.appendChild(element(documentRef, 'div', 'dl-operations-empty', 'Tidak ada penugasan yang perlu diperiksa.'));
        } else {
            data.exceptions.forEach(function(row) {
                var item = element(documentRef, 'div', 'dl-operations-exception-item');
                item.setAttribute('data-request-id', String(row.request_id || ''));
                var copy = element(documentRef, 'div', 'dl-operations-exception-copy');
				var title = row.type === 'ambiguous_assignment' ? 'Penugasan perlu diperiksa' : 'Penanggung jawab belum ditentukan';
                copy.appendChild(element(documentRef, 'strong', '', title + ' · Permintaan #' + String(row.request_id || '')));
                copy.appendChild(element(documentRef, 'span', '', String(row.visit_status_label || 'Belum dimulai')));
                item.appendChild(copy);
                exceptionList.appendChild(item);
            });
        }
        exceptionSection.appendChild(exceptionList);
        container.appendChild(exceptionSection);
        return true;
    }

    function renderError(container) {
        if (!container || !container.ownerDocument) return false;
        clearNode(container);
        container.setAttribute('aria-busy', 'false');
        container.appendChild(element(container.ownerDocument, 'div', 'dl-nakes-card dl-dashboard-section dl-operations-error', 'Ringkasan operasional belum dapat dimuat. Sistem akan mencoba lagi.'));
        return true;
    }

    function create(rawConfig) {
        var config = normalizedConfig(rawConfig);
        var stopped = false;
        var pending = false;
        var timer = null;
        var abortController = null;
        var container = root.document ? root.document.getElementById(config.containerId) : null;

        function visible() {
            return !root.document || root.document.visibilityState !== 'hidden';
        }

        function snapshot() {
            if (stopped || pending || !visible() || !config.snapshotUrl || typeof root.fetch !== 'function') {
                return Promise.resolve(false);
            }
            pending = true;
            if (container) container.setAttribute('aria-busy', 'true');
            abortController = typeof root.AbortController === 'function' ? new root.AbortController() : null;
            var options = {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { 'Accept': 'application/json' }
            };
            if (abortController) options.signal = abortController.signal;
            return root.fetch(config.snapshotUrl, options).then(function(response) {
                return response.json().catch(function() { throw new Error('invalid_response'); }).then(function(body) {
                    if (!response.ok || !body || body.success !== true || !body.data) throw new Error('request_failed');
                    if (!stopped) render(container, body.data);
                    return !stopped;
                });
            }).catch(function() {
                if (!stopped) renderError(container);
                return false;
            }).finally(function() {
                pending = false;
                abortController = null;
            });
        }

        function onVisibilityChange() {
            if (!stopped && visible()) snapshot();
        }

        function teardown() {
            if (stopped) return false;
            stopped = true;
            if (timer !== null) root.clearInterval(timer);
            if (abortController) abortController.abort();
            if (root.document) root.document.removeEventListener('visibilitychange', onVisibilityChange);
            root.removeEventListener('pagehide', teardown);
            return true;
        }

        function begin() {
            if (!config.enabled || !config.snapshotUrl) return false;
            if (root.document) root.document.addEventListener('visibilitychange', onVisibilityChange);
            root.addEventListener('pagehide', teardown);
            snapshot();
            timer = root.setInterval(snapshot, config.pollIntervalMs);
            return true;
        }

        return { begin: begin, snapshot: snapshot, teardown: teardown, render: render, config: config };
    }

    function start(config) {
        if (runtime) return runtime;
        runtime = create(config);
        runtime.begin();
        return runtime;
    }

    function resetForTests() {
        if (runtime) runtime.teardown();
        runtime = null;
    }

    return {
        create: create,
        start: start,
        render: render,
        renderError: renderError,
        normalizedConfig: normalizedConfig,
        safeData: safeData,
        stateLabel: stateLabel,
        presenceLabel: presenceLabel,
        resetForTests: resetForTests
    };
});
