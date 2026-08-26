# Schema baseline

- `users` keeps role enum semantics (`admin`, `dokter`, `warga`) and active status; no role enum change is permitted.
- `audit_logs` exists in the Admin compatibility migration with actor, action, entity, IP, user-agent, metadata JSON, and timestamp fields.
- `medicalrecords` contains record/request clinical fields and local newer attribution/anamnesis foundations; clinical access auditing must store metadata only.
- Existing login writes audit events through the application login model; shared request authorization writes audit events through `request_authz_helper`.
- Latest additive clinical foundation is `20260813000100_phase6_clinical_visit_foundation.php`; next migration must be additive and repeat-safe.
- The capability foundation adds `admin_user_capabilities` and append-only `clinical_access_audit`; no backfill or destructive DDL.
