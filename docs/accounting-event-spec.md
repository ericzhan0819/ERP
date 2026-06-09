# Accounting Event Foundation Spec

> Status: Spec completed + Phase 1 foundation completed + Phase 2 readonly workspace completed + Phase 3 completion integration completed + Phase 4A review workflow completed + Phase 4B void workflow completed + Phase 4C account mapping spec completed + Phase 4C-2 config-based mapping foundation completed + Phase 4D-1 Convert Skeleton completed + Phase 4D-2 Journal Draft Generation Spec completed.
> Scope: define Accounting Event product semantics, current foundation state, future data direction, status flow, source documents, tenant / permission / audit principles, and future Journal Draft / Revenue / COGS integration direction.
> Phase 1 has implemented the minimal table, model, config, and tests. Phase 2 has implemented a readonly index/show workspace. Phase 3 has implemented successful completion -> one pending Accounting Event. Phase 4A has implemented pending -> reviewed. Phase 4B has implemented pending / reviewed -> voided. Phase 4C completed the account mapping design spec. Phase 4C-2 implemented config-based mapping foundation metadata. Phase 4D-1 implemented convert skeleton. Phase 4D-2 completed journal draft generation spec only. It does not implement create workflows, successful journal draft conversion, journal draft generation runtime, or accounting recognition runtime behavior.

## 1. Purpose

Accounting Event is the intermediary layer between business documents and accounting journal entries.

Core positioning:

```txt
Business Document
-> Accounting Event
-> Journal Draft
-> Posted Journal
```

Accounting Event is not an official journal entry. It is also not a report.

Its purpose is to:

- Capture accounting candidate data from business events.
- Let accountants review candidate accounting events in a future workflow.
- Let the system generate journal drafts from reviewed events in a future phase.
- Prevent business flows from directly auto-posting journals.
- Prevent completion actions from directly recognizing revenue or COGS.

## 2. Current Repo State

The current completed business flow is:

```txt
Customer
-> Vehicle Sale
-> Receivables / Payments
-> Mark Sold
-> Complete Transaction / Confirm Delivery
-> Customer Transaction History
-> Audit Logs
```

The current completed accounting foundation includes:

```txt
Accounting Accounts
Accounting Journal Draft
Accounting Journal Post
Accounting Journal Void
```

Current accounting module boundaries are already split:

- `accounting-accounts`: Chart of Accounts module.
- `accounting-journals`: Accounting Journal module.
- `module.accounting.view`: compatibility / category concept only. It must not be used as the only permission to enter accounting accounts, journals, or future accounting events.

The current Accounting Event foundation includes:

```txt
accounting_events table
AccountingEvent model
config/accounting_events.php
AccountingEventTest
AccountingEventService
AccountingEventCompletionIntegrationTest
accounting_events.reviewed_at
module.accounting.events.review
review route
ReviewAccountingEventRequest
AccountingEventPolicy::review
AccountingEventController::review
AccountingEventReviewTest
module.accounting.events.void
void route
VoidAccountingEventRequest
AccountingEventPolicy::void
AccountingEventController::void
AccountingEventVoidTest
config/accounting_event_mappings.php
AccountingEventMappingConfigTest
vehicle_sale_completed mapping metadata
module.accounting.events.convert
convert route
ConvertAccountingEventRequest
AccountingEventPolicy::convert
AccountingEventController::convert
AccountingEventConvertTest
docs/accounting-event-journal-draft-generation-spec.md
```

`AccountingEventTest` covers schema, casts, relationships, config, tenant scoped query, and completion regression. The completion regression confirms a successful completion action creates one pending Accounting Event.

The current Accounting Event readonly workspace includes:

```txt
accounting-events module exists
module.accounting.events.view exists
Accounting Event readonly index/show routes exist
AccountingEventController exists with index/show/review/void/convert
AccountingEventPolicy exists with viewAny/view/review/void/convert
React readonly Index / Show pages exist
Accounting Event Show page has review UI for pending events when can.review = true
Accounting Event Show page has void UI for pending / reviewed events when can.void = true
```

`AccountingEventWorkspaceTest` covers route access, tenant scope, filters, payload sanitizer, module registry, staff permission matrix, no mutation routes, and completion regression.

`AccountingEventReviewTest` covers authorized review, view-only denied, `module.accounting.view` denied, cross-tenant 404, only pending, deny-list, audit safe payload, `can.review` props, seeder permission, permission matrix, and no journal draft/lines.

`AccountingEventVoidTest` covers pending/reviewed void, view-only denied, review-only denied, `module.accounting.view` denied, cross-tenant 404, converted denied, already voided denied, void_reason validation, deny-list, audit safe payload, `can.void` props, seeder permission, permission matrix, and no journal draft/lines.

`AccountingEventConvertTest` covers authorized convert skeleton, mapping disabled fail-safe 422, mapping missing 422, source_type mismatch 422, same tenant guard, reviewed-only guard, not voided / not converted guards, view-only / review-only / void-only / `module.accounting.view` denial, forbidden payload 403, `can.convert` props, seeder permission, permission matrix, and no journal draft/lines/status/write.

`AccountingEventService` exists. Successful completion creates one pending Accounting Event. Completion integration is backend-only and does not require `module.accounting.events.view`.

`AccountingEventCompletionIntegrationTest` covers successful completion event creation, safe payload allowlist, ReceivableSummaryService semantics, overpaid status, failure / unauthorized / cross-tenant non-creation, idempotency, no journal draft / lines, and readonly workspace consumption.

The current Accounting Event mapping foundation includes:

- `config/accounting_event_mappings.php` exists.
- `AccountingEventMappingConfigTest` exists.
- `vehicle_sale_completed` mapping metadata exists.
- Mapping is disabled for runtime conversion.
- Journal line templates are disabled metadata only.
- Mapping config has no runtime account IDs and no fixed account codes.
- `docs/accounting-event-journal-draft-generation-spec.md` exists as docs-only Phase 4D-2 specification.

The following are not completed yet:

```txt
Accounting Event successful conversion to journal draft
Journal Draft generation from Accounting Event
Journal Entry Lines generation
converted status transition
converted_journal_entry_id write
Automatic Revenue Recognition
Automatic COGS Recognition
Profit / Gross Margin Payload
AR / AP
Cash / Bank
Invoice
Reports
```

## 3. Design Principles

- Business documents first.
- Accounting entries behind the scenes.
- Manual journals are exception / advanced workflow.
- No automatic posting.
- No automatic revenue recognition in this phase.
- No automatic COGS recognition in this phase.
- No profit / gross margin payload in this phase.
- Tenant scope must always use `company_id` / `branch_id`.
- Frontend visibility is UX only; backend is source of truth.
- Accounting Event must be auditable.
- Accounting Event must be reversible / voidable in future, but this spec does not implement reversal yet.

## 4. Accounting Event Definition

Accounting Event is a candidate accounting event that has not yet been converted into an official accounting journal draft.

Future data model direction may include:

```txt
id
company_id
branch_id
source_type
source_id
source_number
event_type
event_date
status
currency
amount
payload
review_note
created_by
reviewed_by
converted_journal_entry_id
voided_by
voided_at
void_reason
created_at
updated_at
```

Important boundaries:

- Phase 0 spec did not add migration.
- Phase 1 has now added the minimal table / model / config / tests foundation.
- Phase 2 has now added readonly index/show workspace routes, controller, policy, permission, module registry entry, React pages, and focused tests.
- Phase 3 has now added successful completion -> one pending Accounting Event.
- Phase 4A has now added pending Accounting Event -> reviewed workflow.
- Phase 4B has now added pending / reviewed Accounting Event -> voided workflow.
- Phase 4C has now completed Account Mapping Config design spec.
- Phase 4C-2 has now added config-based mapping foundation metadata.
- Phase 4D-1 has now added convert permission / route / request / policy / controller skeleton.
- Phase 4D-2 has now added journal draft generation design spec only.
- Future phases still must not assume journal draft or accounting recognition runtime exists.
- `payload` must not store sensitive personal data.
- `payload` must not store profit / gross margin.
- `payload` must not store unnecessary tenant raw IDs.
- `payload` must be treated as a controlled server-side snapshot, not frontend-provided accounting truth.

## 5. Source Types

Source types:

```txt
vehicle_sale_completion
vehicle_sale_payment
vehicle_cost
manual_adjustment
```

Runtime source type:

```txt
vehicle_sale_completion
```

`vehicle_sale_completion` has runtime event creation logic. Other source types remain future direction.

## 6. Event Types

Event types:

```txt
vehicle_sale_completed
payment_received
vehicle_cost_recorded
manual_accounting_review
```

Runtime event type:

```txt
vehicle_sale_completed
```

`vehicle_sale_completed` has runtime event creation logic. Other event types remain future direction.

## 7. Status Flow

Future Accounting Event statuses:

```txt
pending
reviewed
converted
voided
```

Status semantics:

- `pending`: event has been created and is waiting for accounting review.
- `reviewed`: accountant has confirmed the event can be converted into a journal draft.
- `converted`: journal draft has been generated and the relationship is retained.
- `voided`: event has been voided and must retain a reason.

Implemented flow:

- `pending` -> `reviewed` has been implemented.
- `pending` / `reviewed` -> `voided` has been implemented.
- `reviewed` -> `converted` skeleton route / guard exists, but successful transition is not implemented because mapping disabled fail-safe returns 422.
- `converted` status is still not produced by runtime.
- `converted` event currently cannot be voided.
- `voided` event cannot be voided again.
- `voided` does not delete the original event.
- `voided` does not mean refund / reversal has been processed.
- `reviewed` does not mean journal draft exists.
- `reviewed` does not mean posted.
- Review only marks the event as an accounting-reviewed candidate.

Important boundaries:

- `converted` does not mean journal posted.
- Accounting Event must not directly become a posted journal.
- Journal posting must still go through the existing Accounting Journal post workflow.

## 8. Relationship with Transaction Completion

Current state:

```txt
Complete Transaction / Confirm Delivery currently only records completion state.
Successful completion creates one pending Accounting Event.
It does not create Journal Draft yet.
It does not recognize revenue yet.
It does not recognize COGS yet.
```

The current completion action writes only:

- `vehicle_sales.completed_at`
- `vehicle_sales.completed_by`
- `vehicle_sales.completion_note`
- Audit event `vehicle_sale.transaction_completed`
- Pending Accounting Event with `source_type = vehicle_sale_completion` and `event_type = vehicle_sale_completed`

Future direction:

```txt
Vehicle Sale sold + paid / overpaid + completed
-> candidate for Accounting Event: vehicle_sale_completed
-> accounting review
-> generate Journal Draft
-> accountant posts Journal
```

`mark sold` must not be described as the revenue recognition point.

- `mark sold` is the sale / vehicle status connection point.
- `complete transaction / confirm delivery` is the future candidate point for revenue / COGS recognition.
- This phase creates only a pending candidate event and still must not automatically recognize revenue or COGS.

## 9. Relationship with Journal Draft

Future Accounting Event can be converted into an Accounting Journal Draft, but current Phase 4D-1 only implements convert skeleton and fail-safe guards.

Current review boundaries:

- Review does not generate journal draft.
- Review does not generate `accounting_journal_entry_lines`.
- Review does not post journal.
- Review does not recognize revenue.
- Review does not recognize COGS.
- Review does not add profit / gross margin payload.

Current void boundaries:

- Void does not generate journal draft.
- Void does not generate `accounting_journal_entry_lines`.
- Void does not cancel journal draft.
- Void does not reverse posted journal.
- Void does not post journal.
- Void does not recognize revenue.
- Void does not recognize COGS.
- Void does not add profit / gross margin payload.

Current mapping config boundaries:

- Mapping config does not generate journal draft.
- Mapping config does not generate `accounting_journal_entry_lines`.
- Mapping config does not post journal.
- Mapping config does not recognize revenue.
- Mapping config does not recognize COGS.
- Mapping config does not add profit / gross margin payload.
- Mapping disabled fail-safe exists for the convert skeleton.

Current convert skeleton boundaries:

- Convert route exists.
- Mapping check exists.
- Mapping disabled fail-safe exists.
- No draft is created.
- No journal lines are created.
- No status is changed to `converted`.
- No `converted_journal_entry_id` is written.

Future conversion direction:

```txt
Accounting Event reviewed
-> generate AccountingJournalEntry draft
-> generate accounting_journal_entry_lines
-> accountant reviews draft
-> accountant posts
```

Boundaries:

- Do not automatically post.
- Do not skip the existing journal validator.
- Do not bypass existing `module.accounting.journals.create/update/post` permissions.
- Do not change the existing `AccountingJournalEntryController` in this spec.
- Do not change existing journal statuses: `draft` / `posted` / `voided`.

## 10. Revenue / COGS Recognition Direction

Direction only:

```txt
Revenue recognition candidate:
Complete Transaction / Confirm Delivery

COGS recognition candidate:
Complete Transaction / Confirm Delivery
```

Accounting review must remain in the flow:

```txt
Completion creates candidate event only.
Revenue / COGS journal draft generation should be reviewed before posting.
```

This spec must not:

- Add concrete account IDs.
- Hard-code journal lines.
- Hard-code debit / credit mappings.
- Add profit / gross margin calculation.

## 11. Vehicle Cost Relationship

Based on the current Vehicle Cost Accounting Treatment Spec, future vehicle cost accounting direction is:

```txt
purchase_price / repair / detailing / inspection -> likely capitalized vehicle cost
transport -> usually capitalized but may require review
management -> period expense
```

Future vehicle cost records may become Accounting Event sources, but not in this phase.

Vehicle Cost Management remains a business cost workflow until a future accounting event / journal integration phase explicitly implements the connection.

## 12. Permissions

Current implemented permissions:

```txt
module.accounting.events.view
module.accounting.events.review
module.accounting.events.void
module.accounting.events.convert
```

Future permission direction may include:

```txt
module.accounting.events.create
```

Permission boundaries:

- `module.accounting.events.view` is implemented.
- `module.accounting.events.view` only controls readonly workspace access.
- `module.accounting.events.review` is implemented.
- `module.accounting.events.review` controls pending Accounting Event review only.
- `module.accounting.events.void` is implemented.
- `module.accounting.events.void` controls pending / reviewed Accounting Event void only.
- Completion side effect does not require `module.accounting.events.view`.
- `module.accounting.events.convert` is implemented.
- `module.accounting.events.convert` controls the skeleton route only for reviewed events.
- `module.accounting.view` cannot convert.
- `accounting-events` module is independent from `accounting-accounts` and `accounting-journals`.
- `module.accounting.view` must not be used as the only permission for accounting events or any Accounting Event operation.

## 13. Tenant Scope

Tenant rules:

```txt
Accounting Event must be scoped by company_id.
If branch_id exists, branch boundary must be respected.
Cross-tenant source documents must return 404 before authorization details leak.
Accounting Event payload must not be trusted from frontend.
Source document data must be resolved server-side.
```

The backend must resolve source documents through tenant-scoped queries before creating, reviewing, converting, or voiding accounting events in any future implementation.

## 14. Audit Principles

Future accounting event audit actions should include:

```txt
Accounting event created
Accounting event reviewed
Accounting event converted to journal draft
Accounting event voided
```

Audit payload must not include:

```txt
id_number
birthday
address
customer sensitive fields
profit
gross_margin
raw tenant internals unless necessary
unneeded actor ids
```

Audit payloads should use explicit allowlists and only include fields needed to understand the accounting workflow event.

## 15. Explicit Non-goals

This phase does not do:

```txt
No create route.
No store route.
No journal draft generation.
No successful conversion.
No converted status write.
No converted_journal_entry_id write.
No accounting_journal_entry_lines generation.
No journal draft cancellation.
No posted journal reversal.
No automatic journal posting.
No revenue recognition.
No COGS recognition.
No profit / gross margin payload.
No AR / AP module.
No Cash / Bank module.
No Invoice module.
No Reports.
No PDF / Excel.
No refund / reversal implementation.
No full accounting automation.
```

## 16. Suggested Implementation Phases

Suggested future small-step sequence:

```txt
Phase 0: Spec completed.
Phase 1: AccountingEvent table + model + config + tests completed.
Phase 2: Accounting Event index/show readonly workspace completed.
Phase 3: Completion -> pending Accounting Event completed.
Phase 4A: Pending Accounting Event -> reviewed completed.
Phase 4B: Pending / reviewed Accounting Event -> voided completed.
Phase 4C: Account mapping config design spec completed.
Phase 4C-2: Config-based mapping foundation completed.
Phase 4D-1: Convert permission / route / request / policy / controller skeleton completed, no journal draft generation because mapping remains disabled fail-safe.
Phase 4D-1-docs: Documentation status sync completed by this change.
Phase 4D-2-spec: Journal draft generation design spec completed, docs-only.
Phase 4D-2A: Convert preflight service only, future.
Phase 4D-2B: Revenue-side draft generation only, future.
Phase 5: COGS / vehicle cost basis / inventory mapping after cost capitalization rules are reliable.
Phase 6: Reversal / refund / return flow.
```

Phase 3 directly connects successful completion to one pending Accounting Event without changing journal draft, posting, revenue recognition, or COGS recognition behavior.

## 17. Acceptance Criteria

- This update changes documentation only.
- Accounting Event Foundation Phase 1 exists.
- Accounting Event Phase 2 readonly workspace exists.
- Accounting Event Phase 3 completion integration exists.
- Accounting Event Phase 4A review workflow exists.
- Accounting Event Phase 4B void workflow exists.
- Accounting Event Phase 4C-2 config-based mapping foundation exists.
- Accounting Event Phase 4D-1 Convert Skeleton exists.
- Accounting Event Phase 4D-2 Journal Draft Generation Spec exists.
- Mapping config is metadata only.
- Mapping config contains no actual account IDs or fixed account codes.
- Mapping config is disabled for runtime conversion.
- Successful completion creates one pending Accounting Event.
- Only pending Accounting Events can be reviewed.
- Only pending / reviewed Accounting Events can be voided.
- Converted / already voided Accounting Events cannot be voided.
- Convert skeleton exists; no successful Journal Draft generation exists.
- No journal draft cancellation / posted journal reversal exists.
- No revenue / COGS / profit / gross margin runtime exists.
- Current accounting module boundaries remain unchanged.
- This spec can be used as the next Roo Code prompt reference for Accounting Event Foundation.
