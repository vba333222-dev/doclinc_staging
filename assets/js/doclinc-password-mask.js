(function (root, factory) {
	var api = factory(root);
	if (typeof module === 'object' && module.exports) {
		module.exports = api;
	}
	if (root && root.document) {
		root.DoclincPasswordMask = api;
	}
})(typeof window !== 'undefined' ? window : globalThis, function (root) {
	'use strict';

	function attach(input, options) {
		if (!input || input.type !== 'password' || input.__doclincPasswordMask) {
			return null;
		}
		var delay = options && options.delay ? options.delay : 600;
		var timer = null;
		var documentRef = input.ownerDocument || (root && root.document);
		var badge = documentRef.createElement('span');
		badge.className = 'doclinc-password-last-character';
		badge.setAttribute('aria-hidden', 'true');
		badge.style.position = 'absolute';
		badge.style.right = '14px';
		badge.style.top = '50%';
		badge.style.transform = 'translateY(-50%)';
		badge.style.pointerEvents = 'none';
		badge.style.minWidth = '1ch';
		badge.style.textAlign = 'center';
		badge.style.background = 'inherit';
		badge.style.color = 'inherit';
		badge.style.display = 'none';
		var parent = input.parentNode;
		if (!parent) {
			return null;
		}
		var position = root && root.getComputedStyle ? root.getComputedStyle(parent).position : '';
		if (!position || position === 'static') {
			parent.style.position = 'relative';
		}
		parent.appendChild(badge);

		function mask() {
			if (timer !== null) {
				root.clearTimeout(timer);
				timer = null;
			}
			badge.textContent = '';
			badge.style.display = 'none';
		}

		function reveal(character) {
			mask();
			if (typeof character !== 'string' || Array.from(character).length !== 1) {
				return;
			}
			badge.textContent = character;
			badge.style.display = 'inline-block';
			timer = root.setTimeout(mask, delay);
		}

		input.addEventListener('beforeinput', function (event) {
			if (event && event.inputType === 'insertText') {
				reveal(event.data);
				return;
			}
			mask();
		});
		input.addEventListener('paste', mask);
		input.addEventListener('blur', mask);
		if (input.form) {
			input.form.addEventListener('submit', mask);
		}
		input.__doclincPasswordMask = true;
		return { mask: mask, reveal: reveal, badge: badge };
	}

	function start(documentRef) {
		if (!documentRef || !documentRef.querySelectorAll) {
			return 0;
		}
		var inputs = documentRef.querySelectorAll('input[type="password"]');
		var attached = 0;
		for (var index = 0; index < inputs.length; index++) {
			if (attach(inputs[index])) {
				attached++;
			}
		}
		return attached;
	}

	if (root && root.document) {
		if (root.document.readyState === 'loading') {
			root.document.addEventListener('DOMContentLoaded', function () { start(root.document); });
		} else {
			start(root.document);
		}
	}

	return { attach: attach, start: start };
});
