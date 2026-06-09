# Accounting Event Journal Draft Generation Spec

Status:

- Spec completed and Phase 4D-2B revenue-side runtime accepted
- Phase 4D-1 Convert Skeleton completed
- Phase 4D-2A Convert Preflight Service completed
- Phase 4D-2A-1 Runtime Mapping Decision Spec completed
- Accounting Event Phase 4D-2A-2 Database-backed Mapping Foundation verified
- Accounting Event Phase 4D-2B Revenue-side Journal Draft Generation completed

Scope:

- Define reviewed Accounting Event -> Accounting Journal Draft generation rules
- `AccountingEventJournalDraftPreflightService` exists and returns backend-generated preview with `header`
- Preflight validates permissions / mapping / accounts / amount / preview lines / `AccountingJournalValidator`
- Convert route calls `AccountingEventConvertService`
- Reviewed `vehicle_sale_completed` with valid DB-backed AR / Sales Revenue mappings creates one draft journal
- Journal has exactly two lines: debit AR and credit Sales Revenue
- Event status becomes `converted` and `converted_journal_entry_id` is written
- `accounting_event.converted` audit is written without payload / sensitive / profit / cost fields
- Journal remains `draft` and is not posted

## Current Repo State

Completed accounting foundations:

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

Current Phase 4D-2B behavior:

```txt
reviewed Accounting Event
-> user with module.accounting.events.convert
-> scoped tenant query
-> policy convert guard
-> AccountingEventConvertService
-> DB transaction + accounting_events lockForUpdate
-> AccountingEventJournalDraftPreflightService
-> checks module.accounting.events.convert and module.accounting.journals.create
-> tenant / status / voided / converted guards
-> mapping exists / source_type / enabled / DB-backed account mapping checks
-> account company / branch / active / intended type checks
-> revenue-side preview lines validated / normalized by AccountingJournalValidator
-> AccountingJournalNumberService generates journal number
-> creates one draft AccountingJournalEntry
-> creates exactly two AccountingJournalEntryLine rows
-> sets accounting_events.status = converted
-> writes converted_journal_entry_id
-> writes accounting_event.converted audit
```

Current boundaries:

- Accounting Event review exists, but review alone does not create journal drafts.
- Accounting Event void exists, but void does not cancel journals or reverse posted journals.
- Convert route exists and creates a revenue-side draft journal only after review and DB-backed mapping validation.
- Config `enabled = true` is only an event-type activation gate.
- Config is not runtime account-id source; `runtime_account_id` remains null.
- Actual runtime accounts come from DB-backed `accounting_event_account_mappings`.
- Existing Accounting Journal creation, validation, numbering, posting, and voiding workflows remain the only supported journal runtime paths.

## Non-goals

This spec does not implement or approve:

- No automatic posting
- No COGS recognition
- No inventory recognition
- No profit / gross margin payload
- No tax runtime
- No overpayment runtime
- No AR / AP module
- No Cash / Bank module
- No Invoice module
- No Reports
- No refund / reversal
- No journal preview endpoint
- No UI redesign
- No Odoo code / CSS / XML / Python copy

## Journal Draft Generation Positioning

Future flow:

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
- Completion / review / mapping / convert are not the final revenue recognition action; posting is the formal accounting entry point.
- Current scope still does not implement revenue / COGS runtime.

## Future Convert Preconditions

Future runtime that actually creates a draft must require both permissions:

```txt
module.accounting.events.convert
module.accounting.journals.create
```

Permission rationale:

- `module.accounting.events.convert` means the user may move an Accounting Event into the journal conversion flow.
- `module.accounting.journals.create` means the user may create an `AccountingJournalEntry`.
- Convert must not rely only on view / review / void / `module.accounting.view`.
- Convert must not rely only on `module.accounting.events.convert` to bypass journal create permission.

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

Based on the current `AccountingJournalEntry` schema/model, future generated draft header should use:

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

Alternative Chinese domain format:

```txt
車輛交易完成轉傳票：{source_number}
```

Required header decisions:

- `journal_number` cannot be provided by frontend or event payload.
- `company_id` / `branch_id` cannot be provided by frontend.
- `source_type` / `source_id` must point to `accounting_event`, not directly to `vehicle_sale`, to preserve the source chain.
- Source document detail should be displayed through Accounting Event payload / source fields, not by embedding vehicle sale directly as journal source.

## Future Draft Line Shape

Based on the current `AccountingJournalEntryLine` schema/model, future generated lines should use:

```txt
journal_entry_id
account_id
debit
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

The first real draft generation runtime should not do COGS in the same step.

Recommended split:

Phase 4D-2A: Convert Preflight Service only

- Add a service that resolves mapping / checks accounts / produces preview array.
- Do not create journal.
- Do not create lines.
- Do not change status.
- Do not write `converted_journal_entry_id`.
- Test mapping enabled false / missing / account missing / inactive / wrong tenant / wrong account type.

Phase 4D-2B: Revenue-side Draft Generation only

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

Rationale:

- Current vehicle cost basis / capitalization does not have enough runtime guarantees.
- COGS should not be derived from Accounting Event payload.
- Payload explicitly forbids profit / gross margin / purchase_cost / cogs_amount.
- Doing revenue + COGS at once would over-expand accounting recognition scope.

## Mapping Requirements for First Runtime

Minimum required mapping for future revenue-side first runtime:

- `accounts_receivable_account`
- `sales_revenue_account`

These keys exist as required metadata in config, `runtime_account_id` remains null, and actual account IDs come from verified DB-backed mappings.

Runtime mapping source decision:

- Formal runtime mapping source should be DB-backed.
- Config `runtime_account_id` is not the production strategy.
- `config/accounting_event_mappings.php` should keep metadata / allowed mapping keys / intended account types / template directions.
- Actual runtime account IDs should not be committed in config defaults.
- See `docs/accounting-event-runtime-mapping-decision-spec.md`.

Mapping account type validation:

- AR / clearing account intended type: `asset`.
- Sales revenue account intended type: `revenue`.
- Account must be same company.
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

Future runtime must enforce:

- If `event.converted_journal_entry_id` is not null, abort; do not create second draft.
- If `event.status` is not `reviewed`, abort.
- Journal creation, line creation, event status update, `converted_journal_entry_id` write, and audit log must be in the same DB transaction.
- If any line/account/validator step fails, no journal should remain.
- If journal is created but event update fails, transaction rollback should remove the journal and lines.
- Use row lock or transaction pattern if needed to prevent double convert race condition.
- Consider `lockForUpdate()` on `AccountingEvent` during runtime conversion.
- Do not rely on frontend disabled button for idempotency.

## Audit Rules

Only future successful conversion writes audit:

- Event: `accounting_event.converted`
- Description: `Accounting event converted to journal draft`
- Module metadata: `accounting_events`
- `old_values` / `new_values` must use allowlist only.

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

Phase 4D-1 fail-safe convert attempt still should not write audit because no state change occurs.

## UI Direction

This spec does not implement UI.

Future UI direction:

- Accounting Event Show may show Generate Journal Draft button when `can.convert = true` and `status = reviewed`.
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
- Unbalanced lines: reuse `AccountingJournalValidator`.
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
- Creates one `AccountingJournalEntry` with `status=draft`.
- Creates two revenue-side lines only.
- Line debit/credit balanced.
- Uses `AccountingJournalNumberService` format `JE-YYYYMM-0001`.
- Journal `source_type=accounting_event` and `source_id=event.id`.
- Event status becomes `converted`.
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
Phase 4D-2A: Convert preflight service only, no writes
Phase 4D-2A-1: Runtime mapping decision spec completed
Phase 4D-2A-2: DB-backed mapping foundation verified
Phase 4D-2B: Revenue-side draft generation only
Phase 4D-2C: optional preview UI / backend preview endpoint
Phase 5: COGS / vehicle cost basis / inventory mapping after cost capitalization rules are reliable
Phase 6: tax / overpayment / refund / reversal
```
