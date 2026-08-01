(function (root, factory) {
    var api = factory();
    if (typeof module === 'object' && module.exports) {
        module.exports = api;
    }
    if (root) {
        root.DoclincPicAssignment = api;
    }
})(typeof window !== 'undefined' ? window : globalThis, function () {
    'use strict';

    function positiveInteger(value) {
        var normalized = String(value == null ? '' : value).trim();
        if (!/^[1-9][0-9]*$/.test(normalized)) {
            return 0;
        }
        var parsed = Number(normalized);
        return Number.isSafeInteger(parsed) && parsed > 0 ? parsed : 0;
    }

    function assignmentLabel(assignment) {
        if (!assignment || positiveInteger(assignment.staff_id) < 1 || !String(assignment.staff_name || '').trim()) {
            return 'Belum ditentukan';
        }
        var label = String(assignment.staff_name).trim();
        var profession = String(assignment.staff_profession || '').trim();
        return profession ? label + ' · ' + profession : label;
    }

    function create(options) {
        options = options || {};
        var documentRoot = options.documentRoot;
        var windowObject = options.windowObject;
        var fetchImpl = options.fetchImpl;
        var formDataFactory = options.formDataFactory || function (form) { return new FormData(form); };
        var started = false;
        var released = false;
        var handlers = [];
        var inFlight = new Map();

        if (!documentRoot || !windowObject || typeof fetchImpl !== 'function'
            || typeof documentRoot.querySelectorAll !== 'function') {
            return null;
        }

        function requestSelector(requestId) {
            return '[data-request-id="' + String(requestId) + '"]';
        }

        function matchingRoots(requestId) {
            return Array.prototype.slice.call(documentRoot.querySelectorAll(requestSelector(requestId)));
        }

        function setFeedback(requestId, message, isError) {
            matchingRoots(requestId).forEach(function (rootNode) {
                var feedback = rootNode.querySelector ? rootNode.querySelector('[data-pic-feedback]') : null;
                if (!feedback) {
                    return;
                }
                feedback.textContent = message || '';
                feedback.hidden = !message;
                if (feedback.classList && typeof feedback.classList.toggle === 'function') {
                    feedback.classList.toggle('is-error', Boolean(isError));
                }
                if (typeof feedback.setAttribute === 'function') {
                    feedback.setAttribute('role', isError ? 'alert' : 'status');
                }
            });
        }

        function updateState(requestId, assignment) {
            var selectedStaffId = assignment ? positiveInteger(assignment.staff_id) : 0;
            var label = assignmentLabel(assignment);
            var contact = assignment ? String(assignment.staff_contact || '').trim() : '';
            matchingRoots(requestId).forEach(function (rootNode) {
                Array.prototype.slice.call(rootNode.querySelectorAll ? rootNode.querySelectorAll('[data-pic-name]') : [])
                    .forEach(function (node) { node.textContent = label; });
                Array.prototype.slice.call(rootNode.querySelectorAll ? rootNode.querySelectorAll('[data-pic-contact]') : [])
                    .forEach(function (node) {
                        node.textContent = contact;
                        node.hidden = !contact;
                    });
                Array.prototype.slice.call(rootNode.querySelectorAll ? rootNode.querySelectorAll('.nk-pic-form select[name="staff_id"]') : [])
                    .forEach(function (select) { select.value = selectedStaffId ? String(selectedStaffId) : ''; });
                Array.prototype.slice.call(rootNode.querySelectorAll ? rootNode.querySelectorAll('[data-pic-submit]') : [])
                    .forEach(function (button) { button.textContent = selectedStaffId ? 'Ganti PIC' : 'Tetapkan PIC'; });
                Array.prototype.slice.call(rootNode.querySelectorAll ? rootNode.querySelectorAll('.nk-pic-clear-form') : [])
                    .forEach(function (form) { form.hidden = !selectedStaffId; });
                if (selectedStaffId) {
                    Array.prototype.slice.call(rootNode.querySelectorAll ? rootNode.querySelectorAll('.nk-pic-form input[name="note"]') : [])
                        .forEach(function (input) { input.value = ''; });
                }
            });
        }

        function lockRequest(requestId) {
            var controls = [];
            matchingRoots(requestId).forEach(function (rootNode) {
                Array.prototype.slice.call(rootNode.querySelectorAll ? rootNode.querySelectorAll('button, select, input') : [])
                    .forEach(function (element) {
                        controls.push({ element: element, disabled: Boolean(element.disabled) });
                        element.disabled = true;
                    });
            });
            return function () {
                controls.forEach(function (state) { state.element.disabled = state.disabled; });
            };
        }

        async function submitForm(event, form) {
            event.preventDefault();
            if (released) {
                return false;
            }
            if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
                if (typeof form.reportValidity === 'function') {
                    form.reportValidity();
                }
                return false;
            }

            var formData = formDataFactory(form);
            var requestId = positiveInteger(formData.get('request_id'));
            if (requestId < 1 || inFlight.has(requestId)) {
                return false;
            }

            var restore = lockRequest(requestId);
            var abortController = typeof AbortController === 'function' ? new AbortController() : null;
            inFlight.set(requestId, { restore: restore, abortController: abortController });
            setFeedback(requestId, form.classList.contains('nk-pic-clear-form') ? 'Melepas PIC...' : 'Memperbarui PIC...', false);
            try {
                var response = await fetchImpl(form.action, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    signal: abortController ? abortController.signal : undefined
                });
                var data = null;
                try {
                    data = await response.json();
                } catch (error) {
                    data = null;
                }
                if (released) {
                    return false;
                }
                var validBody = data && typeof data === 'object' && !Array.isArray(data)
                    && positiveInteger(data.request_id) === requestId;
                if (!response.ok || !validBody || data.status !== 'success') {
                    var message = validBody && typeof data.message === 'string' && data.message.trim()
                        ? data.message.trim()
                        : 'PIC belum dapat diperbarui. Coba lagi.';
                    throw new Error(message);
                }
                updateState(requestId, data.assignment || null);
                setFeedback(requestId, typeof data.message === 'string' ? data.message : 'PIC diperbarui.', false);
                return true;
            } catch (error) {
                if (!released && (!error || error.name !== 'AbortError')) {
                    setFeedback(requestId, error && error.message ? error.message : 'PIC belum dapat diperbarui. Coba lagi.', true);
                }
                return false;
            } finally {
                var operation = inFlight.get(requestId);
                if (operation) {
                    operation.restore();
                    inFlight.delete(requestId);
                }
            }
        }

        function start() {
            if (started || released) {
                return false;
            }
            started = true;
            Array.prototype.slice.call(documentRoot.querySelectorAll('.nk-pic-form, .nk-pic-clear-form'))
                .forEach(function (form) {
                    var handler = function (event) { return submitForm(event, form); };
                    form.addEventListener('submit', handler);
                    handlers.push({ form: form, handler: handler });
                });
            return true;
        }

        function release() {
            if (released) {
                return false;
            }
            released = true;
            handlers.forEach(function (entry) { entry.form.removeEventListener('submit', entry.handler); });
            handlers = [];
            inFlight.forEach(function (operation) {
                if (operation.abortController) {
                    operation.abortController.abort();
                }
                operation.restore();
            });
            inFlight.clear();
            return true;
        }

        return {
            start: start,
            release: release,
            updateState: updateState,
            isInFlight: function (requestId) { return inFlight.has(positiveInteger(requestId)); }
        };
    }

    return {
        create: create,
        assignmentLabel: assignmentLabel,
        positiveInteger: positiveInteger
    };
});
