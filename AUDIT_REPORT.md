# Doklinc CI3 HMVC Audit Report

Date: 2026-06-15
Scope: static audit of the public CodeIgniter 3 HMVC app, embedded `admin_menu` app, configs, logs, uploads, and bundled SQL dump. No application code was modified.

## Executive Summary

This is a legacy CodeIgniter 3 + Wiredesignz HMVC application. The public app is centered on warga/patient consultation, nakes/doctor request handling, chat/uploads, Firebase notification, and a super-app entry screen. It also contains legacy or duplicate modules from the original Ngaji Geh / broader platform fork. A separate full CodeIgniter app exists under `admin_menu/`.

The highest risks are production secrets committed in source, weak authentication, SQL injection, disabled CSRF/cookie protections, public unauthenticated upload endpoints, hardcoded absolute production paths, and session-path failures already visible in logs.

## Architecture

- Front controller: `index.php`.
- Framework: CodeIgniter 3 with HMVC extensions in `application/third_party/MX` and custom HMVC loader/router in `application/core`.
- Default route: `home` in `application/config/routes.php`.
- Global autoload: `session`, `database`, `form_validation`, and helpers `url`, `form`, `login`.
- Database: single default MySQL/MariaDB connection in `application/config/database.php`.
- Main public modules:
  - `super_app`: entry/loading screens.
  - `login`: SHA1 username/password login and location save.
  - `home`: warga dashboard, feeds, doctor discovery, ratings, request history.
  - `konsultasi`: warga consultation request creation, file upload, encrypted complaint/history, Firebase notification.
  - `home_nakes`: nakes dashboard, location updates, request acceptance, profile update.
  - `konsultasi_nakes`: completion of accepted requests, diagnosis, prescription/terapi.
  - `chat`: chat view and media uploads.
  - `notifikasi`: appears present but not active from routing.
- Embedded admin app: `admin_menu/` has its own CI app, config, database credentials, modules, uploaded assets, and vendor libraries.

## Critical Bugs

1. Unauthenticated `home` constructor no longer redirects.
   - Evidence: `application/modules/home/controllers/Home.php:9-12` comments out `redirect('../')` and only echoes `tidak ada`.
   - Impact: unauthenticated requests can still enter controller methods until method-level logic stops them. State-changing endpoints like `updateRequestById`, `deleterequestbyid`, `submit_rating`, and `getDuration` are exposed to unauthenticated execution paths.
   - Root cause: temporary debug change left in production path.

2. Login page route likely broken.
   - Evidence: `Login::index()` redirects unauthenticated users to `../` instead of loading `login_v` (`application/modules/login/controllers/Login.php:24-27`). The login view exists and posts to `login/auth`.
   - Impact: direct `/login` access can loop back to the parent/root instead of showing the login form. Super-app links to `login`, so this can break the intended entry flow.

3. Hardcoded absolute vendor path.
   - Evidence: `application/modules/konsultasi/controllers/Konsultasi.php:4` requires `/home/idbcsnet/public_html/cilegon_bersatu/vendor/autoload.php`.
   - Impact: deployment to `/www/wwwroot/doklinc.com/sehat_geh` or any staging path can fatal before the controller loads.

4. Session storage failure is already happening in production logs.
   - Evidence: `application/config/config.php:390` sets `sess_save_path` to `APPPATH . 'cache/sessions'`; logs show repeated `mkdir(): Invalid path` and `session_start(): Failed to initialize storage module` in `application/logs/log-2025-08-05.php`.
   - Impact: login/session behavior can fail globally and cause header warnings/redirect failures.

5. Consultation history null dereference.
   - Evidence: `application/modules/konsultasi/models/Konsultasi_m.php:153-158` calls `$query->riwayat` without checking if the row exists. Logs show repeated "Attempt to assign property 'riwayat' of non-object" around this flow.
   - Impact: new users or users without `tbl_riwayat` records can trigger PHP warnings and broken consultation page rendering.

6. Request acceptance does not verify doctor ownership.
   - Evidence: controller accepts posted `id_user` but model ignores it and updates by `request_id` only (`application/modules/home_nakes/controllers/Home_nakes.php:72-79`, `application/modules/home_nakes/models/Home_nakes_m.php:111-114`).
   - Impact: any logged-in user reaching the endpoint can accept/alter another doctor's request if they know the request ID.

## Security Issues

1. Secrets are committed in source.
   - Database credentials: `application/config/database.php:78-82` and `admin_menu/application/config/database.php`.
   - Encryption key: `application/config/config.php:330` and admin config.
   - API bearer token: `application/config/config.php:542`.
   - Firebase service account and device token: `application/config/firebase.php` and `application/third_party/firebase/...json`.
   - Google Maps keys: `application/modules/home_nakes/controllers/Home_nakes.php:88`, legacy `assets/bahan/*`.
   - Required action: rotate all exposed keys and move runtime secrets to environment/server config.

2. SQL injection in login and multiple models.
   - Login query directly interpolates username/password: `application/modules/login/models/Login_m.php:7-13`.
   - Home doctor/profile queries interpolate session-derived or posted values: `application/modules/home/models/Home_m.php:103-161`.
   - Nakes acceptance interpolates request ID and coordinates: `application/modules/home_nakes/models/Home_nakes_m.php:111-114`.
   - Nakes request read interpolates `request_id`: `application/modules/konsultasi_nakes/models/Konsultasi_nakes_m.php:10-18`.
   - Admin app has many direct SQL strings, including admin login: `admin_menu/application/modules/login/models/Login_m.php`.

3. Password hashing is weak and inconsistent.
   - Public login hashes posted password with SHA1: `application/modules/login/controllers/Login.php:32-33`.
   - Signup saves SHA1: `application/modules/sign_up/controllers/Sign_up.php:31-33`.
   - SQL dump includes SHA1 of `123456` for admin/dokter/warga seed users.
   - `application/helpers/login_helper.php` has `password_hash()` helpers, but they are not used by active public login/signup.

4. CSRF and cookie protections are disabled.
   - `csrf_protection = FALSE`: `application/config/config.php:468`.
   - `cookie_secure = FALSE`, `cookie_httponly = FALSE`: `application/config/config.php:423-424`.
   - Same issue exists in `admin_menu/application/config/config.php`.
   - Impact: session theft via XSS is easier, and state-changing POST endpoints are CSRFable.

5. Host header trust in `base_url`.
   - Evidence: `application/config/config.php:27-29` builds `base_url` from `$_SERVER['HTTP_HOST']`; admin app does the same.
   - Impact: cache poisoning, malicious links, generated URL manipulation, and callback/service-worker URL confusion if host header is not locked by the web server.

6. Public unauthenticated media upload endpoints.
   - Evidence: `application/modules/chat/controllers/Chat.php` has no constructor/session guard and exposes `foto()` and `video()` uploads.
   - Upload directories are public under `uploads/`; the repository already contains 143M of uploaded media.
   - Impact: disk exhaustion, illegal/PHI media exposure, unauthenticated storage abuse. File extension checks help but are not enough.

7. Public PHI/media exposure risk.
   - Consultation photos/videos are stored directly in public `uploads/` and referenced by URL.
   - Profile images and chat media are public.
   - No access-control wrapper, signed URL, retention policy, or per-record authorization is visible.

8. Admin app exposure risk.
   - `admin_menu/` is a full web app under the public project tree with its own login and modules.
   - It reuses hardcoded credentials and weak config.
   - If web-accessible, it greatly expands attack surface.

9. Firebase notification send endpoint accepts token from GET.
   - Evidence: `application/modules/konsultasi/controllers/Konsultasi.php:98-137`.
   - Impact: a logged-in user can send notification attempts to arbitrary provided FCM tokens; exceptions are echoed to the response.

10. TLS verification disabled for Google request.
   - Evidence: `CURLOPT_SSL_VERIFYPEER => false` in `application/modules/home_nakes/controllers/Home_nakes.php:91-96`.
   - Impact: man-in-the-middle risk for distance-matrix responses.

## Dead, Legacy, Or Duplicate Modules

Likely active public modules based on `AI_CONTEXT.md` and controller usage:
- `home`
- `home_nakes`
- `login`
- `konsultasi`
- `konsultasi_nakes`
- `chat`
- `notifikasi`
- `super_app`

Likely dead or legacy public modules:
- `sign_up`: not listed as active in `AI_CONTEXT.md`; still registers active warga accounts with SHA1.
- `splash_screen`: not listed as active; duplicates super-app/loading behavior.
- `application/modules/home/controllers/Home copy.php`: duplicate controller copy.
- `application/modules/home/views/home_nakes_v.php`: nakes view duplicated under home.
- `application/modules/home_nakes/views/home_nakes_v2.php`: alternate/old nakes view with debug artifacts.
- `application/modules/home_nakes/views/tes_save_lokasi.php`: test-only location page with hardcoded sample user.
- `application/modules/konsultasi_nakes/models/Konsultasi_m.php` and `views/konsultasi_v.php`: duplicate warga consultation pieces under nakes module.
- `application/modules/konsultasi/views/chat.php` and `application/modules/konsultasi_nakes/views/chat.php`: duplicate chat views alongside `chat` module.
- `assets/bahan/*.php`: standalone legacy scripts with direct MySQL and Google API experiments; logs show they fail using root/no password.

Embedded admin modules under `admin_menu/application/modules` are not in `AI_CONTEXT.md` but may still be operational:
- `login`, `home`, `kelola_dokter_nakes`, `kelola_keluhan`, `kelola_layanan_kesehatan`, `kelola_news_feed`, `kelola_warga`, `konsultasi_kesehatan`, `laporan`.
- Legacy/broader app modules likely unrelated to current Doklinc: `Pdfview`, `PrintPdf`, `landing_page`, `kelola_tindakan`, and psychology/test-report dependencies.

Do not delete any module directly from production. First confirm URL access logs, route usage, database usage, and whether Superapps Cilegon Juare links depend on it.

## Database Dependencies

Configured database:
- Host: `localhost`
- Database/user: `idbcsnet_railway`
- Driver: `mysqli`
- Same credential set appears in public and admin apps.

Tables required by current public app:
- `users`: login, roles, profile, doctor/patient identity, phone, puskesmas/remark.
- `locations`: user/doctor location tracking and distance calculations.
- `requests`: consultation request lifecycle; statuses `Pending`, `Accepted`, `Completed`.
- `konsultasi`: completed consultation diagnosis, advice, criteria, referral, files.
- `terapi`: prescription/therapy rows tied to `konsultasi.konsul_id`.
- `tbl_riwayat`: encrypted supporting/history data by `idUser`.
- `m_dokter`: legacy doctor catalog still joined in `home` for doctor names and listings.
- `feeds`: public feed/news cards.
- `rating`: doctor ratings.
- `fcm_tokens`: notification tokens joined to `users.no_hp`.
- `keluhan`: ICD/complaint autocomplete for nakes.
- `m_puskesmas`: used heavily by admin reports and likely tied to `users.remark`.

Tables/views referenced by embedded admin or legacy modules:
- Healthcare/admin: `users`, `requests`, `konsultasi`, `m_puskesmas`, `m_dokter`, `feeds`, `keluhan`.
- Landing/content: `tbl_home`, `tbl_about`, `tbl_news`, `tbl_services`, `tbl_services_detail`, `tbl_services_sub_detail`, `tbl_harga`, `tbl_contact`, `tbl_partner`, `tbl_team`, `tbl_user`, `m_level`, `tbl_faq`, `tbl_portofolio`, `tbl_harga_detail`, `tbl_alat_tes`.
- Psychology/test/report legacy: `tbl_trans_test_kesehatan_mental`, `tbl_trans_test_kepribadian`, `tbl_trans_test_papi`, `tbl_asoka_mates`, `tbl_trans_berkas_tes`, `tbl_order_detail`, and views such as `v_hitung_stress`, `v_hitung_kecemasan`, `v_hitung_depresi`, `v_test_papi_*`.

Bundled SQL dump limitation:
- `assets/railway.sql` only defines `m_dokter`, `medicalrecords`, `requests`, and `users`.
- It does not define many tables the current app requires, including `locations`, `konsultasi`, `terapi`, `tbl_riwayat`, `feeds`, `rating`, `fcm_tokens`, `keluhan`, and `m_puskesmas`.
- This dump is not a reliable restore artifact for the current app.

Database coupling risks:
- `users.remark` is overloaded as puskesmas/kode wilayah and used in joins/filtering.
- `users.role` controls app routing but has no centralized authorization guard.
- `requests.dokter_id` sometimes joins to `users.userId`, sometimes to `m_dokter.professional_id`; this mixed doctor identity model is fragile.
- Patient complaint/history data is encrypted using a single application key committed in source; key rotation would affect existing encrypted rows.

## Deployment And Runtime Risks

- `ENVIRONMENT` defaults to `development` unless `CI_ENV` is set in the web server (`index.php`), so production may display errors if server config is missing.
- `admin_menu` is 250M and appears to include vendor libraries and a separate CI system; this increases backup/deploy size and attack surface.
- `user_guide/` is web-facing CI documentation and should not be public in production.
- `application/logs/` are committed/present with production path and error data.
- `uploads/` contains 143M of user media in the repo/worktree, including medical consultation media.

## Fix Priority

1. Immediate containment:
   - Rotate DB, Firebase service account, API bearer token, and Google API keys.
   - Ensure `CI_ENV=production`.
   - Block direct public access to `application/`, `system/`, `user_guide/`, `assets/bahan/`, logs, Firebase JSON, and any unneeded `admin_menu/` paths at the web server.
   - Fix session save path to an absolute writable non-public directory.

2. Authentication/authorization:
   - Restore redirects in `Home::__construct()`.
   - Add role-based guards for `home`, `home_nakes`, `konsultasi`, `konsultasi_nakes`, and `chat`.
   - Verify request ownership on accept/complete/update/delete operations.

3. Input/database safety:
   - Replace raw interpolated SQL with Query Builder bindings or escaped parameters.
   - Prioritize login, request acceptance, request read/write, profile/rating, and admin login.

4. Password migration:
   - Move new passwords to `password_hash()`.
   - On successful SHA1 login, rehash to bcrypt/Argon-compatible `password_hash()`.
   - Disable or protect `sign_up` if it is not intended for public self-registration.

5. Web security:
   - Enable CSRF or add endpoint-specific token protection for AJAX.
   - Set `cookie_secure = TRUE` and `cookie_httponly = TRUE` under HTTPS.
   - Consider SameSite cookie policy through server/PHP config.

6. Upload/data protection:
   - Require authentication on chat upload endpoints.
   - Store sensitive consultation files outside web root or serve through authorized controller actions.
   - Add MIME validation, size quotas, per-user rate limits, and cleanup policy.

7. Cleanup after verification:
   - Quarantine or remove dead modules only after checking production traffic and Superapps integration.
   - Separate the admin app into its own protected deployment or remove from public app if unused.
   - Create a current schema dump/migration inventory for all required tables.

## Notes

This audit is static. I did not connect to the database, run the app, or validate external services. Findings should be verified on staging before production changes.
