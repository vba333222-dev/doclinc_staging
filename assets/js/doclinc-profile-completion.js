(function (window, document) {
  'use strict';

  var config = window.DOCLINC_PROFILE_COMPLETION || {};
  var form = document.getElementById('profileCompletionForm');
  var feedback = document.getElementById('profileCompletionFeedback');
  var busy = false;

  function show(message, success) {
    if (!feedback) return;
    feedback.textContent = message || '';
    feedback.classList.toggle('is-success', !!success);
  }

  function parse(response) {
    return response.json().catch(function () {
      return { success: false, message: 'Respons server tidak dapat dibaca.' };
    }).then(function (body) {
      body.httpStatus = response.status;
      return body;
    });
  }

  function accepted(result) {
    return result && (result.status === 'success' || result.success === true);
  }

  function status() {
    return fetch(config.statusUrl, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin'
    }).then(parse);
  }

  function uploadPhoto(photo) {
    if (!photo || !photo.size) return Promise.resolve();
    var photoData = new FormData();
    photoData.append('foto', photo);
    return fetch(config.photoUpdateUrl, {
      method: 'POST',
      body: photoData,
      headers: { Accept: 'application/json' },
      credentials: 'same-origin'
    }).then(parse).then(function (result) {
      if (!accepted(result)) throw result;
    });
  }

  function submitProfile(event) {
    event.preventDefault();
    if (busy || !form) return;
    if (!form.checkValidity()) {
      form.reportValidity();
      return;
    }

    busy = true;
    var button = form.querySelector('button[type="submit"]');
    if (button) button.disabled = true;
    show('Menyimpan data...', true);

    var data = new FormData(form);
    var photo = data.get('foto');
    data.delete('foto');
    fetch(config.profileUpdateUrl, {
      method: 'POST',
      body: data,
      headers: { Accept: 'application/json' },
      credentials: 'same-origin'
    }).then(parse).then(function (result) {
      if (!accepted(result)) throw result;
      return uploadPhoto(photo);
    }).then(status).then(function (result) {
      if (result.success && result.data && result.data.complete) {
        window.location.assign(config.redirectUrl);
        return;
      }
      show('Data tersimpan. Data yang dikelola Admin masih perlu dilengkapi.', true);
      window.setTimeout(function () { window.location.reload(); }, 500);
    }).catch(function (error) {
      var message = error && error.message
        ? error.message
        : 'Data belum dapat disimpan. Periksa isian dan coba lagi.';
      show(message, false);
    }).finally(function () {
      busy = false;
      if (button) button.disabled = false;
    });
  }

  if (form) form.addEventListener('submit', submitProfile);
  var logout = document.querySelector('[data-profile-logout]');
  if (logout) {
    logout.addEventListener('click', function () {
      if (busy) return;
      busy = true;
      fetch(config.logoutUrl, {
        method: 'POST',
        headers: { Accept: 'application/json' },
        credentials: 'same-origin'
      }).finally(function () {
        window.location.assign(config.logoutUrl.replace(/\/logout\/?$/, '/'));
      });
    });
  }
})(window, document);
