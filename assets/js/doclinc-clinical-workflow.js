(function (root) {
    'use strict';

    if (!root || !root.document || root.__doclincClinicalWorkflowInstalled) {
        return;
    }
    root.__doclincClinicalWorkflowInstalled = true;

    var documentRef = root.document;

    function newKey() {
        if (root.crypto && typeof root.crypto.randomUUID === 'function') {
            return root.crypto.randomUUID();
        }
        var random = Math.random().toString(36).slice(2);
        return 'cw-' + Date.now().toString(36) + '-' + random + random;
    }

    function messageNode(form) {
        return form.querySelector('[data-clinical-message]');
    }

    function showMessage(form, text, isError) {
        var node = messageNode(form);
        if (!node) {
            return;
        }
        node.textContent = text || '';
        node.className = 'small mt-2 ' + (isError ? 'text-danger' : 'text-success');
        node.setAttribute('role', 'status');
        node.setAttribute('aria-live', 'polite');
        if (isError) {
            node.setAttribute('tabindex', '-1');
            if (typeof node.focus === 'function') {
                node.focus();
            }
        }
    }

    function notify(title, text, icon) {
        if (root.Swal && typeof root.Swal.fire === 'function') {
            return root.Swal.fire({ title: title, text: text, icon: icon || 'info', confirmButtonText: 'OK' });
        }
        root.alert((title ? title + '\n\n' : '') + (text || ''));
        return Promise.resolve();
    }

    function syncDispositionProfession(form) {
        var staff = form.querySelector('[data-disposition-staff]');
        var profession = form.querySelector('[data-disposition-profession]');
        if (!staff || !profession) {
            return;
        }
        var option = staff.options && staff.selectedIndex >= 0 ? staff.options[staff.selectedIndex] : null;
        profession.value = option ? (option.getAttribute('data-profesi') || '') : '';
    }

    function toggleVisitFields(form) {
        var decision = form.querySelector('[data-disposition-decision]');
        var fields = form.querySelector('[data-disposition-visit-fields]');
        if (!decision || !fields) {
            return;
        }
        var isVisit = decision.value === 'visit';
        fields.classList.toggle('d-none', !isVisit);
        var urgency = fields.querySelector('[name="urgency"]');
        if (urgency) {
            urgency.required = isVisit;
        }
        var staff = fields.querySelector('[data-disposition-staff]');
        if (staff) {
            staff.required = isVisit;
        }
        syncDispositionProfession(form);
    }

    function serialize(form) {
        var body = new URLSearchParams();
        var data = new FormData(form);
        data.forEach(function (value, key) {
            body.append(key, typeof value === 'string' ? value : '');
        });
        return body;
    }

    function fillKey(form, selector, prefix) {
        var input = form.querySelector(selector);
        if (input && input.value === '') {
            input.value = (prefix || '') + newKey();
        }
    }

    function fillKeys(form) {
        fillKey(form, '[data-clinical-idempotency]', '');
        fillKey(form, '[data-clinical-submission-key]', 'sub-');
    }

    function clearGenerated(form, namePrefix) {
        Array.prototype.forEach.call(form.querySelectorAll('input[data-generated-list="1"]'), function (input) {
            if (input.name.indexOf(namePrefix) === 0) {
                input.parentNode.removeChild(input);
            }
        });
    }

    function buildResultLists(form) {
        Array.prototype.forEach.call(form.querySelectorAll('[data-result-list]'), function (textarea) {
            var listName = textarea.getAttribute('data-result-list');
            clearGenerated(form, listName + '[');
            var repeater = form.querySelector('[data-cw-result-list="' + listName + '"]');
            var rows = repeater ? repeater.querySelectorAll('[data-cw-repeater-row]') : [];
            if (rows.length > 0) {
                var generated = false;
                for (var rowIndex = 0; rowIndex < rows.length && rowIndex < 50; rowIndex += 1) {
                    var labelInput = rows[rowIndex].querySelector('[data-cw-result-label]');
                    var valueInput = rows[rowIndex].querySelector('[data-cw-result-value]');
                    var labelValue = labelInput ? String(labelInput.value || '').trim() : '';
                    var itemValue = valueInput ? String(valueInput.value || '').trim() : '';
                    if (labelValue === '' && itemValue === '') {
                        continue;
                    }
                    generated = true;
                    if (labelValue === '') {
                        labelValue = itemValue;
                    }
                    if (itemValue === '') {
                        itemValue = labelValue;
                    }
                    [
                        ['key', 'item_' + rowIndex],
                        ['label', labelValue.slice(0, 120)],
                        ['value', itemValue.slice(0, 2000)]
                    ].forEach(function (pair) {
                        var input = document.createElement('input');
                        input.type = 'hidden';
                        input.setAttribute('data-generated-list', '1');
                        input.name = listName + '[' + rowIndex + '][' + pair[0] + ']';
                        input.value = pair[1];
                        form.appendChild(input);
                    });
                }
                if (generated) {
                    return;
                }
            }
            var index = 0;
            String(textarea.value || '').split(/\r?\n/).forEach(function (line) {
                line = line.trim();
                if (line === '' || index >= 50) {
                    return;
                }
                var fields = [
                    ['key', 'item_' + index],
                    ['label', line.slice(0, 120)],
                    ['value', line.slice(0, 2000)]
                ];
                fields.forEach(function (pair) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.setAttribute('data-generated-list', '1');
                    input.name = listName + '[' + index + '][' + pair[0] + ']';
                    input.value = pair[1];
                    form.appendChild(input);
                });
                index += 1;
            });
        });
    }

    function buildAmendItems(form) {
        var target = form.querySelector('[data-amend-items]');
        if (!target) {
            return;
        }
        var field = form.querySelector('[name="field_key"]');
        var value = form.querySelector('[name="corrected_value"]');
        target.value = JSON.stringify([{
            field_key: field ? field.value : '',
            corrected_value: value ? value.value : ''
        }]);
    }

    function toggleReviewReason(form) {
        var decisions = form.querySelectorAll('[data-review-decision]');
        var box = form.querySelector('[data-review-reason]');
        if (!decisions.length || !box) {
            return;
        }
        var checked = form.querySelector('[data-review-decision]:checked');
        var needsReason = checked && checked.value === 'correction_required';
        box.classList.toggle('d-none', !needsReason);
        var reason = box.querySelector('[name="correction_reason"]');
        if (reason) {
            reason.required = needsReason;
            reason.setAttribute('aria-required', needsReason ? 'true' : 'false');
        }
    }

    function submitForm(form, onSuccess) {
        if (form.getAttribute('data-clinical-submitting') === '1') {
            return;
        }
        var endpoint = form.getAttribute('data-clinical-endpoint') || '';
        var successText = form.getAttribute('data-clinical-success') || 'Perubahan disimpan.';
        var submit = form.querySelector('.clinical-workflow-submit');
        var decision = form.querySelector('[data-disposition-decision]');

        if (decision && decision.value === '') {
            showMessage(form, 'Pilih jenis layanan terlebih dahulu.', true);
            decision.focus();
            return;
        }

        form.setAttribute('data-clinical-submitting', '1');
        form.setAttribute('aria-busy', 'true');
        if (submit) {
            submit.disabled = true;
        }
        showMessage(form, 'Menyimpan...', false);

        root.fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: serialize(form)
        }).then(function (response) {
            return response.json().catch(function () {
                return {};
            }).then(function (payload) {
                return { ok: response.ok, payload: payload };
            });
        }).then(function (result) {
            var payload = result.payload || {};
            if (result.ok && payload.success) {
                var assignmentMessage = payload.data && payload.data.staff_assignment_message
                    ? String(payload.data.staff_assignment_message)
                    : '';
                var successMessage = assignmentMessage || successText;
                var successWithWarning = assignmentMessage !== '';
                var savedRevision = payload.data && payload.data.draft_revision;
                if (savedRevision !== undefined && savedRevision !== null) {
                    var revisionInput = form.querySelector('input[name="expected_revision"]');
                    if (revisionInput) {
                        revisionInput.value = String(savedRevision);
                    }
                }
                showMessage(form, successMessage, successWithWarning);
                return notify(successWithWarning ? 'Tersimpan sebagian' : 'Tersimpan', successMessage, successWithWarning ? 'warning' : 'success').then(function () {
                    if (typeof onSuccess === 'function') {
                        onSuccess();
                        return;
                    }
                    root.location.reload();
                });
            }
            var message = payload.message || 'Langkah klinis belum dapat diproses. Coba lagi.';
            showMessage(form, message, true);
            return notify('Belum tersimpan', message, 'error');
        }).catch(function () {
            var message = 'Koneksi terputus. Periksa jaringan lalu coba lagi.';
            showMessage(form, message, true);
            notify('Belum tersimpan', message, 'error');
        }).then(function () {
            form.removeAttribute('data-clinical-submitting');
            form.removeAttribute('aria-busy');
            if (submit) {
                submit.disabled = false;
            }
        });
    }

    function install(form) {
        if (!form || form.getAttribute('data-clinical-installed') === '1') {
            return;
        }
        form.setAttribute('data-clinical-installed', '1');

        form.addEventListener('invalid', function (event) {
            if (event.target && typeof event.target.setAttribute === 'function') {
                event.target.setAttribute('aria-invalid', 'true');
            }
        }, true);
        form.addEventListener('input', function (event) {
            if (event.target && typeof event.target.removeAttribute === 'function') {
                event.target.removeAttribute('aria-invalid');
            }
        });

        var keyInput = form.querySelector('[data-clinical-idempotency]');
        fillKeys(form);

        toggleVisitFields(form);
        toggleReviewReason(form);

        var decision = form.querySelector('[data-disposition-decision]');
        if (decision) {
            decision.addEventListener('change', function () {
                toggleVisitFields(form);
            });
        }
        var dispositionStaff = form.querySelector('[data-disposition-staff]');
        if (dispositionStaff) {
            dispositionStaff.addEventListener('change', function () {
                syncDispositionProfession(form);
            });
        }

        var reviewDecisions = form.querySelectorAll('[data-review-decision]');
        Array.prototype.forEach.call(reviewDecisions, function (reviewDecision) {
            reviewDecision.addEventListener('change', function () {
                toggleReviewReason(form);
            });
        });

        // Payload berubah berarti operasi baru: kunci idempotensi diperbarui
        // supaya percobaan sebelumnya tidak dianggap konflik.
        var refreshKey = function () {
            if (keyInput) {
                keyInput.value = newKey();
            }
        };
        form.addEventListener('input', refreshKey);
        form.addEventListener('change', refreshKey);

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            buildResultLists(form);
            buildAmendItems(form);
            fillKeys(form);
            submitForm(form);
        });

        Array.prototype.forEach.call(form.querySelectorAll('[data-clinical-save-then-submit]'), function (button) {
            button.addEventListener('click', function () {
                var target = documentRef.querySelector(button.getAttribute('data-clinical-save-then-submit'));
                if (!target) {
                    return;
                }
                var proceed = function () {
                    buildResultLists(form);
                    fillKeys(form);
                    submitForm(form, function () {
                        submitForm(target);
                    });
                };
                if (root.DoclincClinicalWorkspace && typeof root.DoclincClinicalWorkspace.confirm === 'function') {
                    root.DoclincClinicalWorkspace.confirm({
                        title: 'Kirim hasil kunjungan?',
                        text: 'Pastikan tanda vital dan catatan sudah benar. Hasil yang dikirim akan ditinjau dokter.',
                        icon: 'warning',
                        confirmText: 'Ya, kirim hasil'
                    }).then(function (confirmed) {
                        if (confirmed) {
                            proceed();
                        }
                    });
                    return;
                }
                proceed();
            });
        });
    }

    function installSkipDisposition(button) {
        if (!button || button.getAttribute('data-skip-installed') === '1') {
            return;
        }
        button.setAttribute('data-skip-installed', '1');
        button.addEventListener('click', function () {
            if (button.disabled) {
                return;
            }
            var endpoint = button.getAttribute('data-skip-endpoint') || '';
            var requestId = button.getAttribute('data-request-id') || '';
            var messageSelector = button.getAttribute('data-skip-message') || '';
            var message = messageSelector ? documentRef.querySelector(messageSelector) : null;
            if (!endpoint || !requestId) {
                return;
            }
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            if (message) {
                message.className = 'small mt-2 text-muted';
                message.textContent = 'Menyiapkan konsultasi tanpa disposisi...';
            }
            var body = new URLSearchParams();
            body.append('request_id', requestId);
            body.append('service_mode', 'non_visit');
            root.fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: body.toString()
            }).then(function (response) {
                return response.json().catch(function () { return {}; }).then(function (payload) {
                    return { ok: response.ok, payload: payload };
                });
            }).then(function (result) {
                var payload = result.payload || {};
                if (result.ok && (payload.ok === true || payload.status === 'success')) {
                    root.location.reload();
                    return;
                }
                var error = payload.message || 'Konsultasi tanpa disposisi belum dapat disiapkan. Coba lagi.';
                if (message) {
                    message.className = 'small mt-2 text-danger';
                    message.textContent = error;
                }
                notify('Belum dapat dilanjutkan', error, 'error');
            }).catch(function () {
                var error = 'Koneksi terputus. Periksa jaringan lalu coba lagi.';
                if (message) {
                    message.className = 'small mt-2 text-danger';
                    message.textContent = error;
                }
                notify('Belum dapat dilanjutkan', error, 'error');
            }).then(function () {
                button.disabled = false;
                button.removeAttribute('aria-busy');
            });
        });
    }

    function boot() {
        Array.prototype.forEach.call(documentRef.querySelectorAll('[data-clinical-form]'), install);
        Array.prototype.forEach.call(documentRef.querySelectorAll('[data-skip-disposition]'), installSkipDisposition);
    }

    if (documentRef.readyState === 'loading') {
        documentRef.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
})(window);
