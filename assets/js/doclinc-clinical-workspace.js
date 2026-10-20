(function (root) {
    'use strict';

    if (!root || !root.document || root.__doclincClinicalWorkspaceInstalled) {
        return;
    }
    root.__doclincClinicalWorkspaceInstalled = true;

    var documentRef = root.document;

    function each(nodes, callback) {
        Array.prototype.forEach.call(nodes || [], callback);
    }

    function confirmAction(options) {
        var settings = options || {};
        if (root.Swal && typeof root.Swal.fire === 'function') {
            return root.Swal.fire({
                title: settings.title || 'Lanjutkan tindakan?',
                text: settings.text || '',
                icon: settings.icon || 'warning',
                showCancelButton: true,
                confirmButtonText: settings.confirmText || 'Ya, lanjutkan',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#2D7D56'
            }).then(function (result) {
                return result.isConfirmed === true;
            });
        }
        if (typeof root.confirm !== 'function') {
            return Promise.resolve(true);
        }
        return Promise.resolve(root.confirm((settings.title || 'Lanjutkan tindakan?') + '\n\n' + (settings.text || '')));
    }

    function initThread(scope) {
        each(scope.querySelectorAll('.cw-thread'), function (thread) {
            each(thread.querySelectorAll('.cw-step'), function (step) {
                if (step.classList.contains('is-current')) {
                    step.setAttribute('aria-current', 'step');
                } else {
                    step.removeAttribute('aria-current');
                }
            });
        });
    }

    function syncChoice(choice) {
        var input = choice.querySelector('input');
        if (!input) {
            return;
        }
        choice.classList.toggle('is-selected', input.checked);
    }

    function initChoices(scope) {
        each(scope.querySelectorAll('.cw-choice'), function (choice) {
            syncChoice(choice);
            var input = choice.querySelector('input');
            if (input) {
                input.addEventListener('change', function () {
                    each(scope.querySelectorAll('.cw-choice'), function (peer) {
                        syncChoice(peer);
                    });
                });
            }
        });
    }

    function initConfirmations(scope) {
        each(scope.querySelectorAll('[data-cw-confirm]'), function (node) {
            if (node.getAttribute('data-cw-confirm-ready') === '1') {
                return;
            }
            node.setAttribute('data-cw-confirm-ready', '1');
            node.addEventListener('click', function (event) {
                if (node.getAttribute('data-cw-confirmed') === '1') {
                    node.removeAttribute('data-cw-confirmed');
                    return;
                }
                event.preventDefault();
                confirmAction({
                    title: node.getAttribute('data-cw-confirm-title') || 'Lanjutkan tindakan?',
                    text: node.getAttribute('data-cw-confirm') || '',
                    icon: node.getAttribute('data-cw-confirm-icon') || 'warning',
                    confirmText: node.getAttribute('data-cw-confirm-ok') || 'Ya, lanjutkan'
                }).then(function (confirmed) {
                    if (!confirmed) {
                        return;
                    }
                    node.setAttribute('data-cw-confirmed', '1');
                    if (node.tagName === 'A' && node.getAttribute('href')) {
                        root.location.href = node.getAttribute('href');
                        return;
                    }
                    if (typeof node.click === 'function') {
                        node.click();
                    }
                });
            });
        });
    }

    function repeaterIndex(container) {
        var index = parseInt(container.getAttribute('data-cw-next-index') || '0', 10);
        if (isNaN(index) || index < 0) {
            index = 0;
        }
        container.setAttribute('data-cw-next-index', String(index + 1));
        return index;
    }

    function syncResultRepeater(container) {
        var textarea = container.querySelector('[data-result-list]');
        if (!textarea) {
            return;
        }
        var lines = [];
        each(container.querySelectorAll('[data-cw-repeater-row]'), function (row) {
            var label = row.querySelector('[data-cw-result-label]');
            var value = row.querySelector('[data-cw-result-value]');
            var labelText = label ? String(label.value || '').trim() : '';
            var valueText = value ? String(value.value || '').trim() : '';
            if (labelText === '' && valueText === '') {
                return;
            }
            if (labelText !== '' && valueText !== '') {
                lines.push(labelText + ': ' + valueText);
            } else {
                lines.push(labelText !== '' ? labelText : valueText);
            }
        });
        textarea.value = lines.join('\n');
    }

    function initRepeaters(scope) {
        each(scope.querySelectorAll('[data-cw-repeater]'), function (container) {
            var add = container.querySelector('[data-cw-repeater-add]');
            var template = container.querySelector('template[data-cw-repeater-template]');
            var list = container.querySelector('[data-cw-repeater-list]') || container;
            var sync = function () {
                syncResultRepeater(container);
            };
            var form = container.closest('form');
            if (form && form.getAttribute('data-cw-result-sync') !== '1') {
                form.setAttribute('data-cw-result-sync', '1');
                form.addEventListener('submit', sync, true);
                form.addEventListener('click', function (event) {
                    if (event.target.closest('[data-clinical-save-then-submit]')) {
                        sync();
                    }
                }, true);
            }
            if (add && template) {
                add.addEventListener('click', function (event) {
                    event.preventDefault();
                    var index = repeaterIndex(container);
                    var markup = template.innerHTML.replace(/__INDEX__/g, String(index));
                    var holder = documentRef.createElement('div');
                    holder.innerHTML = markup;
                    var row = holder.firstElementChild;
                    if (!row) {
                        return;
                    }
                    list.appendChild(row);
                    sync();
                    var focusable = row.querySelector('input, select, textarea, button');
                    if (focusable) {
                        focusable.focus();
                    }
                });
            }
            list.addEventListener('click', function (event) {
                var remove = event.target.closest('[data-cw-repeater-remove]');
                if (!remove || !list.contains(remove)) {
                    return;
                }
                event.preventDefault();
                var row = remove.closest('[data-cw-repeater-row]');
                if (row) {
                    row.parentNode.removeChild(row);
                    sync();
                }
            });
            container.addEventListener('input', sync);
            container.addEventListener('change', sync);
        });
    }

    function initCopyFromResult(scope) {
        each(scope.querySelectorAll('[data-cw-copy-result]'), function (button) {
            if (button.getAttribute('data-cw-copy-ready') === '1') {
                return;
            }
            button.setAttribute('data-cw-copy-ready', '1');
            button.addEventListener('click', function () {
                var targets = [
                    { selector: '#recordAnamnesis', value: button.getAttribute('data-copy-anamnesis') || '' },
                    { selector: '#recordTreatment', value: button.getAttribute('data-copy-treatment') || '' }
                ];
                var filled = [];
                each(targets, function (target) {
                    var field = scope.querySelector(target.selector);
                    if (field && target.value.trim() !== '' && String(field.value || '').trim() !== '' && String(field.value || '').trim() !== target.value.trim()) {
                        filled.push(field);
                    }
                });
                var apply = function () {
                    var copied = 0;
                    each(targets, function (target) {
                        var field = scope.querySelector(target.selector);
                        if (!field || target.value.trim() === '') {
                            return;
                        }
                        field.value = target.value;
                        field.dispatchEvent(new Event('input', { bubbles: true }));
                        copied += 1;
                    });
                    var status = scope.querySelector('[data-cw-copy-status]');
                    if (status) {
                        status.textContent = copied > 0
                            ? 'Disalin dari ' + (button.getAttribute('data-copy-source') || 'hasil kunjungan') + '.'
                            : 'Tidak ada catatan yang dapat disalin.';
                    }
                };
                if (filled.length === 0) {
                    apply();
                    return;
                }
                confirmAction({
                    title: 'Timpa isian rekam medis?',
                    text: 'Beberapa field sudah berisi. Salin hasil kunjungan dan timpa nilai yang ada?',
                    icon: 'warning',
                    confirmText: 'Ya, salin'
                }).then(function (confirmed) {
                    if (confirmed) {
                        apply();
                    }
                });
            });
        });
    }

    function init(scope) {
        var target = scope || documentRef.querySelector('[data-cw-root]') || documentRef;
        if (!target || target.getAttribute('data-cw-ready') === '1') {
            return;
        }
        target.setAttribute('data-cw-ready', '1');
        initThread(target);
        initChoices(target);
        initConfirmations(target);
        initRepeaters(target);
        initCopyFromResult(target);
    }

    function refresh(scope) {
        var target = scope || documentRef.querySelector('[data-cw-root]') || documentRef;
        if (!target) {
            return;
        }
        initThread(target);
        initChoices(target);
        initConfirmations(target);
    }

    root.DoclincClinicalWorkspace = {
        init: init,
        refresh: refresh,
        confirm: confirmAction
    };

    if (documentRef.readyState === 'loading') {
        documentRef.addEventListener('DOMContentLoaded', function () {
            init();
        });
    } else {
        init();
    }

})(typeof window !== 'undefined' ? window : null);
