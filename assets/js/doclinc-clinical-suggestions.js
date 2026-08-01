(function (window, document) {
	'use strict';

	var activeInstances = [];

	function valueOf(element) {
		return element.isContentEditable ? element.textContent : element.value;
	}

	function setValue(element, value) {
		if (element.isContentEditable) {
			element.textContent = value;
		} else {
			element.value = value;
		}
		element.dispatchEvent(new Event('change', { bubbles: true }));
	}

	function normalizeTerm(value) {
		return String(value || '').replace(/\s+/g, ' ').trim().toLocaleLowerCase('id-ID');
	}

	function displayText(item) {
		return String(item && (item.display || item.label) || '').trim();
	}

	function metadataText(item) {
		return String(item && item.meta || '').trim();
	}

	function supportingText(item) {
		return String(item && item.supporting || '').trim();
	}

	function selectedValue(type, item) {
		if (type === 'diagnosis') {
			return String(item && (item.value || item.label) || '').trim();
		}
		return String(item && item.label || '').trim();
	}

	function symptomQuery(value) {
		var text = String(value || '');
		var boundary = Math.max(text.lastIndexOf('\n'), text.lastIndexOf(';'));
		return text.slice(boundary + 1).replace(/\s+/g, ' ').trim();
	}

	function mergeSymptomText(currentValue, chosenValue, lastQuery) {
		var current = String(currentValue || '');
		var chosen = String(chosenValue || '').trim();
		if (!chosen) return current;
		var chosenKey = normalizeTerm(chosen);
		var existingTerms = current.split(/[\n;]+/).map(normalizeTerm).filter(Boolean);
		if (existingTerms.indexOf(chosenKey) !== -1) return current;

		var boundary = Math.max(current.lastIndexOf('\n'), current.lastIndexOf(';'));
		var tail = current.slice(boundary + 1);
		if (normalizeTerm(tail) && normalizeTerm(tail) === normalizeTerm(lastQuery)) {
			return current.slice(0, boundary + 1) + (boundary >= 0 && current.charAt(boundary) === ';' ? ' ' : '') + chosen;
		}
		return current.trimEnd() + (current.trim() ? '\n' : '') + chosen;
	}

	function requestIdFor(element) {
		var selector = element.getAttribute('data-clinical-request-id-source');
		var source = selector ? document.querySelector(selector) : null;
		return source ? String(source.value || source.getAttribute('value') || '').trim() : String(element.getAttribute('data-clinical-request-id') || '').trim();
	}

	function closeOthers(instance) {
		activeInstances.forEach(function (other) {
			if (other !== instance) other.close();
		});
	}

	function SuggestionBox(element) {
		this.element = element;
		this.timer = null;
		this.controller = null;
		this.sequence = 0;
		this.index = -1;
		this.items = [];
		this.lastQuery = '';
		this.endpoint = element.getAttribute('data-clinical-suggestion-endpoint');
		this.type = element.getAttribute('data-clinical-suggestion-type');
		this.list = document.createElement('div');
		this.list.className = 'clinical-suggestion-list';
		this.list.setAttribute('role', 'listbox');
		this.list.hidden = true;
		element.parentNode.classList.add('clinical-suggestion-anchor');
		element.setAttribute('autocomplete', 'off');
		element.setAttribute('aria-autocomplete', 'list');
		element.parentNode.insertBefore(this.list, element.nextSibling);
		this.bind();
	}

	SuggestionBox.prototype.bind = function () {
		var self = this;
		this.element.addEventListener('input', function () {
			window.clearTimeout(self.timer);
			var query = self.type === 'symptom'
				? symptomQuery(valueOf(self.element))
				: valueOf(self.element).replace(/\s+/g, ' ').trim();
			if (query.length < 2) {
				self.abort();
				self.message('Ketik minimal 2 karakter');
				return;
			}
			self.message('Mencari...');
			self.timer = window.setTimeout(function () { self.search(query); }, 300);
		});
		this.element.addEventListener('focus', function () {
			closeOthers(self);
			if (valueOf(self.element).trim().length < 2) self.message('Ketik minimal 2 karakter');
		});
		this.element.addEventListener('keydown', function (event) {
			if (self.list.hidden) return;
			if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
				event.preventDefault();
				self.move(event.key === 'ArrowDown' ? 1 : -1);
			} else if (event.key === 'Enter' && self.index >= 0) {
				event.preventDefault();
				self.select(self.items[self.index]);
			} else if (event.key === 'Escape') {
				self.close();
			}
		});
		document.addEventListener('pointerdown', function (event) {
			if (event.target !== self.element && !self.list.contains(event.target)) self.close();
		});
	};

	SuggestionBox.prototype.abort = function () {
		if (this.controller) this.controller.abort();
		this.controller = null;
		this.sequence += 1;
	};

	SuggestionBox.prototype.search = function (query) {
		var self = this;
		this.abort();
		this.lastQuery = query;
		var sequence = this.sequence;
		this.controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
		var parameters = new URLSearchParams({ type: this.type, q: query, limit: '10' });
		var requestId = requestIdFor(this.element);
		if (requestId) parameters.set('request_id', requestId);
		fetch(this.endpoint + '?' + parameters.toString(), {
			method: 'GET',
			credentials: 'same-origin',
			headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
			signal: this.controller ? this.controller.signal : undefined
		}).then(function (response) {
			if (!response.ok) throw new Error('reference_unavailable');
			return response.json();
		}).then(function (payload) {
			if (sequence !== self.sequence) return;
			if (!payload || payload.success !== true || !Array.isArray(payload.data)) throw new Error('invalid_response');
			self.render(payload.data.slice(0, 10));
		}).catch(function (error) {
			if (error && error.name === 'AbortError') return;
			if (sequence === self.sequence) self.message('Referensi tidak tersedia, tetap lanjutkan dengan teks yang diketik');
		});
	};

	SuggestionBox.prototype.render = function (items) {
		var self = this;
		var seen = Object.create(null);
		items = items.filter(function (item) {
			var key = normalizeTerm(item && (item.id || item.label || item.display));
			if (!key || seen[key]) return false;
			seen[key] = true;
			return true;
		});
		this.list.replaceChildren();
		this.items = items;
		this.index = -1;
		if (!items.length) {
			this.message('Tidak ada saran yang cocok');
			return;
		}
		items.forEach(function (item, index) {
			var button = document.createElement('button');
			button.type = 'button';
			button.className = 'clinical-suggestion-option';
			button.setAttribute('role', 'option');
			button.dataset.index = String(index);
			var primary = document.createElement('span');
			primary.textContent = displayText(item);
			button.appendChild(primary);
			var supporting = supportingText(item);
			if (supporting && normalizeTerm(supporting) !== normalizeTerm(displayText(item))) {
				var supportingLabel = document.createElement('small');
				supportingLabel.className = 'clinical-suggestion-supporting';
				supportingLabel.textContent = supporting;
				button.appendChild(supportingLabel);
			}
			var metadata = metadataText(item);
			if (metadata) {
				var source = document.createElement('small');
				source.textContent = metadata;
				button.appendChild(source);
			}
			button.addEventListener('pointerdown', function (event) {
				event.preventDefault();
				self.select(item);
			});
			self.list.appendChild(button);
		});
		this.list.hidden = false;
	};

	SuggestionBox.prototype.message = function (text) {
		this.items = [];
		this.index = -1;
		this.list.replaceChildren();
		var message = document.createElement('div');
		message.className = 'clinical-suggestion-message';
		message.textContent = text;
		this.list.appendChild(message);
		this.list.hidden = false;
	};

	SuggestionBox.prototype.move = function (direction) {
		if (!this.items.length) return;
		this.index = (this.index + direction + this.items.length) % this.items.length;
		Array.prototype.forEach.call(this.list.querySelectorAll('[role="option"]'), function (option, index) {
			option.classList.toggle('is-active', index === this.index);
			option.setAttribute('aria-selected', index === this.index ? 'true' : 'false');
		}, this);
	};

	SuggestionBox.prototype.select = function (item) {
		var chosen = selectedValue(this.type, item);
		if (this.type === 'symptom') {
			chosen = mergeSymptomText(valueOf(this.element), chosen, this.lastQuery);
		}
		setValue(this.element, chosen || '');
		this.element.dispatchEvent(new CustomEvent('clinical-suggestion:selected', { bubbles: true, detail: item }));
		this.close();
		this.element.focus();
	};

	SuggestionBox.prototype.close = function () {
		this.list.hidden = true;
		this.index = -1;
	};

	function initialize(root) {
		(root || document).querySelectorAll('[data-clinical-suggestion]:not([data-clinical-suggestion-ready])').forEach(function (element) {
			if (!element.getAttribute('data-clinical-suggestion-endpoint') || !element.getAttribute('data-clinical-suggestion-type')) return;
			element.setAttribute('data-clinical-suggestion-ready', '1');
			activeInstances.push(new SuggestionBox(element));
		});
	}

	document.addEventListener('DOMContentLoaded', function () { initialize(document); });
	window.DoclincClinicalSuggestions = {
		initialize: initialize,
		mergeSymptomText: mergeSymptomText,
		symptomQuery: symptomQuery,
		displayText: displayText,
		metadataText: metadataText,
		supportingText: supportingText,
		selectedValue: selectedValue
	};
})(window, document);
