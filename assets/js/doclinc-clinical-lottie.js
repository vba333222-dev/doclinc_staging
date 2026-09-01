(function (root) {
    'use strict';
    root.DoclincClinicalLottie = {
        play: function (element, source, fallback) {
            if (!element) return Promise.resolve(false);
            var reduced = root.matchMedia && root.matchMedia('(prefers-reduced-motion: reduce)').matches;
            if (reduced || typeof root.lottie === 'undefined') {
                if (fallback) element.textContent = fallback;
                return Promise.resolve(false);
            }
            try {
                return Promise.resolve(root.lottie.loadAnimation({ container: element, renderer: 'svg', loop: false, autoplay: true, path: source }))
                    .catch(function () { if (fallback) element.textContent = fallback; return false; });
            } catch (error) {
                if (fallback) element.textContent = fallback;
                return Promise.resolve(false);
            }
        }
    };
}(window));
