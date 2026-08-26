# Local baseline

- Local HEAD: `70799e198fe8078f2ff9078cb64581cf865319d8`
- Remote reference: `origin/fix/config-session` at `70799e198fe8078f2ff9078cb64581cf865319d8`
- Divergence: no commit divergence; primary has protected uncommitted changes.
- Protected primary inventory: tracked and untracked UI, authorization, operations, clinical workflow, test, and local data artifacts remain in the primary repository. This worktree copies only relevant placement/authz/clinical overlays and records their hashes in `LOCAL_OVERLAY_MANIFEST.txt`.
- Relevant local flags: existing `Doclinc_feature_flags` resolves enabled values only for matching `staging`/`uat`; new capability flags follow this resolver and remain absent/false by default.
- Relevant migrations: latest application migration is `20260813000100_phase6_clinical_visit_foundation.php`; latest Admin migration is `20260622000100_admin_database_compatibility.php`.
- Relevant tests: `tools/phase6`, `tools/role_identity`, `tools/care_operations_foundation`, `tools/clinical_suggestions`, and disposable migration integration patterns.
