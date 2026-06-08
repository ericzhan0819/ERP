# Accounting Event Account Mapping Spec

Status: Spec only.
Scope: define future account mapping configuration boundaries for converting reviewed Accounting Events into manual Journal Drafts.
This document does not implement migrations, models, controllers, requests, policies, permissions, React pages, journal draft generation, journal lines generation, posting, revenue recognition, COGS recognition, tax handling, refund / reversal, or runtime behavior.

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
- This document defines future direction only and does not implement code.

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

Currently not completed:

- Account Mapping Config
- Accounting Event convert
- Accounting Event -> Journal Draft
- Journal Draft generation from Accounting Event
- Journal Entry Lines generation from Accounting Event
- Revenue Recognition
- COGS Recognition
- Profit / Gross Margin payload
- Tax handling
- Refund / reversal
- AR / AP
- Cash / Bank
- Invoice
- Reports

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

This document does not implement that scope.

Explicit exclusions for the first mapping phase:

- `payment_received` should not be included in convert yet.
- `vehicle_cost_recorded` should not be included in convert yet.
- `manual_accounting_review` should not be included in convert yet.
- Do not implement AR / AP / Cash / Bank modules in this stage.

## 6. Future Mapping Configuration Shape

Future mapping may use a config file such as:

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

Recommendation:

```txt
Short-term: config-based mapping spec first, no runtime.
Medium-term: database-backed mapping before SaaS multi-tenant accounting customization.
```

This document does not add a config file or table.

## 7. Suggested Mapping Keys

Future mapping key direction for `vehicle_sale_completed` may include:

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

- These keys are future direction and do not currently exist.
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
- mapped accounts must exist
- mapped accounts must belong to same company / allowed branch scope
- mapped accounts must be active
- journal lines must balance
- journal lines must pass `AccountingJournalValidator`
- no posted journal should be created directly

Convert idempotency is required. If `converted_journal_entry_id` already exists, the system must not create a second draft.

Convert failure must not change event status. Journal draft creation and event status update should happen in the same DB transaction.

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
- `mapping_key`
- `account_id`
- `is_active`
- `created_by`
- `updated_by`
- timestamps

This is future direction only and does not add a migration.

## 18. Permissions Direction

Current implemented permissions:

```txt
module.accounting.events.view
module.accounting.events.review
module.accounting.events.void
```

Future possible permissions:

```txt
module.accounting.events.convert
module.accounting.mappings.view
module.accounting.mappings.update
```

Permission boundaries:

- This document does not add permissions.
- `module.accounting.events.convert` is not implemented.
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
Phase 4C-1: Decide config-based vs database-backed mapping.
Phase 4C-2: If config-based, add config/accounting_event_mappings.php + tests only.
Phase 4C-3: If database-backed, add mapping table/model/policy/settings UI later.
Phase 4D-1: Add convert permission / route / request / policy tests only, no journal generation yet if mapping absent.
Phase 4D-2: Add journal draft generation service using mapping.
Phase 4D-3: Add journal line preview / validation before creation if needed.
Phase 4D-4: Convert reviewed event into draft only, never posted.
Phase 5: Revenue / COGS draft mapping after account configuration exists.
Phase 6: Reversal / refund / return flow.
```

The next code step must not directly hard-code mapping.

The safest next step is config-based mapping foundation with tests only.

Convert should only come after mapping foundation exists.

## 22. Explicit Non-goals

This document does not do:

- No code changes.
- No migration.
- No model change.
- No controller change.
- No request.
- No policy.
- No route.
- No React page.
- No permission seeding.
- No mapping config implementation.
- No mapping table.
- No mapping UI.
- No convert implementation.
- No journal draft generation.
- No `accounting_journal_entry_lines` generation.
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

- This spec adds no runtime behavior.
- This spec does not change database schema.
- This spec does not change routes.
- This spec does not change permissions.
- This spec does not change React pages.
- This spec preserves current completion -> pending Accounting Event behavior.
- This spec preserves current review and void workflows.
- This spec defines future account mapping boundaries before convert.
- This spec explicitly prevents hard-coded account IDs and debit / credit mappings in the next implementation step.
- This spec explicitly prevents automatic posting, revenue recognition, COGS recognition, and profit / gross margin payload.
