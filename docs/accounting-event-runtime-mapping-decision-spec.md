# Accounting Event Runtime Mapping Decision Spec

## 1. Status / Scope

Status:

- Phase 4D-2A-1 Runtime Mapping Decision Spec completed
- Phase 4D-2A Convert Preflight Service completed
- Accounting Event Phase 4D-2A-2 Database-backed Mapping Foundation verified
- Runtime mapping decision documented
- DB-backed mapping migration / model / policy / request / controller / resolver / UI / routes / permissions exist and are verified for first stable scope

Scope:

- Decide how future Accounting Event -> Journal Draft runtime account mapping should be provided
- Compare config-based mapping vs database-backed mapping
- Recommend next implementation path
- This decision spec did not add runtime code; current repo state now includes verified DB-backed mapping migration / model / policy / request / resolver / controller / UI / routes / permissions
- This checkpoint is not 4D-2B
- Convert route still calls `AccountingEventJournalDraftPreflightService`
- `AccountingEventConvertService` exists but is not wired into `AccountingEventController::convert()`
- No journal draft generation in this task
- Phase 4D-2B remains future and must not be inferred from this verified mapping foundation

## 2. Current Problem

- `AccountingEventJournalDraftPreflightService` already needs `runtime_account_id` to produce a valid preview.
- Current `config/accounting_event_mappings.php` only keeps metadata.
- Config defaults should not hard-code account ids.
- SaaS / multi-company / multi-branch scenarios are not suitable for account ids in config.
- Different companies have different Chart of Accounts.
- The same company may later need branch-level mapping overrides.
- Therefore, before Phase 4D-2B actually creates journal drafts, the runtime mapping source must be decided.

## 3. Option A: Config-based runtime_account_id

Implementation direction:

```txt
config/accounting_event_mappings.php
-> mapping_keys.*.runtime_account_id = actual accounting_accounts.id
-> preflight service reads runtime_account_id
-> convert service creates journal draft
```

Pros:

- Fastest
- No migration required
- No UI required
- Simple tests
- Single-company MVP can run quickly

Cons:

- Not suitable for SaaS / multi-company
- Account id is database-specific and should not be written into config
- Different environments may have different ids
- Hard to manage when each company has different mappings
- Accounting staff cannot adjust mappings inside the system
- Mapping changes require code changes / deployment
- Weak audit trail
- Branch override is difficult
- Easy to turn config from metadata into runtime data and blur boundaries

Conclusion:

- Option A can be used as local test / temporary development override.
- Option A is not suitable as the formal runtime mapping strategy.
- Production mapping should not be stored in config long term.

## 4. Option B: Database-backed runtime mapping

Implementation direction:

```txt
accounting_event_account_mappings table
-> company_id
-> branch_id nullable
-> event_type
-> mapping_key
-> account_id
-> is_active
-> created_by
-> updated_by
-> timestamps
```

Future flow:

```txt
AccountingEventJournalDraftPreflightService
-> read config metadata for allowed event_type / mapping_key / intended account types
-> resolve actual account_id from DB mapping
-> validate account company / branch / active / type
-> build preview
```

Pros:

- Suitable for SaaS / multi-company
- Each company can have its own mapping
- Nullable `branch_id` supports company-level default
- Future branch-level override is possible
- UI management is possible
- Audit log is possible
- Mapping can be adjusted without deployment
- Config remains metadata and DB stores runtime data
- Better fit for long-term ERP direction

Cons:

- Requires migration / model / policy / request / controller / UI
- More tests required
- Fallback / override rules must be defined
- Slower initially than config-based mapping

Conclusion:

- Option B should be the formal direction.
- Build a minimal database-backed mapping foundation first, then a simple mapping management UI.
- Phase 4D-2B revenue-side journal draft generation should wait until DB-backed mapping foundation is completed.

## 5. Final Decision

```txt
正式 runtime mapping 採用 Option B：Database-backed runtime mapping。
config/accounting_event_mappings.php 繼續保留 metadata / allowed mapping keys / intended account types / template directions。
actual runtime account_id 不應寫進 config 預設檔。
```

Decision rationale:

- The project target is a used car ERP / future SaaS.
- Tenant scope already uses company / branch.
- Chart of Accounts is already DB data.
- Account ids are environment-specific and tenant-specific runtime data.
- Accounting staff will need to maintain mapping inside the system in the future.
- Hard-coded config binds deployment and data environment together, which hurts long-term maintenance.
- Database-backed mapping keeps the system auditable, adjustable, and extensible.

## 6. Proposed Table Design

Verified current table direction. Nullable `branch_id` unique behavior remains documented and is guarded by application validation for active duplicates in the first stable scope.

Recommended table:

```txt
accounting_event_account_mappings
```

Columns:

```txt
id
company_id
branch_id nullable
event_type string
mapping_key string
account_id foreign id
is_active boolean default true
created_by nullable foreign id users
updated_by nullable foreign id users
created_at
updated_at
```

Indexes / constraints direction:

```txt
index company_id
index branch_id
index event_type
index mapping_key
index account_id
unique company_id + branch_id + event_type + mapping_key
```

Notes:

- MySQL unique index behavior with nullable `branch_id` must be handled carefully.
- If nullable `branch_id` makes unique behavior unsuitable, future migration can consider generated column `normalized_branch_key`.
- Or first skip branch override and only implement company-level mapping.
- Or use application-level validation to prevent duplicate mappings.
- First version should only implement company-level mapping, meaning `branch_id = null`.
- Branch override should remain later work.

## 7. First Runtime Scope

First stable runtime mapping scope supports:

```txt
event_type = vehicle_sale_completed
mapping_key = accounts_receivable_account
mapping_key = sales_revenue_account
branch_id = null company default, with tested branch mapping fallback support
is_active = true
```

Not supported:

- COGS mapping runtime
- inventory mapping runtime
- tax mapping runtime
- overpayment mapping runtime
- rounding adjustment runtime
- payment_received event
- vehicle_cost_recorded event
- manual_accounting_review event

## 8. Mapping Resolution Rule

Future service resolution order.

Phase 1 company-level only:

```txt
Find active mapping where:
company_id = event.company_id
branch_id is null
event_type = event.event_type
mapping_key = required mapping key
```

Future branch override:

```txt
1. Try branch mapping:
   company_id = event.company_id
   branch_id = event.branch_id
   event_type = event.event_type
   mapping_key = key

2. Fallback company mapping:
   company_id = event.company_id
   branch_id is null
   event_type = event.event_type
   mapping_key = key
```

Branch-specific lookup and fallback to company-level mapping are covered by resolver tests, but UI scope remains limited to the first AR / Sales Revenue keys.

## 9. Account Validation Rule

Future DB-backed mapping resolution must preserve existing preflight validation:

- account exists
- `account.company_id = event.company_id`
- `account.is_active = true`
- first version `account.branch_id` should be null or explicitly allowed
- account type must match config metadata `intended_account_types`
- AR / clearing: `asset`
- Sales Revenue: `revenue`
- account cannot be from another company
- inactive account cannot be used
- wrong type account cannot be used

## 10. Permission Design

Future mapping management permission options:

```txt
module.accounting.mappings.view
module.accounting.mappings.update
```

More precise option:

```txt
module.accounting.event-mappings.view
module.accounting.event-mappings.update
```

Recommended permissions:

```txt
module.accounting.event-mappings.view
module.accounting.event-mappings.update
```

Rationale:

- Mapping is dedicated to Accounting Event, not all accounting mappings.
- Future tax mappings / payment mappings / cost mappings may exist.
- `event-mappings` avoids an overly broad permission name.

Recommended default roles:

- admin: view / update
- accounting: view / update
- viewer: none
- sales: none
- inventory: none

Important boundaries:

- `module.accounting.view` cannot replace mapping permissions.
- `module.accounting.events.convert` cannot replace mapping update.
- `module.accounting.journals.create` cannot replace mapping update.

## 11. Future UI Direction

Future minimal mapping UI.

Route direction:

```txt
/employee-system/accounting/event-mappings
```

Page:

```txt
resources/js/Pages/Accounting/EventMappings/Index.jsx
```

First UI only needs:

- Display event_type: 車輛交易完成
- Display mapping_key: `accounts_receivable_account`
- Display mapping_key: `sales_revenue_account`
- Display label / description / intended_account_types
- Select active AccountingAccount
- Only show same company active accounts
- Save mapping
- Do not show COGS / tax / overpayment runtime fields, or show them as future disabled
- Do not allow manual account_id input
- Do not implement complex workflow

Not included:

- branch override UI
- audit diff UI
- mapping versioning
- mapping approval
- preview endpoint
- journal generation

## 12. Future Implementation Sequence

```txt
Phase 4D-2A-1: Runtime Mapping Decision Spec only
Phase 4D-2A-2: Database-backed Mapping Foundation verified
Phase 4D-2A-3: Minimal Mapping Management UI completed
Phase 4D-2B: Revenue-side Journal Draft Generation only
Phase 5: COGS / vehicle cost basis / inventory mapping
Phase 6: tax / overpayment / refund / reversal
```

## 13. Phase 4D-2A-2 Future Scope

Phase 4D-2A-2 verified / normalized existing DB-backed mapping foundation:

- migration for `accounting_event_account_mappings`
- model `AccountingEventAccountMapping`
- policy
- request validation
- service or resolver: `AccountingEventAccountMappingResolver`
- tests
- UI / routes / permissions

Should not add yet:

- journal draft generation
- journal lines generation
- converted status write
- converted_journal_entry_id write
- COGS
- tax
- overpayment
- refund
- reports

## 14. Acceptance Criteria

- New spec exists
- Clearly compares Option A / Option B
- Clearly decides to use DB-backed mapping
- Clearly states config only keeps metadata
- Clearly states production should not hard-code `runtime_account_id`
- Clearly states first runtime only supports `vehicle_sale_completed` AR / Sales Revenue mapping
- Clearly states next implementation sequence
- No runtime code changed
