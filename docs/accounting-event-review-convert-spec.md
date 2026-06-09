# Accounting Event Review / Convert Workflow Spec

Status: Spec completed + Phase 4A review workflow completed + Phase 4B void workflow completed + Phase 4C account mapping spec completed + Phase 4C-2 config-based mapping foundation completed + Phase 4D-1 Convert Skeleton completed + Phase 4D-2 Journal Draft Generation Spec completed + Phase 4D-2A Convert Preflight Service completed + Phase 4D-2A-1 Runtime Mapping Decision Spec completed + DB-backed mapping foundation code exists and requires focused verification / normalization.
Scope: review / void / convert skeleton and `AccountingEventJournalDraftPreflightService` preview are implemented; journal draft generation spec exists at `docs/accounting-event-journal-draft-generation-spec.md`, but active route-wired journal draft creation, revenue recognition, COGS recognition, posting, and additional accounting runtime behavior remain out of scope until explicit Phase 4D-2B acceptance.
This document reflects the implemented review, void, convert skeleton, and preflight workflows. It does not implement journal draft creation, revenue recognition, COGS recognition, posting, or additional runtime behavior.

## 1. Purpose

This document is the design specification before Accounting Event Phase 4. It defines the safe workflow boundary for:

```txt
pending Accounting Event
-> reviewed Accounting Event
-> future manual journal draft generation
```

Core positioning:

```txt
Business Document
-> Pending Accounting Event
-> Accounting Review
-> Reviewed Accounting Event
-> Manual Journal Draft Generation
-> Accountant reviews Journal Draft
-> Accountant posts Journal
```

Accounting Event is an accounting candidate event. It is not an official journal entry.

- `reviewed` does not mean posted.
- `converted` does not mean posted.
- Journal posting must continue through the existing Accounting Journal post workflow.
- Phase 4A implemented pending -> reviewed. Phase 4B implemented pending / reviewed -> voided. Phase 4D-1 implemented convert permission / route / request / policy / controller skeleton and mapping fail-safe only; journal draft generation remains future work.
- Detailed draft generation specification exists at `docs/accounting-event-journal-draft-generation-spec.md`; runtime remains not implemented.

## 2. Current Repo State

Accounting Event Phase 1 completed:

- `accounting_events` table
- `AccountingEvent` model
- `config/accounting_events.php`
- `AccountingEventTest`

Accounting Event Phase 2 completed:

- `accounting-events` module
- `module.accounting.events.view`
- readonly index/show routes
- `AccountingEventController` index/show
- `AccountingEventPolicy` viewAny/view
- React readonly Index / Show
- `AccountingEventWorkspaceTest`

Accounting Event Phase 3 completed:

- `AccountingEventService`
- successful completion creates one pending Accounting Event
- `source_type = vehicle_sale_completion`
- `event_type = vehicle_sale_completed`
- `status = pending`
- `AccountingEventCompletionIntegrationTest`

Accounting Event Phase 4A completed:

- reviewed_at migration added
- `module.accounting.events.review` added
- review route added
- review request / policy / controller / UI / tests added

Accounting Event Phase 4B completed:

- `module.accounting.events.void` added
- void route added
- void request / policy / controller / UI / tests added
- pending / reviewed -> voided exists
- converted event void remains not allowed
- already voided event cannot be voided again

Accounting Event Phase 4C completed:

- Account Mapping Spec completed
- `docs/accounting-event-account-mapping-spec.md` exists

Accounting Event Phase 4C-2 completed:

- Config-based Mapping Foundation completed
- `config/accounting_event_mappings.php` exists
- `AccountingEventMappingConfigTest` exists
- mapping metadata exists and remains disabled for runtime draft generation

Accounting Event Phase 4D-1 completed:

- `module.accounting.events.convert` added
- PATCH route `/employee-system/accounting/events/{accountingEvent}/convert`, route name `employee-system.accounting.events.convert`
- `ConvertAccountingEventRequest` added
- `AccountingEventPolicy::convert` added
- `AccountingEventController::convert` added
- Show payload includes `can.convert`
- `AccountingEventConvertTest` exists
- admin / accounting default to convert permission; viewer does not
- Staff Permission matrix shows accounting.events convert action label `轉傳票`
- convert checks same tenant, reviewed, not voided, not converted, convert permission, mapping exists, source_type match, and mapping enabled
- mapping disabled fail-safe returns 422 because `vehicle_sale_completed.enabled = false`

Accounting Event Phase 4D-2-spec completed:

- Journal Draft Generation Spec completed
- `docs/accounting-event-journal-draft-generation-spec.md` exists
- future header / line / permission / transaction / audit / testing boundaries are documented
- future runtime split recommends Phase 4D-2A preflight only and Phase 4D-2B revenue-side draft generation only
- runtime journal draft generation remains not implemented

Accounting Event Phase 4D-2A completed:

- `AccountingEventJournalDraftPreflightService` exists
- preflight validates permissions / mapping / accounts / amount / preview / `AccountingJournalValidator`
- preflight only returns validated backend-generated preview
- runtime still does not create journal draft
- runtime still does not create journal lines
- runtime still does not set status converted
- runtime still does not write `converted_journal_entry_id`
- runtime still does not write `accounting_event.converted` audit
- mapping config default remains disabled and no actual runtime account IDs in committed config
- Phase 4D-2A-2 Verification / Normalization remains the next step before any direct Phase 4D-2B work
- COGS / tax / overpayment / refund / reversal remains backlog
- DB-backed mapping migration / model / resolver / controller / UI / routes / permissions may already exist, but this checkpoint is not 4D-2B.
- Convert route still calls `AccountingEventJournalDraftPreflightService` only；`AccountingEventConvertService` exists but is not wired into `AccountingEventController::convert()`。

Current business flow:

```txt
Customer
-> Vehicle Sale
-> Receivables / Payments
-> Mark Sold
-> Complete Transaction / Confirm Delivery
-> Pending Accounting Event
-> Accounting Event readonly workspace
```

Currently not completed:

- Accounting Event successful conversion to Journal Draft
- Journal Draft generation from Accounting Event
- Journal Draft generation from Accounting Event
- Revenue Recognition
- COGS Recognition
- Profit / Gross Margin payload
- AR / AP
- Cash / Bank
- Invoice
- Reports
- Refund / reversal

## 3. Design Principles

- Business document is the source.
- Accounting Event is a candidate accounting layer.
- Review is human-controlled.
- Convert is human-controlled.
- No automatic posting.
- No automatic revenue recognition.
- No automatic COGS recognition.
- No profit / gross margin payload.
- No hard-coded debit / credit mapping in this phase.
- No account IDs should be guessed.
- Tenant scope must remain `company_id` / `branch_id` based.
- Frontend visibility is UX only; backend remains source of truth.
- Audit trail must exist for review / convert / void in future.

## 4. Status Semantics

Current / future Accounting Event statuses are already defined in `config/accounting_events.php`:

```txt
pending
reviewed
converted
voided
```

Status semantics:

- `pending`: created by a business event and waiting for accounting review.
- `reviewed`: accountant confirmed the event can be used as the basis for journal draft generation.
- `converted`: journal draft has been generated from the event and `converted_journal_entry_id` is retained.
- `voided`: event is voided and must retain `void_reason` / `voided_by` / `voided_at`.

Boundaries:

- `reviewed` does not mean a journal draft has been created.
- `converted` does not mean the journal has been posted.
- `voided` must not delete the original event.
- No workflow should automatically move directly from `pending` to `converted`.
- No status should directly create a posted journal.

## 5. Current Review Workflow

Review behavior is implemented as a human-controlled accounting action, not as an automatic completion side effect.

Current implementation:

```txt
pending Accounting Event
-> accountant opens readonly detail
-> accountant checks source, amount, receivable status, payload
-> accountant enters review_note
-> status becomes reviewed
-> review_note / reviewed_by / reviewed_at are recorded
-> accounting_event.reviewed audit log is written
```

Current table state includes:

```txt
review_note
reviewed_by
reviewed_at
```

Current implementation does not generate journal draft.

Review conditions:

- Only `pending` events can be reviewed.
- Reviewed event must remain same tenant.
- Voided event cannot be reviewed.
- Converted event cannot be reviewed again unless future rollback exists.
- `review_note` is the only allowed review form field.

Implemented permission:

```txt
module.accounting.events.review
```

Permission boundaries:

- This permission is implemented.
- `module.accounting.events.view` can only view and must not allow review.
- `module.accounting.view` must not be used as review permission.

## 6. Convert Workflow

Current Phase 4D-1 Convert Skeleton behavior:

```txt
reviewed Accounting Event
-> user with module.accounting.events.convert
-> scoped tenant query
-> policy convert guard
-> mapping exists / source_type / enabled checks
-> mapping enabled=false
-> 422 fail-safe
-> no state change
-> no journal draft
```

Current fail-safe messages:

- Mapping disabled：`會計事件映射尚未啟用，無法產生傳票草稿。`
- Mapping missing：`找不到會計事件映射設定，無法產生傳票草稿。`
- Mapping source_type mismatch：`會計事件映射與來源類型不一致，無法產生傳票草稿。`

Current convert boundaries:

- Convert skeleton exists.
- Cross tenant convert returns 404 before authorization details leak.
- view-only / review-only / void-only / `module.accounting.view` cannot convert.
- Forbidden payload returns 403.
- Phase 4D-1 does not create `AccountingJournalEntry`.
- Phase 4D-1 does not create `AccountingJournalEntryLine`.
- Phase 4D-1 does not write `converted_journal_entry_id`.
- Phase 4D-1 does not change `accounting_events.status` to `converted`.
- `AccountingEventConvertService` exists as candidate future 4D-2B logic, but current convert route must not wire or call it.

Current Phase 4D-2A Convert Preflight Service behavior:

- Preflight requires `module.accounting.events.convert` and `module.accounting.journals.create`.
- Preflight validates tenant scope, reviewed status, non-voided state, no `converted_journal_entry_id`, positive amount, mapping exists / source_type / enabled, required runtime accounts, account company / branch / active / type, and draft line balance.
- Preflight returns a safe preview header and two revenue-side lines only.
- Preflight does not create `AccountingJournalEntry`.
- Preflight does not create `AccountingJournalEntryLine`.
- Preflight does not update `accounting_events.status`.
- Preflight does not write `converted_journal_entry_id`.
- Preflight does not write `accounting_event.converted` audit.
- Preflight does not generate COGS / inventory / tax / overpayment / rounding / refund / reversal lines.

Future Phase 4D-2+ Draft Generation behavior should generate only a manual journal draft, never a posted journal.

Detailed draft generation rules and boundaries are defined in `docs/accounting-event-journal-draft-generation-spec.md`.

Future draft generation flow:

```txt
reviewed Accounting Event
-> accountant clicks Generate Journal Draft
-> backend creates AccountingJournalEntry status=draft
-> backend creates accounting_journal_entry_lines
-> event.status becomes converted
-> event.converted_journal_entry_id points to draft journal
-> accountant reviews draft
-> accountant posts journal through existing journal post workflow
```

Convert boundaries:

- Convert can only run from `reviewed` event.
- Convert must not allow `pending` event to directly generate journal draft.
- Convert must not auto post.
- Convert must not skip `AccountingJournalValidator`.
- Convert must not bypass `module.accounting.journals.create/update/post` permission logic.
- Convert must not modify existing posted / voided journal.
- Convert must not create posted journal.
- In Phase 4D-2+, after successful draft generation, event status should become `converted`, but journal status must remain `draft`.
- Convert needs an idempotency guard: if the event already has `converted_journal_entry_id`, it must not create a second journal draft.
- Convert still must not run unless mapping is enabled and validated.
- Current mapping config has `enabled = false`.
- Current journal line templates have `enabled = false`.
- Future convert must fail safely if mapping disabled / missing.
- Future convert must still create draft only, never posted.

Implemented permission:

```txt
module.accounting.events.convert
```

Permission boundaries:

- This permission is implemented.
- Convert is not review.
- `module.accounting.view` cannot replace convert.
- `module.accounting.events.view` must not convert.
- `module.accounting.events.review` should not automatically equal convert.
- Whether convert also requires `module.accounting.journals.create` must be explicitly decided during future implementation.

## 7. Void Workflow

Current void behavior retains the event and records why it is no longer usable.

Suggested flow:

```txt
pending / reviewed Accounting Event
-> accountant enters void_reason
-> status becomes voided
-> voided_by / voided_at / void_reason recorded
```

Void boundaries:

- Current implementation: pending / reviewed -> voided exists.
- Current implementation records `void_reason`, `voided_by`, `voided_at`.
- Current implementation writes `accounting_event.voided` audit log.
- Current implementation preserves review fields.
- Current implementation does not cancel journal draft.
- Current implementation does not reverse posted journal.
- Current implementation does not process refund / reversal.
- `converted` event should not be directly voided unless future cancellation / reversal logic for generated journal draft is handled first.
- If event is converted and the journal draft is still `draft`, a future design may support cancel draft + void event. This document does not implement it.
- If journal is posted, event cannot simply be voided. It must go through reversal / refund / return flow.
- `void_reason` should be required.
- Void must not delete the event.
- Converted event simple void remains not implemented.
- Posted journal reversal remains future flow.
- Refund / return remains future flow.

Implemented permission:

```txt
module.accounting.events.void
```

## 8. Journal Draft Generation Boundary

Future event -> journal draft generation must not hard-code accounting decisions.

Currently prohibited:

- No hard-coded account IDs.
- No hard-coded debit / credit mapping.
- No automatic revenue account selection.
- No automatic COGS account selection.
- No automatic inventory / vehicle cost account selection.
- No profit / gross margin calculation.

Before future journal draft generation, the system needs:

- account mapping configuration
- revenue account selection rule
- COGS / vehicle cost account selection rule
- tax handling decision
- overpaid handling decision
- refund / reversal policy

Current `AccountingJournalEntry` schema / model already includes:

- `company_id`
- `branch_id`
- `journal_number`
- `entry_date`
- `summary`
- `status`
- `posted_at` / `posted_by`
- `voided_at` / `voided_by` / `void_reason`
- `attachment`
- `source_type`
- `source_id`
- `created_by`
- `updated_by`

Future draft generation field direction:

AccountingJournalEntry:

- `company_id`
- `branch_id`
- `journal_number`
- `entry_date`
- `summary`
- `status = draft`
- `source_type = accounting_event`
- `source_id = accounting_events.id`
- `created_by`
- `updated_by`

AccountingJournalEntryLine:

- `account_id`
- `description` or current `memo`
- `debit`
- `credit`

Current line schema uses `memo`, not `description`. Future implementation should either map the conceptual description to `memo` or explicitly add / rename fields in a separate migration. This document does not change schema.

## 9. Revenue / COGS Recognition Boundary

Recognition boundary:

```txt
Completion is the candidate point for revenue / COGS recognition.
Pending Accounting Event is the candidate layer.
Review validates the candidate.
Convert may generate draft entries in the future.
Posting is still manual.
```

Explicit prohibitions:

- No revenue recognition at completion directly.
- No COGS recognition at completion directly.
- No revenue recognition at pending event creation directly.
- No COGS recognition at pending event creation directly.
- No profit / gross margin calculation in Accounting Event payload.

Revenue / COGS implementation must wait until:

- account mapping exists
- vehicle cost capitalization logic is reliable
- journal draft generation rules are reviewed
- accountant can inspect before posting

## 10. Vehicle Sale Completion Event Mapping

Current Phase 3 runtime mapping:

```txt
source_type = vehicle_sale_completion
event_type = vehicle_sale_completed
status = pending
amount = sale.sale_price
currency = TWD
source_id = vehicle_sales.id
source_number = vehicle.stock_number fallback SALE-{sale.id}
payload = backend-controlled safe allowlist
```

Current payload allowlist summary:

- `vehicle_sale_id`
- `vehicle_id`
- `vehicle_stock_number`
- `vehicle_label`
- `customer_id`
- `customer_number`
- `customer_name`
- `sale_status`
- `sold_at`
- `completed_at`
- `completed_by_name`
- `receivable_status`
- `receivable_status_label`
- `receivable_amount`
- `received_amount`
- `receivable_balance`
- `source_number`

Payload must not include:

- `customer_phone`
- `id_number`
- `birthday`
- `address`
- `company_id`
- `branch_id`
- `purchase_cost`
- `cogs_amount`
- `revenue_amount`
- `gross_profit`
- `gross_margin`
- `profit`
- `accounting_event_id`
- `journal_entry_id`

Receivable summary rules:

- `received_amount` / `receivable_status` must continue to use `ReceivableSummaryService`.
- Do not use the `vehicle_sales.paid_amount` snapshot as the received amount source.

## 11. Permissions Direction

Current implemented permissions:

```txt
module.accounting.events.view
module.accounting.events.review
module.accounting.events.void
module.accounting.events.convert
```

Permission boundaries:

- `module.accounting.events.view` is implemented.
- `module.accounting.events.review` is implemented.
- `module.accounting.events.void` is implemented.
- `module.accounting.events.convert` is implemented.
- `module.accounting.events.view` only views readonly workspace.
- `module.accounting.events.convert` controls the convert skeleton route for reviewed events only.
- `module.accounting.events.convert` is required before any future journal draft generation.
- `module.accounting.events.void` is required to void.
- `module.accounting.view` must not be the only entry or operation permission for Accounting Events.
- Review / convert / void should default to admin / accounting only; whether other roles receive these permissions requires a separate decision.

## 12. Audit Requirements

Future audit events:

```txt
accounting_event.reviewed
accounting_event.converted
accounting_event.voided
```

If pending event creation needs a dedicated audit event in the future, the system may consider:

```txt
accounting_event.created
```

Current Phase 3 represents pending event creation through the completion workflow, `vehicle_sale.transaction_completed` audit event, and the `accounting_events` table. It does not currently define a separate `accounting_event.created` audit event.

Phase 4D-1 convert fail-safe does not write audit because there is no state change, no journal draft, and no converted relationship. `accounting_event.converted` remains required only for future successful draft generation.

Audit payload allowlist principle:

- event id
- `source_type`
- `source_id`
- `source_number`
- `event_type`
- `old_status`
- `new_status`
- `review_note` if safe
- `converted_journal_entry_id` if applicable
- `void_reason`

Audit payload must not include:

- customer sensitive fields
- `id_number`
- `birthday`
- `address`
- `customer_phone`
- `profit`
- `gross_profit`
- `gross_margin`
- `cogs_amount` as recognized amount
- `revenue_amount` as recognized amount
- raw tenant internals unless necessary

## 13. UI Direction

Future UI direction must remain state-driven and backend-authorized.

Review UI:

- Show page may add review action.
- Show only when `status = pending` and `can.review`.
- Review form can only submit `review_note`.
- Review form must not submit amount / payload / tenant fields / source fields.

Convert UI:

- Show page may add generate journal draft action.
- Show only when `status = reviewed` and `can.convert`.
- If already converted, display linked journal draft.
- Frontend must not submit journal lines unless a future dedicated mapping review UI exists.

Void UI:

- Show page may add void action.
- Show only when `status` is `pending` / `reviewed` and `can.void`.
- `void_reason` is required.
- Converted event must not show simple void unless future reversal flow is completed.

## 14. Backend Implementation Direction

Suggested future implementation sequence:

```txt
Phase 4A: completed review permission + review route/controller action/request/policy/tests
Phase 4B: completed void permission + void route/controller action/request/policy/tests for pending/reviewed only
Phase 4C: completed account mapping config design spec
Phase 4C-2: completed config-based mapping foundation
Phase 4D-1: completed convert permission / route / request / policy / controller skeleton only
Phase 4D-2-spec: completed journal draft generation design spec only
Phase 4D-2A: future convert preflight service only, no writes
Phase 4D-2A-1: completed runtime mapping decision spec only
Phase 4D-2A-2: DB-backed mapping foundation verification / normalization, no draft generation
Phase 4D-2B: future revenue-side journal draft generation only
Phase 5: COGS / vehicle cost basis / inventory mapping after cost capitalization rules are reliable
```

Phase boundaries:

- Phase 4A completed and did not do convert.
- Phase 4B completed and does not touch converted event reversal.
- Phase 4C completed and must not hard-code accounts.
- Phase 4C-2 completed and remains disabled metadata only.
- Phase 4D-1 completed convert permission / route / request / policy / controller skeleton.
- Phase 4D-1 does not generate journal draft.
- Phase 4D-1 returns 422 when mapping is disabled / missing / source_type mismatch.
- Phase 4D-2-spec completed as docs-only.
- Phase 4D-2A-1 completed and decided formal runtime mapping should be DB-backed.
- Next runtime step should be DB-backed mapping foundation verification / normalization, not direct draft generation.
- Phase 4D-2B should be revenue-side draft generation only.
- Phase 5 is the first place to consider COGS / vehicle cost basis / inventory mapping.
- Detailed draft generation spec is `docs/accounting-event-journal-draft-generation-spec.md`.
- Journal draft generation runtime must not bypass existing journal validator, number service, journal create permission, tenant guard, or convert policy.

## 15. Explicit Non-goals

This document does not do:

- No code changes.
- No runtime behavior.
- No journal draft generation.
- No convert success state transition.
- No `converted_journal_entry_id` write.
- No `accounting_journal_entry_lines` generation.
- No automatic journal posting.
- No revenue recognition.
- No COGS recognition.
- No profit / gross margin payload.
- No AR / AP module.
- No Cash / Bank module.
- No Invoice module.
- No Reports.
- No PDF / Excel.
- No refund / reversal implementation.
- No full accounting automation.

## 16. Acceptance Criteria

- This spec reflects Phase 4A review workflow completed.
- This spec reflects Phase 4B void workflow completed.
- This spec reflects Phase 4D-1 Convert Skeleton completed.
- This spec adds no new runtime behavior.
- This spec preserves current completion -> pending Accounting Event behavior.
- This spec preserves current readonly workspace behavior.
- This spec defines current convert skeleton behavior and future draft generation boundaries.
- This spec preserves no automatic posting, revenue recognition, COGS recognition, and profit / gross margin payload.
