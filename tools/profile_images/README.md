# Private profile images

Profile photos are served through `GET /profile/photo/<user-id>`. The endpoint
revalidates both users and permits only:

- the same active user;
- a Warga and the Nakes that handled one of their consultations;
- a canonical command-center and active personal Nakes in the same Puskesmas;
- a command-center viewing Warga belonging to a request in its own tenant.

Admin, anonymous, inactive, must-change, unrelated, and cross-tenant requests
fail closed with a 404 response. Missing or invalid media returns the local
default profile image only after authorization succeeds.

New Warga and Nakes uploads are written outside the public web root using
`DOCLINC_PROFILE_IMAGE_STORAGE_PATH`. The default is
`<site-parent>/private/profile-images`; the directory is mode `0700` and new
files are mode `0600`. Database values use the `profile-images/<hash>.<ext>`
key contract. Legacy hashed names remain readable by the authorized endpoint.

Before rollout:

1. Set `DOCLINC_PROFILE_IMAGE_STORAGE_PATH` in every PHP-FPM pool serving this
   checkout and create the directory for the application owner with mode 0700.
2. Run the scoped PHP and source tests below.
3. Include `nginx/doclinc-private-profile-images.conf` in the active vhost,
   test Nginx, then reload it. PHP can still read legacy files locally, while
   direct public HTTP access becomes unavailable.
4. Confirm self, consultation counterpart, command-center tenant, anonymous,
   unrelated, and cross-tenant cases with disposable fixtures before UAT.

```bash
php8.1 tools/profile_images/tests/unit.php
node tools/profile_images/tests/source_test.js
```

This feature does not alter the `users` schema and does not migrate or delete
legacy files. A separately reviewed migration can move legacy files later.
