# DokLinc Visit Clinical Workflow — Core Design

## 1. Status and intent

This document defines the approved core architecture for DokLinc's Visit Clinical Workflow. It is a design specification, not an implementation record and not a deployment authorization.

The core goal is to separate physical Visit execution from clinical responsibility and closure while preserving the legacy CodeIgniter 3 application, existing Care Team semantics, existing physical Visit tracking, and backward compatibility.

The authoritative core flow is:

`Responsible Doctor disposition -> Command Center assign performer -> physical Visit + TTV -> Visit Result -> doctor-authorized review -> medical record finalization -> clinical closure`

The non-Visit flow is:

`Responsible Doctor disposition=non_visit -> existing consultation -> medical record finalization -> clinical closure`

The implementation must remain additive, feature-flagged, and backward-compatible. Existing requests must not receive fabricated clinical history or synthetic backfill.

## 2. Scope

### Included in the core

- Responsible Doctor Visit/non-Visit disposition.
- Versioned disposition revision before physical Visit execution starts.
- Command Center assignment and reassignment of a named Visit Performer.
- Existing five-state physical Visit lifecycle.
- Existing TTV measurements as the vital-sign source of truth.
- Versioned Visit Result drafting, submission, correction cycle, and immutable history.
- Responsible Doctor review of Visit Results.
- Explicit clinical finalization markers on `medicalrecords`.
- Immutable Clinical Amendments after finalization.
- Clinical closure for Visit and non-Visit requests.
- Server-side derived workflow state.
- Actor-specific read-model projection.
- Transaction, concurrency, idempotency, event, notification, and realtime contracts.
- Feature-flag enrollment behavior.

### Explicitly deferred

The following are not part of this core implementation:

- Canonical Encounter model.
- Prescription redesign.
- Controlled Responsible Doctor handover.
- Emergency escalation domain.
- Post-`en_route` Visit Performer reassignment.
- Visit termination/abort lifecycle after physical execution begins.
- Formal competency taxonomy or competency authorization engine.
- Automatic performer selection.
- Large Admin or Command Center redesign.
- External clinical integrations.

These may be added later without changing the core source-of-truth boundaries defined here.

## 3. Architectural invariants

1. `requests.visit_status` remains a strictly physical lifecycle:
   `not_started -> en_route -> arrived -> in_service -> completed`.
2. `requests.request_status` remains the request lifecycle and stays `Accepted` throughout an enrolled Visit until clinical closure succeeds.
3. Physical completion is not clinical completion.
4. No canonical `clinical_workflow_status` column or table is introduced.
5. Typed domain records are authoritative. `request_events` is a timeline projection, not canonical state storage.
6. Responsible Doctor and Visit Performer remain separate assignment authorities. A canonical personal doctor Visit Performer is an explicit exception: for the same request they may review their own submitted result, finalize the clinical record, and perform clinical closure. This does not transfer or replace the Responsible Doctor assignment.
7. Command Center is a facility/operational identity, not a clinician, even where legacy role fields could superficially resemble a clinician identity.
8. Existing compatibility fields may be projected during transition but must not override new canonical assignment records when the new workflow is authoritative.
9. Submitted clinical history is immutable. Corrections create new versions or amendments rather than reopening old records.
10. UI visibility is not an authorization boundary. Every action is revalidated server-side.
11. Feature-flag disablement must never downgrade an already-enrolled request to legacy behavior.

## 4. Canonical lifecycle

### 4.1 Visit disposition

The Responsible Doctor creates one active disposition for an `Accepted` request.

A disposition records:

- `decision`: `visit` or `non_visit`.
- `urgency`: `routine`, `priority`, or `urgent` for Visit decisions.
- `instructions`.
- `rationale`.
- nullable `required_profession` using the existing canonical Nakes profession taxonomy/code.
- nullable `required_competency_note` for human operational guidance only.
- Responsible Doctor attribution.
- version and supersession metadata.

`emergency` is intentionally not an urgency value in the core. Emergency handling belongs to the later escalation domain.

A disposition is immutable after creation except for explicit supersession metadata. Revision creates a new version and atomically supersedes the previous active row.

Revision rules:

- Before performer assignment: allowed.
- Performer assigned while `visit_status=not_started`: allowed.
- If the revised requirement makes the assigned performer ineligible, the old assignment is closed atomically and the request returns to waiting for assignment.
- From the successful transition to `en_route` onward: disposition is locked in the normal core flow.

`requests.consultation_mode` remains a compatibility projection while transition support is required.

### 4.2 Visit Performer assignment

`request_visit_performer_assignments` remains the canonical assignment history.

For the core:

- Command Center selects the named performer.
- One active performer assignment exists per request.
- Assignment/reassignment is allowed only while `visit_status=not_started`.
- Reassignment closes the old assignment historically and creates a new active assignment atomically.
- Once `en_route` succeeds, reassignment is locked in the core.

Performer eligibility requires all of the following:

- personal Nakes identity, not Command Center identity;
- active user/account;
- active placement in the responsible facility;
- match with `required_profession` when the disposition specifies one;
- otherwise satisfy the existing policy for an eligible clinical profession.

A doctor may serve as Visit Performer when the profession requirement and policy permit it. Serving as performer never grants Responsible Doctor authority.

Presence and workload may inform recommendations but are not authorization rules.

Assignment statuses have these semantics:

- `aktif`: performer still has obligations for this Visit.
- `diganti`: closed because Command Center reassigned before Visit start.
- `dibatalkan`: closed because the request/Visit was validly cancelled before Visit start.
- `selesai`: latest Visit Result was approved and the performer has no remaining Visit obligation.

Physical `visit_status=completed` does not close the assignment. `correction_required` keeps the assignment active. Approval of the latest Visit Result closes the active assignment to `selesai` in the same review transaction.

### 4.3 Physical Visit

Physical Visit retains exactly five ordered states:

`not_started -> en_route -> arrived -> in_service -> completed`

Transitions are forward-only and authorized through the active Visit Performer assignment.

The core does not reset the physical state, add clinical review states to `visit_status`, or overload `completed` to mean clinical completion.

Ordinary request cancellation is allowed only before `en_route`, subject to existing authorization. If cancellation succeeds, any active Visit Performer assignment is closed atomically. From `en_route` onward ordinary cancellation is rejected; post-start termination is deferred.

### 4.4 TTV

`request_vital_sign_measurements` remains the canonical source of truth for TTV.

The Visit Result references measurement IDs and does not copy TTV values.

A referenced measurement must belong to the same request and must be attributable to the valid performer/assignment context required by policy. Visit Result submission validates all references before commit.

### 4.5 Visit Result

The Visit Result records factual field evidence from the Visit. It is not a medical record and does not grant diagnosis authority.

Core content:

- observation/findings summary;
- server-schema-validated structured findings;
- server-schema-validated actions actually performed;
- performer notes;
- references to existing TTV measurement IDs;
- performer, assignment, request, timestamps, and version attribution.

It must not contain canonical fields for:

- diagnosis;
- prescription;
- final clinical assessment;
- doctor treatment plan.

Draft rules:

- The active performer may begin a draft at `arrived` or `in_service`.
- The active performer may continue editing the draft after physical completion until submission.
- Draft writes use optimistic concurrency via `draft_revision`.
- A stale expected revision is rejected instead of silently overwriting newer content.

Submission rules:

- Submission requires `visit_status=completed`.
- The submitting actor must own the relevant active assignment.
- Referenced TTV measurements must pass ownership/request/assignment validation.
- Submission freezes the result version permanently.
- A submitted row is never reopened.

Correction rules:

- `correction_required` does not reopen the submitted result.
- The performer creates a new result version referencing the previous version.
- Review history remains attached to the exact result version reviewed.
- A newer submitted version makes older versions ineligible to become the closure-driving approval.

### 4.6 Doctor Review

The latest submitted Visit Result version may be reviewed by either the active Responsible Doctor or the active canonical personal Visit Performer when that performer's Nakes profession is doctor. A doctor Visit Performer may review their own submitted result. A non-doctor performer, unrelated doctor, Command Center, Admin, and super-admin remain ineligible.

Core decisions:

- `approved`
- `correction_required`

`correction_required` requires a non-empty correction reason.

Each Visit Result version may have only one terminal review. Review rows are immutable.

Only the latest submitted result may receive a review that can affect the current workflow. An older result can never later become the valid closure-driving approval after a newer submitted version exists. Every review retains canonical assignment provenance: use the active Responsible Doctor assignment when the actor holds it; otherwise use the active Visit Performer assignment, which is permitted only for a personal doctor performer.

On `approved`:

- the review is persisted;
- the active performer assignment is closed to `selesai`;
- event/notification/outbox persistence occurs according to the transaction contract;
- the request is not yet set to `Completed`;
- the medical record is not automatically finalized.

On `correction_required`:

- the review is persisted;
- the active performer assignment remains `aktif`;
- the performer may produce the next version.

#### Clinical authority reconciliation

- `DOCTOR_PERFORMER_SELF_REVIEW=ALLOWED`: a canonical personal doctor Visit Performer may review their own submitted result.
- `DOCTOR_PERFORMER_CLINICAL_FINALIZATION=ALLOWED`.
- `DOCTOR_PERFORMER_CLINICAL_CLOSURE=ALLOWED`.
- `SAME_USER_RESPONSIBLE_AND_PERFORMER=ALLOWED`.
- `ROLE_DOKTER_ALONE_GRANTS_AUTHORITY=NO`.
- `RESPONSIBLE_DOCTOR_IMPLICIT_HANDOVER=NO`: selecting a doctor as performer never transfers or replaces the Responsible Doctor assignment.

The explicit exception is limited to a canonical active personal Nakes Visit Performer whose profession is doctor. A non-doctor performer remains limited to performer operations. Command Center, Admin, super-admin, and unrelated doctors remain operational/read-only or denied for clinical mutation.

### 4.7 Medical record finalization

The existing `medicalrecords` model remains in use for the core. Canonical Encounter migration is deferred.

Add explicit markers:

- `clinical_finalized_at`
- `clinical_finalized_by_user_id`

Before finalization, the record remains editable according to existing clinical authorization plus the new workflow guards.

For an enrolled Visit, finalization is allowed only after the latest submitted Visit Result has an `approved` Doctor Review. The actor must be the active Responsible Doctor or the active canonical personal doctor Visit Performer for that request.

For an enrolled non-Visit consultation, Visit Result and Doctor Review are not required.

Finalization is a distinct backend domain operation from clinical closure. A successful Doctor Review is not rolled back merely because later medical record finalization or closure fails.

Finalization changes markers only from unset to set. Repeated finalization calls are idempotent and must not create duplicate timestamps, events, or audit rows.

After finalization, normal editing is rejected. Subsequent corrections use Clinical Amendments only.

### 4.8 Clinical Amendments

A finalized medical record is never reopened.

Clinical Amendment consists of an immutable header plus ordered immutable items.

The header records:

- medical record;
- request;
- sequence;
- prior amendment linkage where applicable;
- author;
- mandatory reason;
- timestamp;
- idempotency key.

Each item records:

- whitelisted clinical field key;
- previous value snapshot;
- corrected value;
- deterministic item order.

The server maintains the field whitelist. Client input must never be converted directly into arbitrary SQL column names.

The clinical read model is the original finalized record plus ordered amendments.

Amendments are allowed after the request is `Completed` and do not reopen or change `request_status`.

In the core, only `clinical_finalized_by_user_id` may author an amendment. Authority expansion belongs to controlled doctor handover later.

### 4.9 Clinical closure

#### Visit

`closeClinicalVisit()` is allowed only when all invariants hold:

- request is `Accepted` and not cancelled;
- request is enrolled in the new workflow;
- active disposition is `visit`;
- `visit_status=completed`;
- latest Visit Result is submitted;
- latest Visit Result has `approved` Doctor Review;
- there is no newer unreviewed result;
- there is no pending correction cycle;
- medical record is clinically finalized;
- closure actor is the valid Responsible Doctor or canonical personal doctor Visit Performer for the core flow.

A successful closure changes `request_status` to `Completed` exactly once.

#### Non-Visit

`closeClinicalConsultation()` requires:

- request is `Accepted` and not cancelled;
- request is enrolled in the new workflow;
- active disposition is `non_visit`;
- medical record is clinically finalized;
- closure actor is the valid Responsible Doctor or canonical personal doctor Visit Performer.

It does not require `visit_status`, Visit Performer assignment, Visit Result, or Doctor Review.

## 5. Data model

Physical SQL integer sizes and the physical representation of existing status columns must follow the actual repository schema. The semantic fields and constraints in this section are normative. New profession storage must match the existing canonical profession code type and must not introduce a Visit-specific profession enum.

### 5.1 `visit_dispositions`

Required domain fields:

- `disposition_id` primary key.
- `request_id` foreign key to `requests.request_id`.
- `version_no` positive integer.
- `decision` with allowed values `visit`, `non_visit`.
- `urgency` with allowed values `routine`, `priority`, `urgent`; required for `visit`, null for `non_visit`.
- nullable `instructions`.
- nullable `rationale`.
- nullable `required_profession` using existing canonical profession representation.
- nullable `required_competency_note`.
- `created_by_user_id` Responsible Doctor attribution.
- `created_at` explicit timestamp.
- nullable `superseded_at`.
- nullable `superseded_by_disposition_id` self-reference.
- unique operation/idempotency key.

Constraints:

- unique `(request_id, version_no)`;
- at most one active disposition per request, enforced at DB level using a MariaDB-compatible unique active-row key such as a generated nullable request key;
- decision/urgency consistency enforced by service validation and, where compatible with repository migration conventions, a DB `CHECK` constraint;
- foreign-key deletion policy is `RESTRICT`.

### 5.2 `request_visit_performer_assignments` extension

Reuse the existing table. Preserve its current identity, history, and compatibility behavior.

Ensure the domain can store:

- terminal status `selesai` in addition to existing assignment statuses;
- `ended_by_user_id` where an equivalent field does not already exist;
- `end_reason` where an equivalent field does not already exist;
- `completed_at` where an equivalent completion timestamp does not already exist.

The implementation must inspect the current table first and reuse equivalent existing columns rather than duplicate them.

DB-level singularity must guarantee at most one `aktif` assignment per request.

### 5.3 `visit_results`

Required fields:

- `visit_result_id` primary key.
- `request_id` foreign key.
- `visit_assignment_id` foreign key.
- `version_no` positive integer.
- nullable `supersedes_result_id` self-reference.
- `performer_user_id` attribution snapshot.
- nullable/required `performer_staff_id` according to existing staff-link invariants; when a personal staff link is required by policy it must be persisted.
- `status`: `draft` or `submitted`.
- `draft_revision` non-negative integer.
- nullable `observation_summary`.
- structured `findings_json`.
- structured `actions_json`.
- nullable `performer_notes`.
- `created_at`.
- `updated_at`.
- nullable `submitted_at`.
- nullable `submitted_by_user_id`.
- nullable unique `submission_key`.

Constraints:

- unique `(visit_assignment_id, version_no)`;
- at most one active draft in a result chain/assignment using a MariaDB-compatible DB-level active-row uniqueness mechanism;
- submitted timestamp/actor consistency;
- v2+ must reference a valid predecessor from the same request/assignment chain;
- foreign-key deletion policy is `RESTRICT`.

Structured findings/actions must be validated against an explicit server-side schema. If stored using MariaDB JSON-compatible text storage, JSON validity must be enforced in a way compatible with MariaDB 10.11 and the repository migration style.

### 5.4 `visit_result_vital_sign_measurements`

Required fields:

- `visit_result_id` foreign key.
- `measurement_id` foreign key to `request_vital_sign_measurements`.
- `linked_at` timestamp.

Constraint:

- unique `(visit_result_id, measurement_id)`.

`measurement_id` is intentionally not globally unique across Visit Result versions because a valid historical measurement may remain relevant evidence in a corrected result version.

### 5.5 `clinical_reviews`

Required fields:

- `clinical_review_id` primary key.
- `request_id` foreign key.
- `visit_result_id` foreign key.
- `reviewer_user_id`.
- nullable `responsible_assignment_id` attribution to the Responsible Doctor assignment active for the review.
- nullable `reviewer_visit_assignment_id` attribution to the canonical Visit Performer assignment when that assignment authorizes the review.
- `decision`: `approved` or `correction_required`.
- nullable `correction_reason`, mandatory when decision is `correction_required`.
- nullable `review_notes`.
- `reviewed_at`.
- unique `idempotency_key`.

Constraint:

- unique `visit_result_id` so a result version has only one terminal review.
- at least one of `responsible_assignment_id` or `reviewer_visit_assignment_id` is required; both references must belong to the same request and preserve the assignment that authorized the review.

Foreign-key deletion policy is `RESTRICT`.

### 5.6 `medicalrecords` extension

Add only if equivalent fields do not already exist:

- nullable `clinical_finalized_at`.
- nullable `clinical_finalized_by_user_id` foreign key to `users`.

No new medical-record status enum is required for the core.

### 5.7 `clinical_amendments`

Required fields:

- `amendment_id` primary key.
- `record_id` foreign key to `medicalrecords.record_id`.
- `request_id` foreign key.
- `sequence_no` positive integer.
- nullable `previous_amendment_id` self-reference.
- `created_by_user_id`.
- non-empty `reason`.
- `created_at`.
- unique `idempotency_key`.

Constraint:

- unique `(record_id, sequence_no)`.

### 5.8 `clinical_amendment_items`

Required fields:

- `amendment_item_id` primary key.
- `amendment_id` foreign key.
- `item_order` positive integer.
- `field_key` from a server-side whitelist.
- `previous_value` snapshot.
- `corrected_value`.

Constraint:

- unique `(amendment_id, item_order)`.

### 5.9 `request_events` extension

Add nullable `domain_event_key` if no equivalent deduplication field exists.

Create a unique index on this key. MariaDB permits multiple `NULL` values under a unique index, allowing legacy rows to remain unchanged.

Example server-generated keys:

- `visit-disposition:{id}:created`
- `visit-result:{id}:submitted`
- `clinical-review:{id}:approved`
- `medicalrecord:{id}:finalized`
- `request:{id}:clinical-closed`

Message text must never be the deduplication mechanism.

## 6. Service boundaries

The implementation must avoid a monolithic workflow service.

Recommended responsibilities:

- `Visit_disposition_service`: create/revise disposition and lock rules.
- `Visit_assignment_service`: assign/reassign/close performer assignments.
- existing physical Visit service/model: physical status transitions only.
- `Visit_result_service`: draft, optimistic revision, TTV links, submit, and correction version creation.
- `Clinical_review_service`: result review and assignment completion on approval.
- clinical record service or focused extension: edit/finalize `medicalrecords`.
- `Clinical_amendment_service`: immutable post-finalization corrections.
- `Clinical_closure_service`: Visit/non-Visit closure invariants.
- `Visit_workflow_state_resolver`: read-only deterministic workflow state.

Existing Care Team services/policies continue to own identity, Responsible Doctor assignment, and existing care-team authorization semantics. Visit services own operation-specific workflow guards.

Controllers remain thin:

`HTTP authentication/CSRF/input -> domain policy/service -> response`

Controllers must not independently reconstruct workflow authorization with scattered role checks or direct multi-table mutations.

## 7. Authorization matrix

| Operation | Authorized actor | Core guard |
| --- | --- | --- |
| Create disposition | Responsible Doctor | `Accepted`, valid active responsibility |
| Revise disposition | Responsible Doctor | before successful `en_route`, version current |
| Assign performer | Command Center | Visit disposition, `not_started`, eligible performer |
| Reassign performer | Command Center | `not_started` only |
| Cancel before Visit start | existing authorized actor | before `en_route`; close assignment atomically |
| Advance physical Visit status | active Visit Performer | forward-only, active assignment |
| Record TTV | active Visit Performer | valid physical Visit state and assignment |
| Create/edit result draft | active Visit Performer | `arrived`, `in_service`, or `completed`; active assignment |
| Submit Visit Result | active Visit Performer | physical Visit completed and result valid |
| Review Visit Result | Responsible Doctor or canonical personal doctor Visit Performer | latest submitted result only; performer doctor may review own result |
| Finalize clinical record | Responsible Doctor or canonical personal doctor Visit Performer | Visit: latest result approved; non-Visit: applicable consultation guards |
| Close Visit | Responsible Doctor or canonical personal doctor Visit Performer | all Visit closure invariants |
| Close non-Visit consultation | Responsible Doctor or canonical personal doctor Visit Performer | non-Visit disposition + finalized record |
| Create Clinical Amendment | `clinical_finalized_by_user_id` | record finalized |

Explicit denials in the core:

- Command Center cannot perform clinical findings/review/finalization actions merely because of legacy role representation.
- Visit Performer cannot author canonical diagnosis, prescription, or final assessment through ordinary performer authority. The explicit doctor-performer exception permits review, clinical finalization, and clinical closure for that request.
- Admin and super-admin capabilities do not imply clinician authority.
- `clinical_audit` is a read/audit capability, not clinical mutation authority.
- Another doctor cannot review/finalize/amend solely because they are a doctor; controlled handover is deferred. A doctor with canonical Visit Performer authority is not an unrelated doctor and may exercise the explicit exception.

When the new workflow is authoritative, Responsible Doctor authority comes from the active Responsible Doctor assignment, not fallback `dokter_id` or `accepted_by_user_id`. Visit Performer authority comes from the canonical active performer assignment, not compatibility projection columns alone.

## 8. Transactions, locking, concurrency, and idempotency

### 8.1 Lock order

Workflow mutations that can race use the request row as the coarse serialization root, then lock domain rows in a consistent order:

`requests -> active disposition -> active performer assignment -> result -> review/medicalrecord`

A service may lock only the subset it requires, but when multiple categories are needed their relative order must remain consistent.

### 8.2 Disposition revision

One transaction must:

- lock request and current active disposition;
- lock active performer assignment when present;
- verify the physical Visit has not successfully reached `en_route`;
- supersede the old disposition;
- create the new version;
- close an ineligible `not_started` performer assignment when required;
- persist event/outbox/notification records required by the domain mutation.

Two concurrent revisions must not produce two active dispositions.

### 8.3 Performer assignment/reassignment

One transaction must validate request/disposition/physical state and performer eligibility, then create the active assignment and update compatibility projection as needed.

Reassignment must close the old assignment and create the replacement atomically.

Two concurrent assignment attempts must result in one winner and one safe conflict/refresh response, never two active performers.

### 8.4 Physical-start races

Disposition revision and performer reassignment must serialize against transition to `en_route`.

If `en_route` wins, revision/reassignment loses and returns a conflict. If revision/reassignment commits first, the physical-start attempt must re-evaluate the new authoritative state before proceeding.

### 8.5 Visit Result draft

Every draft save includes the caller's expected `draft_revision`.

A successful save increments the revision. A stale revision returns a conflict and does not overwrite newer data.

### 8.6 Visit Result submission

Submission transaction locks the request, assignment, and target draft; verifies physical completion, ownership, current version, predecessor correction state where applicable, and TTV references; then freezes the result as submitted.

Duplicate/retried submission must not create a second submitted version.

### 8.7 Doctor Review

Review transaction locks the request, target result, current latest submitted result, Responsible Doctor context, and active performer assignment as required.

It must verify the target is still the latest submitted version and has no terminal review.

On approval, assignment completion to `selesai` is part of this same transaction.

### 8.8 Medical record finalization

Finalization locks the request and clinical record plus current review state required to validate eligibility.

Markers change only once. Repeated valid calls return an idempotent already-finalized outcome and do not duplicate events or audit data.

### 8.9 Clinical closure

Closure locks the request first and revalidates every closure prerequisite using canonical records.

Concurrent closure attempts may produce one actual state change; subsequent calls return idempotent already-completed success without duplicate event/audit/notification persistence.

### 8.10 Clinical Amendments

Header and all amendment items are inserted in one transaction.

An operation/idempotency key prevents duplicate amendments caused by network retry. Created amendments are immutable.

### 8.11 Events and delivery persistence

When domain state, `request_events`, notifications, and `realtime_outbox` use the same database and existing repository architecture supports it, the persistence records representing the mutation must be written in the same transaction.

Actual websocket/notification delivery occurs after commit through the existing delivery mechanism.

The design does not permit an HTTP controller to mutate the domain and then depend on a fragile best-effort inline notification as the sole event record.

## 9. Domain error contract

Domain services return stable machine-readable outcomes. Representative error codes include:

- `DISPOSITION_LOCKED`
- `DISPOSITION_VERSION_CONFLICT`
- `PERFORMER_NOT_ELIGIBLE`
- `PERFORMER_ASSIGNMENT_CONFLICT`
- `VISIT_ALREADY_STARTED`
- `STALE_DRAFT_REVISION`
- `RESULT_NOT_LATEST`
- `RESULT_ALREADY_SUBMITTED`
- `REVIEW_ALREADY_EXISTS`
- `CORRECTION_REQUIRED`
- `CLINICAL_RECORD_NOT_FINALIZED`
- `CLINICAL_RECORD_ALREADY_FINALIZED`
- `CLINICAL_CLOSURE_NOT_READY`

The HTTP layer maps domain outcomes to appropriate 403/409/422 responses. UI code must not parse human-readable error strings to determine workflow state.

## 10. Derived workflow state

No workflow status is persisted as canonical state.

`Visit_workflow_state_resolver` derives state from canonical records including:

- request status;
- active disposition;
- active performer assignment;
- physical Visit status;
- latest Visit Result/version;
- exact Doctor Review for the latest result;
- medical record finalization markers.

Representative machine states:

- `WAITING_DOCTOR_DISPOSITION`
- `WAITING_PERFORMER_ASSIGNMENT`
- `VISIT_NOT_STARTED`
- `VISIT_EN_ROUTE`
- `VISIT_ARRIVED`
- `VISIT_IN_SERVICE`
- `WAITING_VISIT_RESULT`
- `WAITING_DOCTOR_REVIEW`
- `CORRECTION_REQUIRED`
- `WAITING_CLINICAL_FINALIZATION`
- `READY_FOR_CLOSURE`
- `COMPLETED`

The resolver is read-only and performs no hidden repair or state mutation.

## 11. Actor-specific presentation

Canonical machine state does not imply identical wording/data exposure for every actor.

A presenter/projector converts the resolver output into safe actor-specific state.

Examples:

- Responsible Doctor on `WAITING_DOCTOR_REVIEW`: "Menunggu review Anda".
- Command Center: "Menunggu review dokter".
- Visit Performer: "Hasil telah dikirim".
- Warga: "Hasil kunjungan sedang ditinjau".

`CORRECTION_REQUIRED` is operational/clinical information for the performer and Responsible Doctor. Warga should receive a neutral progress message rather than internal correction semantics.

### Responsible Doctor surface

Expose:

- disposition creation/revision while permitted;
- Visit progress;
- submitted Visit Result and referenced TTV;
- review action;
- clinical record editing/finalization;
- clinical closure.

Visit Result is evidence. `medicalrecords` remains the doctor's clinical finalization workspace for the core.

### Command Center surface

Expose operational information:

- urgency;
- profession requirement;
- assignment state;
- physical Visit progress;
- placement/presence/workload context suitable for operations;
- high-level derived workflow state.

Do not grant clinical review/finalization authority or expose clinical detail merely because Command Center manages the facility workflow.

### Visit Performer surface

Expose:

- assignment and doctor instructions;
- physical Visit controls;
- TTV entry;
- Visit Result drafting/submission;
- correction reason when applicable.

After latest-result approval closes the assignment to `selesai`, performer workflow actions become read-only through the normal core path.

### Warga surface

Expose patient-safe progress.

Existing physical Visit tracking may continue to show travel/arrival/service progress. Once `visit_status=completed`, the application must not imply that the entire consultation is clinically completed. Until clinical closure, present a safe state such as "Kunjungan selesai — hasil sedang diproses".

## 12. Notifications and realtime

Reuse the existing notification and realtime infrastructure.

Domain events may include, subject to existing event naming conventions:

- Visit disposition created/revised.
- Visit Performer assigned/reassigned.
- Visit Result submitted.
- Visit Result correction required.
- Visit Result approved.
- Clinical record finalized.
- Consultation clinically completed.
- Clinical Amendment created.

Realtime payloads should carry identifiers and state hints, not become a second canonical workflow state. Clients refresh the authoritative read model after receiving a relevant event.

Existing `realtime_outbox` idempotency is reused. The implementation must audit the existing notification writer for recipient-scoped deduplication; if no equivalent mechanism exists, add a focused delivery key rather than building a second notification subsystem.

## 13. Feature flag and enrollment

The feature flag controls whether new requests may enroll in the new Visit Clinical Workflow. It does not turn the workflow off for already-enrolled requests.

Rules:

- Flag OFF + request has never received a new `visit_disposition`: use legacy behavior.
- Flag ON + Responsible Doctor creates the first new disposition: the request becomes enrolled.
- Once enrolled, the request stays on the new workflow through completion even if the global flag is later disabled.
- Disabling the flag stops new enrollment only.

The existence of a new disposition is the enrollment marker. No additional `workflow_version` column is required for the core.

## 14. Legacy compatibility and migration behavior

No migration may fabricate disposition, Visit Result, Doctor Review, finalization, or amendment history for legacy requests.

No clinical backfill is required to activate the core feature.

Legacy request columns may remain compatibility projections during transition, but when a request is enrolled the new typed domain records define authority and workflow state.

Migrations must be:

- additive/backward-compatible;
- compatible with MariaDB 10.11;
- safe to re-run according to the repository's established migration mechanism;
- non-destructive;
- free from `DROP`, `TRUNCATE`, destructive reset, or broad data normalization;
- free from staging data mutations outside a separately approved validation procedure.

Before writing migrations, implementation work must inspect actual table/column/index definitions and reuse equivalent fields/constraints where they already exist. This is a schema reconciliation requirement, not an open design decision.

## 15. Migration grouping

Use natural domain-focused migration names. Do not introduce tool-specific repository artifact names or numbered development-phase naming.

Logical migration groups:

1. Visit clinical workflow foundation: disposition, result, TTV references, review, request-event deduplication, and clinical finalization markers.
2. Clinical amendment foundation: amendment header and items.
3. Care Team compatibility extension: assignment completion/end-attribution fields only where the current schema lacks equivalent fields.

The physical count of migration files may follow the repository's normal migration conventions as long as the domain boundaries and rollback/safety expectations remain clear.

## 16. Testing and acceptance contract

Implementation is not accepted based only on happy-path HTTP tests.

### Required domain tests

Cover at minimum:

- disposition create/revise/version/supersession;
- disposition lock after successful `en_route`;
- profession-based performer eligibility;
- performer assignment/reassignment/cancellation history;
- physical Visit forward-only behavior;
- TTV ownership/reference validation;
- Visit Result draft optimistic concurrency;
- Visit Result submission immutability;
- correction version chain;
- one terminal Doctor Review per result;
- latest-result-only review rule;
- performer assignment remains active on correction;
- performer assignment closes to `selesai` on approval;
- Visit medical record cannot finalize before approval;
- finalized medical record cannot normal-edit;
- immutable Clinical Amendment and ordered read projection;
- Visit closure prerequisites;
- non-Visit closure without Visit Result/Review;
- idempotent finalization and closure;
- actor-specific authorization denials.

### Required concurrency tests

Prove these races:

- two simultaneous disposition revisions -> one active disposition;
- two simultaneous performer assignments -> one active performer;
- concurrent performer reassignment -> one valid active replacement;
- disposition revision vs `en_route` -> only one valid ordering succeeds;
- performer reassignment vs `en_route` -> only one valid ordering succeeds;
- stale draft save -> stale write rejected;
- duplicate Visit Result submission -> one submitted version;
- concurrent creation of correction result version -> one valid next draft/version;
- duplicate Doctor Review -> one terminal review;
- review approval vs invalid newer-result creation -> approval remains bound to the actual latest submitted version;
- duplicate medical record finalization -> one attribution/timestamp/event;
- duplicate clinical closure -> one request completion/event;
- duplicate Clinical Amendment POST -> one immutable amendment;
- Nakes placement transfer activation vs active Visit assignment -> existing governance blocker remains effective.

### Required authorization negatives

Prove at minimum:

- Command Center cannot act as Responsible Doctor.
- Command Center cannot submit performer clinical evidence unless it is also a separately valid personal performer identity through the canonical personal-Nakes path; facility identity alone never suffices.
- Non-doctor Visit Performer cannot review/finalize/close; a canonical personal doctor Visit Performer may do so under the explicit doctor-performer exception.
- Admin/super-admin cannot mutate clinical workflow through governance authority.
- unrelated doctor cannot review/finalize/amend in the core.
- Warga cannot access clinician mutation endpoints.
- compatibility columns alone cannot grant enrolled-workflow authority.

### Regression expectations

Existing governance foundation and Visit proof regressions must remain green. Legacy behavior must remain unchanged when the feature is OFF for non-enrolled requests.

Valhalla remains staging-only. Local/disposable tests must not install/run Valhalla, must not call staging Valhalla, and must not add routing fallbacks merely to satisfy local tests. Routing/path validation, if touched by a regression surface, is validated only in a separately controlled staging procedure.

Requests 47 and 52 must never be used for mutation-based validation; Request 52 remains observe-only/quarantined.

## 17. Release and operational safety

This design does not authorize deployment or feature activation.

Implementation/release work must preserve the existing protected-worktree discipline:

- local repository is implementation authority;
- GitHub is baseline/reference, not a reason to overwrite newer local work;
- no reset/restore/clean/stash against protected user changes;
- no broad staging commands such as `git add .` or `git add -A`;
- clean-worktree patch reconciliation is used before integrating intended changes into the protected primary worktree;
- secrets, credentials, dumps, patient data, logs, backups, and runtime artifacts are never committed;
- feature defaults remain OFF until the explicit release gate is satisfied;
- no staging deployment occurs merely because implementation tests pass.

## 18. Future extension points

The core intentionally leaves clean extension points for later maturity work:

- Responsible Doctor handover can expand reviewer/finalizer/amendment authority through explicit historical assignment transfer rather than loosening current policies.
- Emergency escalation can become a separate typed domain referenced by request/disposition/Visit without adding `emergency` to ordinary urgency.
- Post-start Visit termination/reassignment can extend assignment and physical lifecycle policies without overloading current cancellation semantics.
- Formal competency taxonomy can replace informational competency notes without changing profession requirements.
- Canonical Encounter can later reference stable request, disposition, Visit Result, review, medical record, and amendment identifiers.

## 19. Approved source-of-truth summary

For an enrolled Visit request:

- Responsible Doctor: active Responsible Doctor assignment.
- Visit/non-Visit decision: active `visit_dispositions` row.
- Visit Performer: active/historical `request_visit_performer_assignments`.
- Physical progress: `requests.visit_status` plus existing physical evidence/location stores.
- TTV: `request_vital_sign_measurements`.
- Field Visit evidence: versioned `visit_results` plus TTV reference table.
- Review: `clinical_reviews` attached to exact Visit Result versions.
- Review authority: active Responsible Doctor assignment or, for the explicit exception, active canonical personal doctor Visit Performer assignment; provenance is stored on the review.
- Clinical final record: finalized `medicalrecords` plus ordered `clinical_amendments`.
- Request completion: `requests.request_status` changed only by the applicable clinical closure operation.
- Timeline: `request_events` as projection/history.
- Delivery: existing notifications and `realtime_outbox`.
- UI state: deterministic server-side resolver plus actor-specific presenter.

This separation is the core safety property of the design: physical execution, performer evidence, doctor review, clinical record finalization, and request closure remain distinct, attributable, testable operations.
