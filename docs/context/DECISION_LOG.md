# Decisions

- Local source wins over remote reference.
- Capability codes are independent; `users.role` remains `admin`.
- Capability state is current-state data; grant/revoke events use existing `audit_logs`.
- Clinical access is append-only metadata, never a clinical-content shadow store.
- Logical clinical audit session uses a random authenticated-session nonce hashed before persistence.
- Feature flags default off and are environment-gated using existing `Doclinc_feature_flags` conventions.
- No UI redesign or staging/database execution in the Admin capability workstream.
- Local uncommitted clinical/authz overlays were copied into this disposable worktree for source parity; they are documented overlays, not part of the capability implementation.
