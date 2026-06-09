# Accounting Event Runtime Mapping Decision Spec

## Status

- Accounting Event Runtime Mapping Decision Spec completed.
- Phase 4D-2A Convert Preflight Service completed.
- Phase 4D-2B revenue-side journal draft generation is still blocked by runtime account mapping source.
- This is docs-only and adds no PHP / JSX / route / seeder / config / migration / test runtime code.

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

## Future Database-backed Mapping Foundation

Table candidate:

```txt
accounting_event_account_mappings
```

Field candidates:

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

This document does not add a migration.

## Future Resolver Priority

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
- No mapping UI in this docs-only commit.
- No route / permission / migration / model / policy / controller / request / seeder changes.

## Phase Recommendation

- Phase 4D-2A completed: preflight service.
- Phase 4D-2A-1 completed: runtime mapping decision spec.
- Phase 4D-2A-2 future: database-backed mapping foundation, no UI, no draft generation.
- Phase 4D-2A-3 future: mapping admin UI, optional.
- Phase 4D-2B future: revenue-side journal draft generation only.
- Phase 5 future: COGS / vehicle cost basis / tax / overpayment / refund / reversal.

## Accounting Boundary

- Preflight only returns backend-validated preview.
- Preflight must not be treated as recognition or conversion.
- Formal draft generation must wait until runtime mapping source, tenant scope, account validation, audit requirements, and idempotency are implemented in runtime code.
- Posting remains manual through the existing Accounting Journal workflow.
