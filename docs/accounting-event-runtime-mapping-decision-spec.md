# Accounting Event Runtime Mapping Decision Spec

## Status

- Accounting Event Runtime Mapping Decision Spec completed.
- Phase 4D-2A Convert Preflight Service completed.
- Phase 4D-2A-2 Database-backed Mapping Foundation completed.
- Accounting Event Phase 4D-2A-3 Minimal Mapping Management UI completed.
- Accounting Event Phase 4D-2A-3 Manual QA Checklist completed.
- Added `docs/accounting-event-mapping-manual-qa-checklist.md`.
- This is docs-only.
- No runtime code changed.
- Phase 4D-2B revenue-side journal draft generation remains backlog.
- Runtime foundation now includes minimal mapping management UI / route / controller / policy / requests / React pages / tests, without journal draft generation.

## Decision

- Committed `config/accounting_event_mappings.php` should stay short-term metadata only.
- Committed config should keep event type, mapping key, account type compatibility, and journal line template metadata.
- Formal production `account_id` values must not be hard-coded into committed config.
- Config override must not be treated as production runtime accounting setup.
- The next runtime implementation should build database-backed mapping foundation before Phase 4D-2B journal draft generation.

## Why Database-backed Mapping

- The project is expected to become SaaS / tenant scoped.
- Different company / branch scopes may require different accounting accounts.
- Account IDs are database data and do not belong in the repository.
- Mapping changes require audit trail because they affect accounting output.
- Mapping validation must stay consistent with `AccountingAccount` company / branch / active / type rules.

## Database-backed Mapping Foundation

Table:

```txt
accounting_event_account_mappings
```

Fields:

- `id`
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

Unique candidate:

```txt
company_id + branch_id + event_type + mapping_key
```

Branch rule:

- `branch_id = null` means company default.
- Branch-specific mapping can override company default later.

Phase 4D-2A-2 added the migration, model, and resolver. Because MySQL allows multiple `NULL` values in unique indexes, branch-null company-default uniqueness is still backed by resolver behavior and should be reinforced by future UI / service validation rather than generated-column tricks in this phase.

## Resolver Priority

Resolution should:

- First try exact branch mapping.
- Then fallback to company-level mapping where `branch_id` is null.
- Reject cross-company account.
- Reject inactive mapping.
- Reject inactive account.
- Reject wrong account type.

## First Supported Runtime Scope

First supported event type remains only:

```txt
vehicle_sale_completed
```

First required mapping keys remain only:

- `accounts_receivable_account`
- `sales_revenue_account`

Optional / future keys remain backlog:

- `vehicle_inventory_account`
- `cogs_account`
- `tax_payable_account`
- `overpayment_account`
- `rounding_adjustment_account`

## Minimal Mapping Management UI

- Phase 4D-2A-3 added the `accounting-event-mappings` module, routes, controller, policy, FormRequests, React pages, seeder permissions, and feature tests.
- The UI only manages DB-backed mapping records for preflight / future draft generation account resolution.
- First scope only supports `vehicle_sale_completed` with required keys `accounts_receivable_account` and `sales_revenue_account`.
- The UI does not enable config runtime, does not write account IDs into config, and does not create journal draft records.

## Manual QA Checklist

- Phase 4D-2A-3 Manual QA Checklist completed.
- Added `docs/accounting-event-mapping-manual-qa-checklist.md`.
- The checklist covers permission / sidebar, mapping index, create, validation, edit, preflight boundary, negative security, and accounting boundary checks.
- This is docs-only.
- No runtime code changed.
- 4D-2B revenue-side journal draft generation remains backlog.

## Explicit Prohibitions

This decision spec does not allow:

- No journal draft generation.
- No journal lines generation.
- No converted status.
- No `converted_journal_entry_id` write.
- No `accounting_event.converted` audit.
- No automatic posting.
- No revenue recognition runtime.
- No COGS runtime.
- No tax runtime.
- No overpayment / refund / reversal.
- No profit / gross margin payload.
- No automatic posting.
- No revenue / COGS recognition runtime.

## Phase Recommendation

- Phase 4D-2A completed: preflight service.
- Phase 4D-2A-1 completed: runtime mapping decision spec.
- Phase 4D-2A-2 completed: database-backed mapping foundation, no UI, no draft generation.
- Phase 4D-2A-3 completed: minimal mapping management UI, no draft generation.
- Phase 4D-2B future: revenue-side journal draft generation only.
- Phase 5 future: COGS / vehicle cost basis / tax / overpayment / refund / reversal.

## Accounting Boundary

- Preflight only returns backend-validated preview.
- Preflight must not be treated as recognition or conversion.
- Formal draft generation must wait until runtime mapping source, tenant scope, account validation, audit requirements, and idempotency are implemented in runtime code.
- Posting remains manual through the existing Accounting Journal workflow.
