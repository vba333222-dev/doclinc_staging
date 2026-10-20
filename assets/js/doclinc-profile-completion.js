(function (window, document) {
  'use strict';

  var config = window.DOCLINC_PROFILE_COMPLETION || {};
  var form = document.getElementById('profileCompletionForm');
  var feedback = document.getElementById('profileCompletionFeedback');
  var summary = document.getElementById('profileCompletionErrorSummary');
  var photoInput = document.getElementById('completionPhoto');
  var photoPreview = document.getElementById('completionPhotoPreview');
  var photoTrigger = document.getElementById('completionPhotoTrigger');
  var photoName = document.getElementById('completionPhotoName');
  var submit = document.getElementById('profileCompletionSubmit');
  var busy = false;
  var photoInvalid = false;
  var MAX_PHOTO_BYTES = 5 * 1024 * 1024;
  var ALLOWED_PHOTO = ['image/jpeg', 'image/png', 'image/webp'];

  function show(message, state) {
    if (!feedback) return;
    feedback.textContent = message || '';
    feedback.classList.toggle('is-error', state === 'error');
    feedback.classList.toggle('is-success', state === 'success');
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

  function setFieldError(field, message) {
    var target = document.getElementById('err-' + field.name);
    field.classList.toggle('is-invalid', !!message);
    if (message) {
      field.setAttribute('aria-invalid', 'true');
    } else {
      field.removeAttribute('aria-invalid');
    }
    if (target) {
      target.textContent = message || '';
      target.hidden = !message;
    }
  }

  function messageFor(field) {
    if (field.validity.valueMissing) return 'Wajib diisi.';
    if (field.validity.patternMismatch) return field.dataset.expected || 'Format belum sesuai.';
    if (field.validity.typeMismatch && field.type === 'email') return 'Format email belum benar.';
    if (field.validity.tooShort) return 'Isian terlalu pendek.';
    if (field.validity.rangeOverflow) return 'Tanggal tidak boleh di masa depan.';
    return 'Periksa isian ini.';
  }

  function validate() {
    if (!form) return [];
    var fields = form.querySelectorAll('input, select, textarea');
    var invalid = [];
    Array.prototype.forEach.call(fields, function (field) {
      if (field.disabled || field.type === 'file') return;
      if (!field.checkValidity()) {
        setFieldError(field, messageFor(field));
        invalid.push(field);
      } else {
        setFieldError(field, '');
      }
    });
    return invalid;
  }

  function showSummary(count) {
    if (!summary) return;
    summary.hidden = count === 0;
    summary.textContent = count === 1 ? 'Satu isian perlu diperbaiki.' : count + ' isian perlu diperbaiki.';
  }

  function renderServerErrors(fieldErrors) {
    var invalid = [];
    Object.keys(fieldErrors).forEach(function (name) {
      var field = form.querySelector('[name="' + name + '"]');
      if (!field) return;
      setFieldError(field, fieldErrors[name]);
      invalid.push(field);
    });
    if (invalid.length) {
      showSummary(invalid.length);
      invalid[0].focus();
    }
  }

  function status() {
    return fetch(config.statusUrl, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin'
    }).then(parse);
  }

  function uploadPhoto(photo) {
    if (!photo || !photo.size) return Promise.resolve();
    if (ALLOWED_PHOTO.indexOf(photo.type) === -1 || photo.size > MAX_PHOTO_BYTES) {
      return Promise.reject({ message: 'Gunakan JPG, PNG, atau WebP maksimal 5 MB.' });
    }
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

    var invalid = validate();
    if (photoInvalid) {
      show('Gunakan JPG, PNG, atau WebP maksimal 5 MB.', 'error');
      if (photoInput) photoInput.focus();
      return;
    }
    if (invalid.length) {
      showSummary(invalid.length);
      show('', '');
      invalid[0].focus();
      return;
    }

    busy = true;
    showSummary(0);
    if (submit) {
      submit.disabled = true;
      submit.textContent = 'Menyimpan...';
    }
    form.setAttribute('aria-busy', 'true');
    show('Menyimpan data...');

    var data = new FormData(form);
    var photo = data.get('foto');
    data.delete('foto');
    fetch(config.profileUpdateUrl, {
      method: 'POST',
      body: data,
      headers: { Accept: 'application/json' },
      credentials: 'same-origin'
    }).then(parse).then(function (result) {
      if (!accepted(result)) {
        if (result && result.field_errors) renderServerErrors(result.field_errors);
        throw result;
      }
      return uploadPhoto(photo);
    }).then(status).then(function (result) {
      if (result.success && result.data && result.data.complete) {
        window.location.assign(config.redirectUrl);
        return;
      }
      show('Data tersimpan. Lengkapi data lain yang diminta.', 'success');
      window.setTimeout(function () { window.location.reload(); }, 600);
    }).catch(function (error) {
      var message = error && error.message
        ? error.message
        : 'Data belum dapat disimpan. Periksa isian dan coba lagi.';
      show(message, 'error');
    }).finally(function () {
      busy = false;
      if (submit) {
        submit.disabled = false;
        submit.textContent = 'Simpan profil';
      }
      form.removeAttribute('aria-busy');
    });
  }

  if (form) {
    form.addEventListener('submit', submitProfile);
    var fields = form.querySelectorAll('input, select, textarea');
    Array.prototype.forEach.call(fields, function (field) {
      field.addEventListener('input', function () {
        if (field.classList.contains('is-invalid')) setFieldError(field, '');
      });
      field.addEventListener('change', function () {
        if (field.classList.contains('is-invalid')) setFieldError(field, '');
      });
    });
  }

  if (photoTrigger && photoInput) {
    photoTrigger.addEventListener('click', function () {
      photoInput.click();
    });
  }

  if (photoInput) {
    photoInput.addEventListener('change', function () {
      var file = photoInput.files && photoInput.files[0];
      photoInvalid = false;
      if (photoName) photoName.textContent = 'Belum ada file dipilih.';
      if (!file) return;
      if (ALLOWED_PHOTO.indexOf(file.type) === -1 || file.size < 1 || file.size > MAX_PHOTO_BYTES) {
        photoInvalid = true;
        show('Gunakan JPG, PNG, atau WebP maksimal 5 MB.', 'error');
        photoInput.value = '';
        return;
      }
      if (photoName) photoName.textContent = file.name;
      if (photoPreview && window.URL) {
        photoPreview.src = URL.createObjectURL(file);
      }
      show('Foto siap disimpan.', 'success');
    });
  }

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
