(function (root) {
  'use strict';

  function render(element, definition) {
    var width = Number(definition && definition.w) || 180;
    var height = Number(definition && definition.h) || 95;
    var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', '0 0 ' + width + ' ' + height);
    svg.setAttribute('role', 'presentation');
    svg.setAttribute('focusable', 'false');
    var layers = (definition && definition.layers) || [];
    var bars = layers.filter(function (layer) { return layer && layer.ty === 4; }).slice(0, 5);
    if (!bars.length) throw new Error('voice_waveform_layers_missing');
    bars.forEach(function (layer, index) {
      var position = layer.ks && layer.ks.p && layer.ks.p.k;
      var scale = layer.ks && layer.ks.s && layer.ks.s.k;
      /* Lottie shape transforms commonly encode animated scale as a
         keyframe array.  Use the first deterministic frame for this
         presentation-only renderer; never let an object coerce to NaN and
         silently produce invisible SVG geometry. */
      if (scale && !Array.isArray(scale) && Array.isArray(scale.k)) {
        scale = scale.k[0] && Array.isArray(scale.k[0].s) ? scale.k[0].s : null;
      }
      if (Array.isArray(scale) && scale.length && typeof scale[0] === 'object') {
        scale = scale[0] && Array.isArray(scale[0].s) ? scale[0].s : null;
      }
      var x = Array.isArray(position) ? Number(position[0]) : 18 + (index * 36);
      var y = Array.isArray(position) ? Number(position[1]) : height / 2;
      var sx = Array.isArray(scale) ? Number(scale[0]) / 100 : 1;
      var sy = Array.isArray(scale) ? Number(scale[1]) / 100 : 1;
      var rect = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
      rect.setAttribute('x', String(x - 6));
      rect.setAttribute('y', String(y - 13));
      rect.setAttribute('width', String(12 * sx));
      rect.setAttribute('height', String(26 * sy));
      rect.setAttribute('rx', '6');
      rect.setAttribute('fill', '#0b9e6d');
      rect.style.transformBox = 'fill-box';
      rect.style.transformOrigin = 'center';
      rect.style.animation = 'doclincVoiceWave 1.15s ease-in-out ' + (index * 90) + 'ms infinite alternate';
      svg.appendChild(rect);
    });
    element.replaceChildren(svg);
    element.dataset.lottieLoaded = 'true';
  }

  function play(element) {
    if (!element) return Promise.resolve(false);
    if (element.dataset.lottieLoaded === 'true') return Promise.resolve(true);
    if (element.dataset.lottieFallback === 'true') return Promise.resolve(false);
    if (element.dataset.lottieLoading === 'true') return Promise.resolve(false);
    var source = element.getAttribute('data-lottie-src');
    if (!source) return Promise.resolve(false);
    element.dataset.lottieLoading = 'true';
    var settled = false;
    var fallbackTimer = window.setTimeout(function () {
      if (settled || element.dataset.lottieLoading !== 'true') return;
      element.dataset.lottieLoaded = 'false';
      element.dataset.lottieFallback = 'true';
      element.classList.add('is-fallback');
      delete element.dataset.lottieLoading;
    }, 4000);
    return fetch(source, { credentials: 'same-origin' }).then(function (response) {
      if (!response.ok) throw new Error('voice_waveform_load_failed');
      return response.json();
    }).then(function (definition) {
      render(element, definition);
      return true;
    }).catch(function () {
      element.dataset.lottieLoaded = 'false';
      element.dataset.lottieFallback = 'true';
      element.classList.add('is-fallback');
      return false;
    }).finally(function () {
      settled = true;
      window.clearTimeout(fallbackTimer);
      delete element.dataset.lottieLoading;
    });
  }

  root.DoclincVoiceWaveform = { play: play };
  function boot() {
    document.querySelectorAll('[data-voice-waveform]').forEach(function (element) { play(element); });
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
    // The call view is assembled by a server-rendered conditional block. A
    // queued second pass covers browsers that transition to interactive while
    // the parser is still completing that block, without duplicating fetches.
    window.setTimeout(boot, 0);
  } else {
    boot();
  }
  if (window.MutationObserver && document.documentElement) {
    var observer = new MutationObserver(boot);
    observer.observe(document.documentElement, { childList: true, subtree: true });
  }
}(window));
