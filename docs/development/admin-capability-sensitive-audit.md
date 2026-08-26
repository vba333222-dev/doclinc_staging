# Admin capability and clinical-access audit foundation

## Goal

Add default-off, independently testable Admin capabilities and fail-closed clinical access auditing without changing role enums, clinical persistence, or staging.

## Work items

1. Test capability independence, grant/revoke guards, last-super-admin protection, and disabled flags.
2. Test first/repeated/new-record/new-session clinical access reasons, nonce hashing, metadata-only rows, malformed reasons, and fail-closed writes.
3. Add an additive repeat-safe migration for capability state and the append-only clinical access ledger.
4. Add scoped feature-flag resolution, capability policy/service, audit ledger/session policy, and a guarded bootstrap tool.
5. Integrate a minimal default-off Admin clinical gateway while keeping ordinary Admin access denied.
6. Run targeted tests, regression checks, PHP lint, and security review without staging execution.
