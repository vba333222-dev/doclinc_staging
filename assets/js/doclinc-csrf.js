(function (root) {
    'use strict';

    if (!root || root.__doclincCsrfInstalled) {
        return;
    }

    var documentRef = root.document;
    var nameMeta = documentRef && documentRef.querySelector('meta[name="doclinc-csrf-name"]');
    var tokenMeta = documentRef && documentRef.querySelector('meta[name="doclinc-csrf-token"]');
    var tokenName = nameMeta ? String(nameMeta.getAttribute('content') || '') : '';
    var token = tokenMeta ? String(tokenMeta.getAttribute('content') || '') : '';
    if (!/^[A-Za-z0-9_\-]{1,100}$/.test(tokenName) || !/^[0-9a-f]{32}$/i.test(token)) {
        return;
    }

    root.__doclincCsrfInstalled = true;

    function isUnsafeMethod(method) {
        return ['GET', 'HEAD', 'OPTIONS'].indexOf(String(method || 'GET').toUpperCase()) === -1;
    }

    function isSameOrigin(url) {
        try {
            return new root.URL(String(url || ''), root.location.href).origin === root.location.origin;
        } catch (error) {
            return false;
        }
    }

    function addHiddenToken(form) {
        if (!form || String(form.method || 'GET').toUpperCase() !== 'POST') {
            return;
        }
        var input = form.querySelector('input[name="' + tokenName.replace(/"/g, '\\"') + '"]');
        if (!input) {
            input = documentRef.createElement('input');
            input.type = 'hidden';
            input.name = tokenName;
            form.appendChild(input);
        }
        input.value = token;
    }

    function submitPost(url, data) {
        if (!documentRef || !isSameOrigin(url)) {
            return false;
        }
        var form = documentRef.createElement('form');
        form.method = 'POST';
        form.action = new root.URL(String(url), root.location.href).href;
        form.hidden = true;
        Object.keys(data || {}).forEach(function (key) {
            var input = documentRef.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = String(data[key]);
            form.appendChild(input);
        });
        addHiddenToken(form);
        documentRef.body.appendChild(form);
        form.submit();
        return true;
    }

    if (typeof root.fetch === 'function') {
        var originalFetch = root.fetch.bind(root);
        root.fetch = function (input, init) {
            var options = Object.assign({}, init || {});
            var method = options.method || (input && input.method) || 'GET';
            var url = input && input.url ? input.url : input;
            if (isUnsafeMethod(method) && isSameOrigin(url)) {
                var headers = new root.Headers(options.headers || (input && input.headers) || {});
                headers.set('X-CSRF-TOKEN', token);
                headers.set('X-Requested-With', headers.get('X-Requested-With') || 'XMLHttpRequest');
                options.headers = headers;
            }
            return originalFetch(input, options);
        };
    }

    if (root.XMLHttpRequest && root.XMLHttpRequest.prototype) {
        var xhrOpen = root.XMLHttpRequest.prototype.open;
        var xhrSend = root.XMLHttpRequest.prototype.send;
        root.XMLHttpRequest.prototype.open = function (method, url) {
            this.__doclincCsrfMethod = method;
            this.__doclincCsrfUrl = url;
            return xhrOpen.apply(this, arguments);
        };
        root.XMLHttpRequest.prototype.send = function () {
            if (isUnsafeMethod(this.__doclincCsrfMethod) && isSameOrigin(this.__doclincCsrfUrl)) {
                this.setRequestHeader('X-CSRF-TOKEN', token);
                this.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            }
            return xhrSend.apply(this, arguments);
        };
    }

    if (documentRef) {
        documentRef.addEventListener('submit', function (event) {
            addHiddenToken(event.target);
        }, true);

        var addTokensToForms = function () {
            Array.prototype.forEach.call(documentRef.forms || [], addHiddenToken);
        };
        if (documentRef.readyState === 'loading') {
            documentRef.addEventListener('DOMContentLoaded', addTokensToForms, { once: true });
        } else {
            addTokensToForms();
        }

        if (root.HTMLFormElement && root.HTMLFormElement.prototype && root.HTMLFormElement.prototype.submit) {
            var nativeSubmit = root.HTMLFormElement.prototype.submit;
            root.HTMLFormElement.prototype.submit = function () {
                addHiddenToken(this);
                return nativeSubmit.apply(this, arguments);
            };
        }
    }

    root.DoclincCsrf = Object.freeze({
        tokenName: tokenName,
        token: token,
        addToForm: addHiddenToken,
        submitPost: submitPost
    });
})(window);
