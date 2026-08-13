(function (root, factory) {
	'use strict';

	var api = factory();
	if (typeof module === 'object' && module.exports) {
		module.exports = api;
	}
	if (root) {
		root.DoclincClinicalFormGuard = api;
	}
})(typeof window !== 'undefined' ? window : globalThis, function () {
	'use strict';

	function create(options) {
		options = options || {};
		var runtime = options.window || (typeof window !== 'undefined' ? window : null);
		var form = options.form || null;
		var dirty = false;
		var submitting = false;
		var started = false;
		var onDirtyChange = typeof options.onDirtyChange === 'function' ? options.onDirtyChange : function () {};

		function setDirty(value) {
			value = value === true;
			if (dirty === value) return;
			dirty = value;
			onDirtyChange(dirty);
		}

		function meaningfulTarget(target) {
			return target && String(target.type || '').toLowerCase() !== 'hidden';
		}

		function changed(event) {
			if (meaningfulTarget(event && event.target)) setDirty(true);
		}

		function beforeUnload(event) {
			if (!dirty) return;
			event.preventDefault();
			event.returnValue = '';
			return '';
		}

		function start() {
			if (started || !form || !runtime) return false;
			started = true;
			form.addEventListener('input', changed, true);
			form.addEventListener('change', changed, true);
			runtime.addEventListener('beforeunload', beforeUnload);
			return true;
		}

		function destroy() {
			if (!started) return;
			form.removeEventListener('input', changed, true);
			form.removeEventListener('change', changed, true);
			runtime.removeEventListener('beforeunload', beforeUnload);
			started = false;
		}

		return Object.freeze({
			start: start,
			destroy: destroy,
			markDirty: function () { setDirty(true); },
			beginSubmission: function () {
				if (submitting) return false;
				submitting = true;
				return true;
			},
			submissionFailed: function () { submitting = false; },
			submissionSucceeded: function () { submitting = false; setDirty(false); },
			isDirty: function () { return dirty; },
			isSubmitting: function () { return submitting; }
		});
	}

	return Object.freeze({ create: create });
});
