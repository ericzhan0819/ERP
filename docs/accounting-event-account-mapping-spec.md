# Accounting Event Account Mapping Spec

Status: Spec completed + config-based mapping foundation completed + Phase 4D-1 Convert Skeleton completed + Phase 4D-2 Journal Draft Generation Spec completed + Phase 4D-2A Convert Preflight Service completed + Phase 4D-2A-1 Runtime Mapping Decision Spec completed + Accounting Event Phase 4D-2A-2 Database-backed Mapping Foundation verified.
Scope: config metadata, verified DB-backed mapping migration / model / policy / requests / resolver / controller / UI / routes / permissions; `AccountingEventJournalDraftPreflightService` preview exists; detailed draft generation runtime boundary is documented in `docs/accounting-event-journal-draft-generation-spec.md`.
This document does not accept 4D-2B journal draft creation, journal lines creation, posting, revenue recognition, COGS recognition, tax handling, refund / reversal, or successful runtime conversion as active route behavior.

## 1. Purpose

This document is the Accounting Event Phase 4C design specification. It defines the future account mapping boundary for:

```txt
Reviewed Accounting Event
-> Account Mapping Resolution
-> Manual Journal Draft Generation
-> Accountant reviews Journal Draft
-> Accountant posts Journal
```

Core purpose:

- Avoid hard-coded account IDs.
- Avoid hard-coded debit / credit mappings.
- Avoid automatic posting.
- Avoid hidden revenue / COGS recognition.
- Keep accounting staff in the review loop.
- Make future journal draft generation configurable and tenant-scoped.

Account Mapping is the required configuration layer before a reviewed Accounting Event can be converted into a Journal Draft.

Boundaries:

- Account Mapping is not an official journal entry.
- Account Mapping does not mean revenue / COGS has been recognized.
- Account Mapping must not directly create a posted journal.
- Config-based mapping foundation now exists as disabled metadata only.
- This document defines runtime draft generation direction. Phase 4D-1 convert skeleton exists but mapping remains disabled and does not produce a draft.
- Detailed draft generation runtime boundary is defined in `docs/accounting-event-journal-draft-generation-spec.md`.
- Runtime mapping decision is defined in `docs/accounting-event-runtime-mapping-decision-spec.md`.
- Phase 4D-2A-2 verified `accounting_event_account_mappings`, `AccountingEventAccountMapping`, policy, requests, resolver, controller, UI, routes, permissions, and tests.

## 2. Current Repo State

Accounting Event Phase 1:

- `accounting_events` table
- `AccountingEvent` model
- `config/accounting_events.php`
- `AccountingEventTest`

Accounting Event Phase 2:

- `accounting-events` module
- `module.accounting.events.view`
- readonly index/show routes
- `AccountingEventController` index/show
- `AccountingEventPolicy` viewAny/view
- React readonly Index / Show
- `AccountingEventWorkspaceTest`

Accounting Event Phase 3:

- `AccountingEventService`
- successful completion creates one pending Accounting Event
- `source_type = vehicle_sale_completion`
- `event_type = vehicle_sale_completed`
- `status = pending`
- `AccountingEventCompletionIntegrationTest`

Accounting Event Phase 4A:

- `reviewed_at` field
- `module.accounting.events.review`
- review route / request / policy / controller / UI / tests
- `pending -> reviewed`

Accounting Event Phase 4B:

- `module.accounting.events.void`
- void route / request / policy / controller / UI / tests
- `pending / reviewed -> voided`

Accounting Event Phase 4C-2:

- `config/accounting_event_mappings.php` added
- `tests/Feature/AccountingEventMappingConfigTest.php` added
- `vehicle_sale_completed` metadata exists
- required / optional keys defined
- templates disabled
- no account IDs / account codes
- no mapping management route / permission / UI
- no runtime journal generation

Accounting Event Phase 4D-1:

- `module.accounting.events.convert` implemented
- convert route / request / policy / controller skeleton implemented
- Show payload includes `can.convert`
- convert checks reviewed / not voided / not converted / tenant / permission / mapping exists / source_type match / enabled
- mapping disabled fail-safe returns 422 because `vehicle_sale_completed.enabled = false`

Accounting Event Phase 4D-2-spec:

- `docs/accounting-event-journal-draft-generation-spec.md` exists
- future draft header / line / permission / transaction / audit / testing boundaries are documented
- runtime mapping remains disabled
- actual journal draft generation remains not implemented

Accounting Event Phase 4D-2A:

- `AccountingEventJournalDraftPreflightService` exists
- preflight validates permissions / mapping / accounts / amount / preview / `AccountingJournalValidator`
- preflight only returns validated backend-generated preview
- runtime still does not create journal draft
- runtime still does not create journal lines
- runtime still does not set status converted
- runtime still does not write `converted_journal_entry_id`
- runtime still does not write `accounting_event.converted` audit
- mapping config default remains disabled and no actual runtime account IDs in committed config
- Phase 4D-2A-2 Database-backed Mapping Foundation is verified before any direct 4D-2B work
- COGS / tax / overpayment / refund / reversal remains backlog
- Convert route still calls `AccountingEventJournalDraftPreflightService` only；`AccountingEventConvertService` exists but is not wired into `AccountingEventController::convert()`。

Currently not completed:

- Accounting Event -> Journal Draft still not completed
- Journal Draft generation from Accounting Event
- Journal Entry Lines generation from Accounting Event
- successful conversion to `converted`
- `converted_journal_entry_id` write
- Revenue Recognition
- COGS Recognition
- Profit / Gross Margin payload
- Tax handling
- Refund / reversal
- AR / AP
- Cash / Bank
- Invoice
- Reports
- Mapping admin UI exists and remains limited to AR / Sales Revenue mapping management; COGS / tax / overpayment keys remain future-disabled.

## 3. Design Principles

- Business document is the source.
- Accounting Event is the candidate accounting layer.
- Review is human-controlled.
- Account mapping must be explicit and configurable.
- Convert must be human-controlled.
- Journal Draft must remain draft after generation.
- Posting must remain manual.
- No automatic posting.
- No automatic revenue recognition without reviewed mapping.
- No automatic COGS recognition without reviewed mapping.
- No profit / gross margin payload.
- No hard-coded account IDs.
- No guessed debit / credit mapping.
- Tenant scope must remain `company_id` / `branch_id` based.
- Frontend visibility is UX only; backend remains source of truth.
- Future mapping changes must be auditable.

## 4. Why Account Mapping Is Required Before Convert

The system must not directly implement `reviewed -> journal draft` without a mapping layer because future journal lines may need to know:

- revenue account
- accounts receivable account
- cash / bank / payment clearing account
- vehicle inventory / asset account
- COGS account
- tax payable account if tax handling exists
- overpayment / liability account
- rounding / adjustment account
- refund / reversal accounts

The current system has not fully defined these mappings. Therefore future implementation must not hard-code:

```txt
Debit AR / Cash
Credit Sales Revenue
Debit COGS
Credit Inventory
```

These journal directions may be reasonable in some accounting contexts, but they must not be written into runtime behavior before explicit configuration and accounting review exist.

Convert workflow must wait for account mapping config and accountant review. Phase 4C only defines the mapping specification and does not generate any journal.

## 5. Mapping Scope

Future Account Mapping Config may eventually handle:

```txt
vehicle_sale_completed
vehicle_cost_recorded
payment_received
manual_accounting_review
```

Recommended first scope:

```txt
vehicle_sale_completed
```

This scope now exists as disabled config metadata only. It is not enabled for runtime conversion.

Explicit exclusions for the first mapping phase:

- `payment_received` should not be included in convert yet.
- `vehicle_cost_recorded` should not be included in convert yet.
- `manual_accounting_review` should not be included in convert yet.
- Do not implement AR / AP / Cash / Bank modules in this stage.

## 6. Future Mapping Configuration Shape

Option A config-based mapping foundation has been implemented as metadata only:

```txt
config/accounting_event_mappings.php
```

Or database-backed settings such as:

```txt
accounting_event_mappings table
```

### Option A: config-based mapping

Pros:

- Fast.
- Suitable for MVP.
- Easy to test.
- Does not require UI.

Cons:

- Accounting staff cannot adjust mappings inside the system.
- Less flexible for future multi-tenant SaaS.
- Config changes require deployment.

### Option B: database-backed mapping

Pros:

- Tenant / company can configure mappings.
- Future UI can manage mappings.
- Better for SaaS.
- Can support audit trail.

Cons:

- Requires migration / model / policy / UI.
- Higher complexity.
- Requires more complete validation.

Current recommendation:

```txt
Formal decision: DB-backed runtime mapping.
Config-based mapping remains metadata / local testing only.
Next runtime step: database-backed mapping foundation before revenue-side draft generation.
```

It remains disabled for runtime conversion and does not contain account IDs. Database-backed mapping is now the formal prerequisite before 4D-2B draft generation. See `docs/accounting-event-runtime-mapping-decision-spec.md`.

## 7. Suggested Mapping Keys

Current mapping metadata for `vehicle_sale_completed` includes:

```txt
vehicle_sale_completed.accounts_receivable_account
vehicle_sale_completed.sales_revenue_account
vehicle_sale_completed.vehicle_inventory_account
vehicle_sale_completed.cogs_account
vehicle_sale_completed.tax_payable_account
vehicle_sale_completed.overpayment_account
vehicle_sale_completed.rounding_adjustment_account
```

Boundaries:

- `vehicle_sale_completed.accounts_receivable_account` exists as metadata.
- `vehicle_sale_completed.sales_revenue_account` exists as metadata.
- Optional keys exist as metadata.
- None contain runtime account IDs.
- Do not specify actual `account_id` values in this document.
- Do not assume the chart of accounts has fixed codes.
- Do not hard-code any mapping into PHP code.
- Before implementation, mapped accounts must be verified as same company / allowed branch scope and active.

## 8. Vehicle Sale Completed Mapping Direction

Current implemented Accounting Event characteristics:

```txt
source_type = vehicle_sale_completion
event_type = vehicle_sale_completed
status = reviewed
amount = sale.sale_price
payload includes receivable summary
```

Future journal draft direction may contain two candidate blocks.

Revenue side:

```txt
Debit accounts receivable / clearing account
Credit sales revenue account
```

COGS side:

```txt
Debit COGS account
Credit vehicle inventory / capitalized cost account
```

Boundaries:

- Revenue side generation must wait for account mapping and accountant review.
- COGS side generation must wait for reliable vehicle cost capitalization data.
- COGS amount must not be derived from Accounting Event payload profit / margin because payload must not include profit / margin.
- COGS amount should come from future confirmed vehicle cost basis / capitalization logic.
- This document does not define concrete amount calculation.
- This document does not define concrete `account_id` values.
- This document does not generate a journal draft.

## 9. Revenue Recognition Boundary

Revenue recognition boundary:

```txt
Complete Transaction / Confirm Delivery is the candidate point.
Pending Accounting Event is candidate data.
Review confirms the candidate event.
Account Mapping defines possible accounts.
Convert may generate draft in future.
Posting remains manual.
```

Explicit prohibitions:

- No revenue recognition at completion directly.
- No revenue recognition at pending event creation directly.
- No revenue recognition at review directly.
- No revenue recognition at mapping config creation directly.
- No posted journal without accountant post action.

Future revenue recognition implementation must require:

- account mapping exists
- reviewed accounting event exists
- journal draft can be inspected
- journal lines pass `AccountingJournalValidator`
- accountant manually posts journal

## 10. COGS Recognition Boundary

COGS recognition is a future candidate at completion / reviewed event stage. COGS must not be generated before vehicle cost basis is reliable.

Based on `docs/vehicle-cost-accounting-treatment-spec.md`, current cost classification direction is:

```txt
purchase_price / repair / detailing / inspection -> likely capitalized vehicle cost
transport / tax / other -> usually capitalized or manually classified, requires review
management -> period expense
```

Important COGS boundaries:

- COGS draft mapping requires reliable vehicle cost capitalization logic.
- Period expenses should not be forced into vehicle inventory / COGS.
- Manual review categories should not be auto-included.
- This document does not implement COGS calculation.
- This document does not add profit / gross margin.

## 11. Payment / Receivable Boundary

- `ReceivableSummaryService` is the current source for receivable summary.
- Accounting Event payload `received_amount` / `receivable_status` should continue to use `ReceivableSummaryService`.
- Do not use the `vehicle_sales.paid_amount` snapshot as the received amount source.
- Payment received event may become a future Accounting Event source, but this spec does not handle it.
- Do not smuggle Cash / Bank modules into this stage.
- Do not hard-code cash, bank, or payment method account mappings in this spec.

## 12. Overpayment Handling Boundary

The `overpaid` receivable status needs explicit future accounting treatment, such as:

```txt
overpayment liability
customer deposit / advance receipt
manual adjustment
refund / reversal
```

Boundaries:

- This document does not decide final overpaid accounting treatment.
- Do not hard-code an overpayment account.
- Do not generate overpayment journal lines.
- Do not implement refund.
- Do not implement reversal.
- Convert must wait for explicit overpayment mapping / policy.

## 13. Tax Handling Boundary

- Tax handling is not implemented.
- Do not hard-code tax payable account.
- Do not hard-code business tax, output tax, input tax, or any tax rate.
- Do not generate tax journal lines without company tax configuration.
- Future tax handling requires separate tax configuration / invoice / tax report design.

This document provides no concrete tax law advice or tax numbers.

## 14. Future Convert Preconditions

Before future `reviewed Accounting Event -> Journal Draft`, implementation must check:

- event status must be reviewed
- event must not be voided
- event must not already have `converted_journal_entry_id`
- event tenant must match user tenant
- user must have `module.accounting.events.convert`
- user may also need `module.accounting.journals.create`
- mapping config must exist for `event_type`
- mapping source_type must match event source_type
- mapping enabled check must pass
- mapped accounts must exist
- mapped accounts must belong to same company / allowed branch scope
- mapped accounts must be active
- journal lines must balance
- journal lines must pass `AccountingJournalValidator`
- no posted journal should be created directly

Convert idempotency is required. If `converted_journal_entry_id` already exists, the system must not create a second draft.

Convert failure must not change event status. Journal draft creation and event status update should happen in the same DB transaction.

Phase 4D-2A now implements preflight validation for reviewed, not voided, no `converted_journal_entry_id`, tenant, convert permission, journal create permission, mapping exists, source_type match, enabled, required runtime accounts, account company / branch / active / type, and balanced preview lines. It returns preview only and still does not generate a draft.

## 15. Future Journal Draft Shape

Based on the current `AccountingJournalEntry` / `AccountingJournalEntryLine` models, a future generated draft may use:

`AccountingJournalEntry`:

- `company_id`
- `branch_id`
- `journal_number`
- `entry_date`
- `summary`
- `status = draft`
- `source_type`
- `source_id`
- `created_by`
- `updated_by`

Current `AccountingJournalEntry` schema/model appears to provide generic `source_type` / `source_id` fields.

Future draft source direction:

```txt
source_type = accounting_event
source_id = accounting_events.id
```

`AccountingJournalEntryLine`:

- `journal_entry_id`
- `account_id`
- `memo`
- `debit`
- `credit`
- `sort_order`

Current line schema uses `journal_entry_id`, `account_id`, and `memo`, not `accounting_journal_entry_id`, `accounting_account_id`, or `description`.

## 16. Mapping Validation Rules

Future mapping validation principles:

- Mapping account must exist.
- Mapping account must belong to current company.
- If branch scoped, branch rules must be explicit.
- Mapping account must be active.
- Mapping account type should match intended use.
- Revenue account should not be asset account unless explicitly allowed.
- AR / clearing account should not be revenue account.
- COGS account should not be liability account.
- Inventory / capitalized vehicle account should not be revenue account.
- Debit / credit totals must balance.
- Zero amount lines should not be generated.
- Negative line handling must be explicitly designed, not guessed.

Account type compatibility rules are future validation rules.

This document does not modify `AccountingJournalValidator` and does not add a mapping validator.

## 17. Tenant Scope

Mapping tenant scope direction:

```txt
Mapping must be company scoped.
Branch-specific mapping may be allowed later.
Company-level mapping can apply to all branches if branch_id is null.
Branch-level override can apply to one branch.
Cross-tenant account references must be rejected.
Frontend-submitted account IDs must not be trusted without backend validation.
```

If future implementation uses database-backed mapping, table direction may include:

- `company_id`
- `branch_id` nullable
- `event_type`
- `source_type`
- `mapping_key`
- `account_id`
- `is_active`
- `notes` nullable
- `created_by`
- `updated_by`
- timestamps

Suggested unique key is `company_id + branch_id + event_type + mapping_key`. `branch_id = null` means company default, and branch-specific mapping may override company default later.

Future resolver should first try exact branch mapping, then fallback to company-level mapping where `branch_id` is null. It must reject cross-company accounts, inactive mappings, inactive accounts, and wrong account types.

This is future direction only and does not add a migration.

## 18. Permissions Direction

Current implemented permissions:

```txt
module.accounting.events.view
module.accounting.events.review
module.accounting.events.void
module.accounting.events.convert
```

Future possible permissions:

```txt
module.accounting.mappings.view
module.accounting.mappings.update
```

Permission boundaries:

- `module.accounting.events.convert` is implemented.
- `module.accounting.mappings.view` is not implemented.
- `module.accounting.mappings.update` is not implemented.
- `module.accounting.events.review` must not automatically allow convert.
- `module.accounting.events.void` must not automatically allow convert.
- Whether `module.accounting.journals.create` is also required for convert must be explicitly decided in future implementation.
- `module.accounting.view` must not be the only permission for mapping or convert.

## 19. Audit Requirements

Future audit event direction:

```txt
accounting_mapping.created
accounting_mapping.updated
accounting_event.converted
```

If future convert creates a journal draft, audit payload allowlist may include:

- `event_id`
- `source_type`
- `source_id`
- `source_number`
- `event_type`
- `old_status`
- `new_status`
- `converted_journal_entry_id`
- `journal_number`
- `mapping_version` or `mapping_source` if available

Audit payload must not include:

- customer sensitive fields
- `customer_phone`
- `id_number`
- `birthday`
- `address`
- `profit`
- `gross_profit`
- `gross_margin`
- raw tenant internals unless necessary
- unreviewed `cogs_amount`
- unreviewed `revenue_amount`
- full payload JSON

## 20. UI Direction

Mapping UI future direction:

- Accounting settings may add Accounting Event Mapping Settings.
- Only accounting / admin class roles should manage mappings.
- UI should show `event_type`, mapping key, account selection, and active/inactive state.
- Normal users must not directly type `account_id`.
- Select list should show only same tenant active accounts.
- UI must clearly state this is draft generation mapping, not direct posting.

Convert UI future direction:

- Accounting Event Show page may display Generate Journal Draft when `status = reviewed` and `can.convert`.
- If mapping is missing, UI should show missing mapping keys.
- If already converted, UI should show linked journal draft.
- Frontend must not submit full journal lines unless a future mapping preview / review UI exists.
- Convert button must not show for pending / voided / converted events.

## 21. Backend Implementation Direction

Suggested future small-step sequence:

```txt
Phase 4C: Account mapping config design spec. Current task.
Phase 4C-1: Decide config-based vs database-backed mapping. Completed as config-based first.
Phase 4C-2: Config-based mapping foundation completed.
Phase 4C-3: If database-backed, add mapping table/model/policy/settings UI later.
Phase 4D-1: Convert permission / route / request / policy / controller skeleton completed, no journal generation.
Phase 4D-2-spec: Journal draft generation design spec completed, docs-only.
Phase 4D-2A: Future convert preflight service only, no writes.
Phase 4D-2A-1: Runtime mapping decision spec completed.
Phase 4D-2A-2: Future database-backed mapping foundation, no UI, no draft generation.
Phase 4D-2A-3: Future mapping admin UI, optional.
Phase 4D-2B: Future revenue-side draft generation only.
Phase 4D-2C: Future preview UI / backend preview endpoint if needed.
Phase 5: COGS / vehicle cost basis / inventory mapping after cost capitalization rules are reliable.
Phase 6: Reversal / refund / return flow.
```

Phase 4C completed.

Phase 4C-2 completed.

Phase 4D-1 completed.

Phase 4D-2-spec completed and documents that first runtime should split into preflight-only and revenue-side draft generation only.

Next runtime step should be Phase 4D-2A preflight service only.

Phase 4D-2A is completed. Next runtime step should be Phase 4D-2A-2 database-backed mapping foundation verification / normalization, no draft generation.

Convert must remain preflight-only while DB-backed mapping foundation is verified / normalized.

Actual draft generation must wait for mapping activation / validation decision.

Detailed draft generation runtime boundary is defined in `docs/accounting-event-journal-draft-generation-spec.md`.

Actual runtime mapping must still respect mapping activation, same-company active accounts, intended account types, explicit branch behavior, and no fixed committed account IDs.

Runtime mapping decision is defined in `docs/accounting-event-runtime-mapping-decision-spec.md`.

## 22. Explicit Non-goals

This document does not do:

- No acceptance of React page / database-backed mapping / mapping UI as stable without Phase 4D-2A-2 verification / normalization.
- No successful draft generation.
- No journal draft generation.
- No journal line generation.
- No converted status transition.
- No `converted_journal_entry_id` write.
- No automatic journal posting.
- No revenue recognition.
- No COGS recognition.
- No profit / gross margin payload.
- No tax engine.
- No AR / AP module.
- No Cash / Bank module.
- No Invoice module.
- No Reports.
- No PDF / Excel.
- No refund / reversal implementation.
- No full accounting automation.

## 23. Acceptance Criteria

- This spec reflects config-based mapping foundation completed.
- This spec reflects Phase 4D-1 Convert Skeleton completed.
- This spec adds no runtime behavior.
- This spec does not change database schema.
- Convert route / permission skeleton exists; mapping management routes / permissions are verified for Phase 4D-2A-2 first scope.
- This spec does not change React pages.
- Mapping config remains disabled.
- Mapping config contains no actual account IDs or fixed account codes.
- This spec preserves current completion -> pending Accounting Event behavior.
- This spec preserves current review and void workflows.
- This spec defines future account mapping boundaries before convert.
- This spec still prevents hard-coded debit / credit runtime behavior.
- This spec still prevents automatic posting, revenue recognition, COGS recognition, and profit / gross margin payload.
