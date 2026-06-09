# Accounting Event Journal Draft Generation Spec

## Status

- Spec completed only
- Phase 4D-1 Convert Skeleton completed
- Phase 4D-2A Convert Preflight Service completed
- Phase 4D-2A-1 Runtime Mapping Decision Spec completed
- Phase 4D-2A-2 Database-backed Mapping Foundation completed
- Phase 4D-2B Revenue-side Journal Draft Generation completed

## Scope

- Define future reviewed Accounting Event -> Accounting Journal Draft generation rules
- Runtime converts reviewed Accounting Event to draft journal only when config is enabled and DB-backed AR / Sales Revenue mappings exist
- Runtime creates draft journal header, two revenue-side lines, converted status, `converted_journal_entry_id`, and `accounting_event.converted` audit

## Current Repo State

Completed accounting and accounting event phases:

- Accounting Phase 1: Chart of Accounts
- Accounting Phase 2: Manual Journal Draft Foundation
- Accounting Phase 3: Journal Posting / Voiding
- Accounting Event Phase 1: table / model / config / tests
- Accounting Event Phase 2: readonly workspace
- Accounting Event Phase 3: completion integration
- Accounting Event Phase 4A: review workflow
- Accounting Event Phase 4B: void workflow
- Accounting Event Phase 4C: account mapping spec
- Accounting Event Phase 4C-2: config-based mapping foundation
- Accounting Event Phase 4D-1: convert skeleton
- Accounting Event Phase 4D-2A: convert preflight service
- Accounting Event Phase 4D-2A-2: database-backed mapping foundation

Current Phase 4D-2B behavior:

```txt
reviewed Accounting Event
-> user with module.accounting.events.convert
-> scoped tenant query
-> policy convert guard
-> mapping exists / source_type / enabled checks
-> DB-backed AR / Sales Revenue mappings exist
-> create draft journal + two lines
-> accounting event status converted
-> converted_journal_entry_id written
```

Current mapping state:

- `config/accounting_event_mappings.php` exists.
- `vehicle_sale_completed` metadata exists.
- `required_mapping_keys` contains `accounts_receivable_account` and `sales_revenue_account`.
- `enabled = true`.
- Runtime account IDs remain null.
- Journal line templates remain disabled metadata.
- Runtime mapping decision is documented in `docs/accounting-event-runtime-mapping-decision-spec.md`.
- `accounting_event_account_mappings` now provides the DB-backed account source for future runtime.
- 4D-2B draft generation is implemented for revenue-side AR / Sales Revenue lines only.

## Phase 4D-2A Runtime Boundary

- Accounting Event Phase 4D-2A Convert Preflight Service completed。
- Preflight only returns validated preview。
- Runtime now creates a draft journal and two revenue-side lines after successful preflight。
- Runtime now sets status converted。
- Runtime now writes `converted_journal_entry_id`。
- Runtime now writes `accounting_event.converted` audit。
- 4D-2B revenue-side draft generation completed。
- COGS / tax / overpayment / refund / reversal remains backlog。
- Mapping config default is enabled for 4D-2B and no actual runtime account IDs exist in committed config。

## Phase 4D-2A-1 Runtime Mapping Decision

- Accounting Event Runtime Mapping Decision Spec completed：`docs/accounting-event-runtime-mapping-decision-spec.md`。
- Committed `config/accounting_event_mappings.php` remains short-term metadata only for event type / mapping key / line template metadata。
- Formal account IDs must not be hard-coded into committed config。
- Config override must not be treated as production runtime setup。
- The next runtime implementation should be database-backed mapping foundation before 4D-2B revenue-side journal draft generation。
- Database-backed mapping is preferred because this project is SaaS / tenant scoped, company / branch mappings may differ, account IDs are database data, mapping changes need audit trail, and mapping validation must align with AccountingAccount company / branch / active / type rules。

## Phase 4D-2A-2 Database-backed Mapping Foundation

- Added `accounting_event_account_mappings` table。
- Added `AccountingEventAccountMapping` model。
- Added `AccountingEventAccountMappingResolver` service。
- Preflight now uses DB-backed mapping resolver after config metadata enabled check。
- Committed config remains metadata-only and disabled by default。
- No UI / route / permission / seeder changes。
- No journal draft / lines / converted status / `converted_journal_entry_id` / converted audit。
- Phase 4D-2A-3 mapping admin UI remains optional future backlog。
- Phase 5 COGS / vehicle cost basis / tax / overpayment / refund / reversal remains backlog。

## Non-goals

- No automatic posting
- No COGS recognition
- No inventory recognition
- No profit / gross margin payload
- No tax runtime
- No AR / AP module
- No Cash / Bank module
- No Invoice module
- No Reports
- No refund / reversal
- No mapping UI
- No route / permission / migration / model / policy / controller / request / seeder changes
- No Odoo code / CSS / XML / Python copy

## Journal Draft Generation Positioning

Future workflow:

```txt
Business Document
-> Pending Accounting Event
-> Accounting Review
-> Reviewed Accounting Event
-> Convert to Journal Draft
-> Accountant reviews draft
-> Accountant posts journal through existing Journal Posting workflow
```

Positioning decisions:

- Convert creates draft only.
- Convert never posts.
- Converted Accounting Event does not mean posted journal.
- Posted journal still requires existing `module.accounting.journals.post`.
- Completion, review, mapping, and convert are not the final revenue recognition action.
- Posting is the formal accounting entry point.
- Current scope still does not implement revenue / COGS runtime.

## Future Convert Preconditions

Future draft creation must require both permissions:

```txt
module.accounting.events.convert
module.accounting.journals.create
```

Permission decisions:

- `module.accounting.events.convert` means the user can move an Accounting Event into the conversion flow.
- `module.accounting.journals.create` means the user can create an `AccountingJournalEntry`.
- Convert must not rely only on view / review / void / `module.accounting.view`.
- Convert must not use only `module.accounting.events.convert` to bypass journal create authorization.

Other required preconditions:

- Event must be tenant scoped by company / branch.
- Cross tenant must return 404 before authorization detail leak.
- `event.status` must be `reviewed`.
- `event.voided_at` must be null.
- `event.converted_journal_entry_id` must be null.
- Mapping config must exist for `event.event_type`.
- Mapping `source_type` must equal `event.source_type`.
- Mapping `enabled` must be true.
- Required mapping keys must resolve to concrete account IDs before actual generation.
- Mapped accounts must belong to same company.
- Branch scope must be explicit.
- Mapped accounts must be active.
- Generated lines must pass `AccountingJournalValidator`.
- Journal number must be generated by `AccountingJournalNumberService`.
- Everything must run in one DB transaction.

## Future Draft Header Shape

Based on current `AccountingJournalEntry` schema/model, future generated draft header should use:

```txt
company_id = event.company_id
branch_id = event.branch_id
journal_number = AccountingJournalNumberService::generate(company_id, entry_date)
entry_date = event.event_date by default
summary = generated safe summary
status = draft
source_type = accounting_event
source_id = accounting_events.id
created_by = current user id
updated_by = current user id
```

Suggested summary format:

```txt
由會計事件產生：{source_number} / {event_type_label}
```

Domain-specific Chinese summary option:

```txt
車輛交易完成轉傳票：{source_number}
```

Header boundaries:

- `journal_number` must not be provided by frontend or event payload.
- `company_id` / `branch_id` must not be provided by frontend.
- `source_type` / `source_id` must point to `accounting_event`, not directly to `vehicle_sale`, to preserve the accounting event source chain.
- Source document detail should be displayed through Accounting Event payload / source fields.
- Vehicle sale data should not be inserted directly into journal source fields.

## Future Draft Line Shape

Based on current `AccountingJournalEntryLine` schema/model, future generated lines should use:

```txt
journal_entry_id
account_id
credit
memo
sort_order
```

Line field decisions:

- Field is `journal_entry_id`, not `accounting_journal_entry_id`.
- Field is `account_id`, not `accounting_account_id`.
- Field is `memo`, not `description`.
- `debit` / `credit` are decimal 15,2.
- `sort_order` must be stable.

## First Runtime Scope Recommendation

The first actual draft generation runtime should not include COGS.

Recommended split:

```txt
Phase 4D-2A: Convert Preflight Service only, completed
Phase 4D-2A-1: Runtime Mapping Decision Spec, completed
Phase 4D-2A-2: Database-backed Mapping Foundation, future, no UI, no draft generation
Phase 4D-2A-3: Mapping Admin UI, future, optional
Phase 4D-2B: Revenue-side Draft Generation only, completed
Phase 5: COGS / inventory / tax / overpayment / refund decisions
```

Phase 4D-2A: Convert Preflight Service only completed:

- Added a service that resolves mapping / checks accounts / returns preview array.
- Do not create journal.
- Do not create lines.
- Do not change status.
- Do not write `converted_journal_entry_id`.
- Test mapping enabled false / missing / account missing / inactive / wrong tenant / wrong account type.

Phase 4D-2B: Revenue-side Draft Generation only:

- Generate only two lines:

```txt
Debit accounts_receivable_account = event.amount
Credit sales_revenue_account = event.amount
```

- `journal.status = draft`.
- `event.status = converted`.
- `event.converted_journal_entry_id = journal.id`.
- Audit `accounting_event.converted`.
- Do not do COGS.
- Do not do inventory.
- Do not do tax.
- Do not do overpayment special handling.
- Do not do profit / gross margin.

Phase 4D-2C or Phase 5 may consider:

- COGS draft lines
- inventory / capitalized vehicle cost
- overpayment treatment
- tax lines
- refund / reversal

Reasons:

- Current vehicle cost basis / capitalization does not have enough runtime guarantees.
- COGS should not be derived from Accounting Event payload.
- Payload explicitly forbids profit / gross margin / purchase_cost / cogs_amount.
- Implementing revenue + COGS together would over-expand accounting recognition scope.

## Mapping Requirements for First Runtime

Minimum required mapping for future revenue-side first runtime:

- `accounts_receivable_account`
- `sales_revenue_account`

Current config state:

- Both keys are already required metadata.
- `runtime_account_id` is still null.
- Mapping remains `enabled = false`.

Before enabling runtime, the project must decide:

- Implement database-backed mapping foundation.
- Mapping account type validation.
- Branch behavior.

Database-backed mapping shape is defined in `docs/accounting-event-runtime-mapping-decision-spec.md`. First supported event type remains `vehicle_sale_completed`, and first required mapping keys remain `accounts_receivable_account` and `sales_revenue_account`.

Mapping validation direction:

- AR / clearing account intended type: `asset`.
- Sales revenue account intended type: `revenue`.
- Account must belong to same company.
- Account must be active.
- Branch behavior must be explicit.

## Amount Rules

- First runtime amount source should be `event.amount`.
- `event.amount` currently equals `sale.sale_price` for `vehicle_sale_completed`.
- `event.amount` must be positive.
- Zero / negative amount should fail validation.
- Line debit and credit totals must match exactly at 2 decimals.
- No rounding adjustment line in first runtime.
- No tax split in first runtime.
- No overpayment liability line in first runtime.
- No COGS line in first runtime.

## Idempotency / Transaction Rules

Future runtime conversion rules:

- If `event.converted_journal_entry_id` is not null, abort; do not create second draft.
- If `event.status` is not `reviewed`, abort.
- Journal creation, line creation, event status update, `converted_journal_entry_id` write, and audit log must be in the same DB transaction.
- If any line/account/validator step fails, no journal should remain.
- If journal is created but event update fails, transaction rollback should remove the journal and lines.
- Use row lock or transaction pattern if needed to prevent double convert race condition.
- Consider `lockForUpdate()` on `AccountingEvent` during runtime conversion.
- Do not rely on frontend disabled button for idempotency.

## Audit Rules

Future successful conversion should write audit only after successful state change:

- event: `accounting_event.converted`
- description: `Accounting event converted to journal draft`
- module metadata: `accounting_events`
- old_values / new_values: allowlist only

Audit allowlist:

```txt
id
source_type
source_id
source_number
event_type
old_status
new_status
converted_journal_entry_id
journal_number
entry_date
amount
```

Audit must not include:

```txt
customer_phone
id_number
birthday
address
company_id
branch_id
profit
gross_profit
gross_margin
gross_margin_rate
purchase_cost
cogs_amount
revenue_amount as recognition detail
full payload JSON
```

Phase 4D-1 fail-safe convert attempt still should not write audit because there is no state change.

## UI Direction

This spec does not implement UI.

Future UI direction:

- Accounting Event Show may show Generate Journal Draft button when `can.convert = true` and status = `reviewed`.
- If mapping disabled/missing, UI may display reason but backend remains source of truth.
- After successful conversion, show linked journal draft.
- Convert button must not show for pending / voided / converted.
- UI must not submit journal lines directly in first runtime.
- UI must not submit `account_id` / `debit` / `credit` / `amount`.
- Future preview UI can show generated lines before creation, but must use backend-generated preview.

## Error Messages Direction

Keep Phase 4D-1 messages:

- Mapping missing: `找不到會計事件映射設定，無法產生傳票草稿。`
- Source type mismatch: `會計事件映射與來源類型不一致，無法產生傳票草稿。`
- Mapping disabled: `會計事件映射尚未啟用，無法產生傳票草稿。`

Future message direction:

- Missing account mapping: `會計事件映射尚未指定必要科目，無法產生傳票草稿。`
- Inactive / cross-tenant account: `會計事件映射科目無效，無法產生傳票草稿。`
- Unbalanced lines: use `AccountingJournalValidator`.
- Already converted: `此會計事件已產生傳票草稿。`
- Not reviewed: `只有已覆核的會計事件可以產生傳票草稿。`
- Missing journal create permission: `沒有建立會計傳票的權限。`

## Testing Direction

Future Phase 4D-2A tests:

- Preflight requires convert permission.
- Preflight also checks journals.create if service is intended to create draft later.
- Preflight rejects pending / voided / converted.
- Preflight rejects cross tenant.
- Preflight rejects mapping disabled.
- Preflight rejects missing mapping.
- Preflight rejects source_type mismatch.
- Preflight rejects missing required account ids.
- Preflight rejects inactive account.
- Preflight rejects account from other company.
- Preflight rejects wrong account type.
- Preflight returns preview lines but creates no journal.

Future Phase 4D-2B tests:

- Reviewed event can convert to draft when mapping enabled and accounts valid.
- Creates one `AccountingJournalEntry` with `status = draft`.
- Creates two revenue-side lines only.
- Line debit/credit balanced.
- Uses `AccountingJournalNumberService` `JE-YYYYMM-0001`.
- Journal `source_type = accounting_event` and `source_id = event.id`.
- Event status becomes converted.
- Event `converted_journal_entry_id` points to journal.
- Writes `accounting_event.converted` audit allowlist.
- Cannot convert twice.
- No COGS / inventory / tax / profit / gross margin.
- Failed validation rolls back journal and event update.
- Posted journal is not created.
- Journal is still editable/postable through existing journal workflow only.

## Security / Boundary Checklist

- Backend permissions are source of truth.
- Frontend visibility is UX only.
- Tenant scoped query before authorization details leak.
- No raw payload trust.
- No frontend account IDs in convert.
- No tenant fields from request.
- No profit / gross margin in payload.
- No direct posting.
- No bypass of `AccountingJournalValidator`.
- No bypass of `AccountingJournalNumberService`.
- No bypass of `AccountingJournalEntryPolicy` create decision.
- No copy from Odoo.

## Suggested Implementation Sequence

```txt
Phase 4D-2-spec: this documentation only
Phase 4D-2A: Convert preflight service only, no writes, completed
Phase 4D-2A-1: Runtime mapping decision spec, completed
Phase 4D-2A-2: Database-backed mapping foundation, future, no UI, no draft generation
Phase 4D-2A-3: Mapping admin UI, future, optional
Phase 4D-2B: Revenue-side draft generation only, future
Phase 5: COGS / vehicle cost basis / inventory mapping after cost capitalization rules are reliable
Phase 5 later scope: tax / overpayment / refund / reversal
```
