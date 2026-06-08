# Accounting Event Foundation Spec

> Status: Foundation spec only.
> Scope: define Accounting Event product semantics, future data direction, status flow, source documents, tenant / permission / audit principles, and future Journal Draft / Revenue / COGS integration direction.
> This document does not implement migrations, models, controllers, routes, policies, React pages, permissions, or runtime behavior.

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

The following are not completed yet:

```txt
Accounting Event
Completion -> Accounting Event
Accounting Event -> Journal Draft
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

- This is future data model direction only. This task must not add a migration.
- `payload` must not store sensitive personal data.
- `payload` must not store profit / gross margin.
- `payload` must not store unnecessary tenant raw IDs.
- `payload` must be treated as a controlled server-side snapshot, not frontend-provided accounting truth.

## 5. Source Types

Future possible source types:

```txt
vehicle_sale_completion
vehicle_sale_payment
vehicle_cost
manual_adjustment
```

Recommended first candidate source:

```txt
vehicle_sale_completion
```

This document does not implement any source type or event creation logic.

## 6. Event Types

Future possible event types:

```txt
vehicle_sale_completed
payment_received
vehicle_cost_recorded
manual_accounting_review
```

Recommended first candidate event type:

```txt
vehicle_sale_completed
```

This document does not implement any event type.

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

Important boundaries:

- `converted` does not mean journal posted.
- Accounting Event must not directly become a posted journal.
- Journal posting must still go through the existing Accounting Journal post workflow.

## 8. Relationship with Transaction Completion

Current state:

```txt
Complete Transaction / Confirm Delivery currently only records completion state.
It does not create Accounting Event yet.
It does not create Journal Draft yet.
It does not recognize revenue yet.
It does not recognize COGS yet.
```

The current completion action writes only:

- `vehicle_sales.completed_at`
- `vehicle_sales.completed_by`
- `vehicle_sales.completion_note`
- Audit event `vehicle_sale.transaction_completed`

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
- This phase still must not automatically recognize revenue or COGS.

## 9. Relationship with Journal Draft

Future Accounting Event can be converted into an Accounting Journal Draft, but this phase does not implement conversion.

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

Future permission direction may include:

```txt
module.accounting.events.view
module.accounting.events.create
module.accounting.events.review
module.accounting.events.convert
module.accounting.events.void
```

Permission boundaries:

- This is future direction only.
- This document does not modify `RolePermissionSeeder.php`.
- If an `accounting-events` module is added in the future, it should be independent from `accounting-accounts` and `accounting-journals`.
- `module.accounting.view` must not be used as the only permission for accounting events.

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
No migration.
No model.
No controller.
No request.
No policy.
No route.
No React page.
No sidebar module.
No permission seeding.
No automatic event creation from completion.
No automatic journal draft generation.
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
Phase 0: Spec only. Current task.
Phase 1: AccountingEvent table + model + config + tests only.
Phase 2: Accounting Event index/show readonly workspace.
Phase 3: Completion -> pending accounting event, still no journal draft.
Phase 4: Reviewed accounting event -> manual journal draft generation.
Phase 5: Revenue / COGS draft mapping after account configuration exists.
Phase 6: Reversal / refund / return flow.
```

Phase 1 must not directly connect completion to Accounting Event. The first implementation step should establish the minimal Accounting Event foundation and tests without changing transaction completion runtime semantics.

## 17. Acceptance Criteria

- This spec adds no runtime behavior.
- This spec does not change database schema.
- This spec does not change permissions.
- This spec does not change routes.
- This spec preserves current completion semantics.
- This spec preserves current accounting module boundaries.
- This spec can be used as the next Roo Code prompt reference for Accounting Event Foundation.
