(function(root, factory) {
    var api = factory(root);
    if (typeof module === 'object' && module.exports) {
        module.exports = api;
    } else {
        root.DoclincNakesPresence = api;
        if (root.document) {
            root.document.addEventListener('DOMContentLoaded', function() {
                api.start(root.DoclincNakesPresenceConfig || {});
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
        var mode = ['heartbeat', 'monitor', 'both'].indexOf(raw.mode) >= 0 ? raw.mode : 'disabled';
        return {
            enabled: raw.enabled === true,
            mode: mode,
            heartbeatUrl: typeof raw.heartbeatUrl === 'string' ? raw.heartbeatUrl : '',
            snapshotUrl: typeof raw.snapshotUrl === 'string' ? raw.snapshotUrl : '',
            heartbeatIntervalMs: positiveInteger(raw.heartbeatIntervalMs, 30000, 15000),
            snapshotIntervalMs: positiveInteger(raw.snapshotIntervalMs, 30000, 15000),
            containerId: typeof raw.containerId === 'string' && raw.containerId ? raw.containerId : 'doclincNakesPresence'
        };
    }

    function requestJson(url, options) {
        if (!url || typeof root.fetch !== 'function') {
            return Promise.reject(new Error('presence_transport_unavailable'));
        }
        var requestOptions = Object.assign({
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'Accept': 'application/json' }
        }, options || {});
        return root.fetch(url, requestOptions).then(function(response) {
            return response.json().catch(function() {
                throw new Error('presence_response_invalid');
            }).then(function(body) {
                if (!response.ok || !body || body.success !== true) {
                    throw new Error('presence_request_failed');
                }
                return body.data || {};
            });
        });
    }

    function clearNode(node) {
        while (node && node.firstChild) {
            node.removeChild(node.firstChild);
        }
    }

    function element(documentRef, tag, className, text) {
        var node = documentRef.createElement(tag);
        if (className) {
            node.className = className;
        }
        if (typeof text === 'string') {
            node.textContent = text;
        }
        return node;
    }

    function render(container, data) {
        if (!container || !container.ownerDocument) {
            return false;
        }
        var documentRef = container.ownerDocument;
        var rows = data && Array.isArray(data.rows) ? data.rows : [];
		if (container.getAttribute && container.getAttribute('data-presence-roster') === 'true') {
			return renderRoster(container, data || {}, rows);
		}
        clearNode(container);
        container.setAttribute('aria-busy', 'false');

        var summary = element(documentRef, 'div', 'doclinc-presence-summary');
        summary.appendChild(element(documentRef, 'span', 'doclinc-presence-count doclinc-presence-count--online', String(data.online_count || 0) + ' online'));
        summary.appendChild(element(documentRef, 'span', 'doclinc-presence-count doclinc-presence-count--offline', String(data.offline_count || 0) + ' offline'));
        container.appendChild(summary);

        if (!rows.length) {
            container.appendChild(element(documentRef, 'div', 'doclinc-presence-empty', 'Belum ada akun Nakes personal aktif.'));
            return true;
        }

        var list = element(documentRef, 'div', 'doclinc-presence-list');
        rows.forEach(function(row) {
            var online = row && row.is_online === true;
            var item = element(documentRef, 'div', 'doclinc-presence-item');
            item.setAttribute('data-user-id', String(row.user_id || ''));
            var identity = element(documentRef, 'div', 'doclinc-presence-identity');
            identity.appendChild(element(documentRef, 'strong', '', (row.display_name || '').trim() || 'Nama belum diisi'));
            var meta = [];
            if (row.profession) {
                meta.push(String(row.profession));
            }
            if (row.puskesmas_name) {
                meta.push(String(row.puskesmas_name));
            } else if (row.puskesmas_code) {
                meta.push(String(row.puskesmas_code));
            }
            identity.appendChild(element(documentRef, 'span', '', meta.join(' · ') || 'Nakes'));
            item.appendChild(identity);

            var state = element(documentRef, 'div', 'doclinc-presence-state');
            state.appendChild(element(documentRef, 'span', online ? 'doclinc-presence-dot is-online' : 'doclinc-presence-dot is-offline'));
            state.appendChild(element(documentRef, 'strong', online ? 'text-success' : 'text-muted', online ? 'Online' : 'Offline'));
            if (!online) {
                state.appendChild(element(documentRef, 'small', '', row.last_seen_at ? 'Terakhir ' + row.last_seen_at : 'Belum pernah online'));
            }
            item.appendChild(state);
            list.appendChild(item);
        });
        container.appendChild(list);
        return true;
    }

	function renderRoster(container, data, rows) {
		var documentRef = container.ownerDocument;
		var summary = container.querySelector('[data-presence-summary]');
		if (summary) {
			clearNode(summary);
			summary.appendChild(element(documentRef, 'span', 'doclinc-presence-count doclinc-presence-count--online', String(data.online_count || 0) + ' online'));
			summary.appendChild(element(documentRef, 'span', 'doclinc-presence-count doclinc-presence-count--offline', String(data.offline_count || 0) + ' offline'));
		}
		var byUserId = {};
		rows.forEach(function(row) {
			var userId = row && row.user_id ? String(row.user_id) : '';
			if (userId) {
				byUserId[userId] = row;
			}
		});
		var rosterRows = container.querySelectorAll('[data-presence-user-id]');
		Array.prototype.forEach.call(rosterRows, function(item) {
			var row = byUserId[String(item.getAttribute('data-presence-user-id') || '')] || null;
			var online = !!(row && row.is_online === true);
			var dot = item.querySelector('[data-presence-dot]');
			var label = item.querySelector('[data-presence-label]');
			var lastSeen = item.querySelector('[data-presence-last-seen]');
			if (dot) {
				dot.className = 'doclinc-presence-dot ' + (online ? 'is-online' : 'is-offline');
			}
			if (label) {
				label.className = 'nk-staff-presence ' + (online ? 'is-online' : 'is-offline');
				label.textContent = online ? 'Online' : 'Offline';
			}
			if (lastSeen) {
				lastSeen.textContent = online ? 'Aktif sekarang' : (row && row.last_seen_at ? 'Terakhir ' + row.last_seen_at : 'Belum pernah online');
			}
		});
		container.setAttribute('aria-busy', 'false');
		return true;
	}

    function renderError(container) {
        if (!container || !container.ownerDocument) {
            return;
        }
		container.setAttribute('aria-busy', 'false');
		if (container.getAttribute && container.getAttribute('data-presence-roster') === 'true') {
			return;
		}
        clearNode(container);
        container.appendChild(element(container.ownerDocument, 'div', 'doclinc-presence-empty text-muted', 'Status Nakes belum dapat dimuat. Akan dicoba lagi.'));
    }

    function create(rawConfig) {
        var config = normalizedConfig(rawConfig);
        var stopped = false;
        var heartbeatTimer = null;
        var snapshotTimer = null;
        var heartbeatPending = false;
        var snapshotPending = false;
        var container = root.document ? root.document.getElementById(config.containerId) : null;

        function visible() {
            return !root.document || root.document.visibilityState !== 'hidden';
        }

        function heartbeat() {
            if (stopped || heartbeatPending || !visible()) {
                return Promise.resolve(false);
            }
            heartbeatPending = true;
            return requestJson(config.heartbeatUrl, { method: 'POST' })
                .then(function() { return true; })
                .catch(function() { return false; })
                .finally(function() { heartbeatPending = false; });
        }

        function snapshot() {
            if (stopped || snapshotPending || !visible()) {
                return Promise.resolve(false);
            }
            snapshotPending = true;
            if (container) {
                container.setAttribute('aria-busy', 'true');
            }
            return requestJson(config.snapshotUrl, { method: 'GET' })
                .then(function(data) {
                    if (!stopped) {
                        render(container, data);
                    }
                    return true;
                })
                .catch(function() {
                    if (!stopped) {
                        renderError(container);
                    }
                    return false;
                })
                .finally(function() { snapshotPending = false; });
        }

        function onVisibilityChange() {
            if (!visible() || stopped) {
                return;
            }
            if (config.mode === 'heartbeat' || config.mode === 'both') {
                heartbeat();
            }
            if (config.mode === 'monitor' || config.mode === 'both') {
                snapshot();
            }
        }

        function teardown() {
            if (stopped) {
                return false;
            }
            stopped = true;
            if (heartbeatTimer !== null) {
                root.clearInterval(heartbeatTimer);
            }
            if (snapshotTimer !== null) {
                root.clearInterval(snapshotTimer);
            }
            if (root.document) {
                root.document.removeEventListener('visibilitychange', onVisibilityChange);
            }
            root.removeEventListener('pagehide', teardown);
            return true;
        }

        function begin() {
            if (!config.enabled || config.mode === 'disabled') {
                return false;
            }
            if (root.document) {
                root.document.addEventListener('visibilitychange', onVisibilityChange);
            }
            root.addEventListener('pagehide', teardown);
            if (config.mode === 'heartbeat' || config.mode === 'both') {
                heartbeat();
                heartbeatTimer = root.setInterval(heartbeat, config.heartbeatIntervalMs);
            }
            if (config.mode === 'monitor' || config.mode === 'both') {
                snapshot();
                snapshotTimer = root.setInterval(snapshot, config.snapshotIntervalMs);
            }
            return true;
        }

        return { begin: begin, heartbeat: heartbeat, snapshot: snapshot, teardown: teardown, render: render, config: config };
    }

    function start(config) {
        if (runtime) {
            return runtime;
        }
        runtime = create(config);
        runtime.begin();
        return runtime;
    }

    function resetForTests() {
        if (runtime) {
            runtime.teardown();
        }
        runtime = null;
    }

    return { create: create, start: start, render: render, normalizedConfig: normalizedConfig, resetForTests: resetForTests };
});
