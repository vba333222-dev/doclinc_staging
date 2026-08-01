# Visit proof completion gate

This feature makes a fresh on-site photo and browser geolocation mandatory before a
`Kunjungan Nakes` consultation can be completed. It does not apply to remote/non-visit
consultations and it does not add a chat attachment feature.

The feature is default-off and requires an exact allowed runtime environment:

- `DOCLINC_VISIT_PROOF_REQUIRED`
- `DOCLINC_VISIT_PROOF_ENVIRONMENT`
- `DOCLINC_VISIT_PROOF_STORAGE_PATH`

The storage path is mandatory when enabling the feature, must be absolute, writable by
PHP-FPM, and must resolve outside the public web root. The service refuses to stage proof
media under `FCPATH`.

When enabled, completion fails closed unless all of the following are true:

- the authenticated personal Nakes still owns the locked request;
- the submitted service mode is one of the two canonical values (`visit` or `non_visit`);
- the request is `Accepted` and the visit state is `arrived` or `in_service`;
- a JPG/JPEG image of at most 5 MB passes decoded-image, MIME, extension,
  size, and SHA-256 validation;
- a high-accuracy browser location was captured within the configured freshness window;
- the location is within the configured arrival radius of the persisted patient location;
- the staged media and location schemas are available.

A persisted visit cannot be downgraded to `non_visit` during completion. The locked
request is treated as a visit once `consultation_mode=visit` or its visit workflow has
reached `en_route`, `arrived`, or `in_service`, even if a client submits a non-visit
criteria value.

The model uses the caller's CI3 database connection. The request completion,
medical record, consultation result, visit location sample, media finalization, durable
notification, and realtime outbox writes share the existing model-owned transaction.
The uploaded file is staged before that transaction. If completion fails, the controller
marks the staged media as failed and removes the staged file.

The compatibility path remains unchanged while the flag is off. A visit may complete
without the new proof gate and the previous optional-photo behavior remains available.

Tests:

```text
php tools/visit_proof/tests/unit.php
php tools/realtime_requests/tests/model_integration.php
php tools/realtime_requests/tests/controller_orchestration_integration.php
```

Manual browser UAT is intentionally deferred until the end of development. It must cover
camera permission, location permission, low GPS accuracy, outside-radius rejection,
successful arrival proof, and retry after a failed submission on the target WebView/browser.
