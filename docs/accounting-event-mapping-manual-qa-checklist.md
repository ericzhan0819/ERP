# Accounting Event Mapping Manual QA Checklist

## Status

- Accounting Event Phase 4D-2A-3 Minimal Mapping Management UI completed.
- This checklist is docs-only.
- Runtime journal draft generation is still not implemented.
- Config remains disabled by default.

## Preconditions

- 已登入 `admin@example.com` 或 accounting role user。
- 已有 active accounting accounts：
- asset 類型科目，用於 `accounts_receivable_account`。
- revenue 類型科目，用於 `sales_revenue_account`。
- 已有完成交易後建立的 Accounting Event。
- Accounting Event 可被 review。
- Mapping config default remains disabled；所以 convert 仍不應成功建立 journal draft。

## Permission / Sidebar QA

- [ ] admin 看得到「會計事件映射」入口。
- [ ] accounting 看得到「會計事件映射」入口。
- [ ] viewer 看不到入口，直接打 URL 不可進入。
- [ ] sales 看不到入口，直接打 URL 不可進入。
- [ ] inventory 看不到入口，直接打 URL 不可進入。
- [ ] Staff Permission matrix 顯示 `accounting.event-mappings` / 會計事件映射。
- [ ] Staff Permission matrix 顯示 view/create/update 三個 actions。
- [ ] 不應出現 delete/export/post/void/convert 等不相關 actions。

## Mapping Index QA

- [ ] 進入 `/employee-system/accounting/event-mappings`。
- [ ] 列表顯示事件類型、映射鍵、科目、科目類型、層級、狀態、操作人員、操作。
- [ ] 不顯示 raw `company_id`、`branch_id`、`created_by`、`updated_by`。
- [ ] `event_type` filter works。
- [ ] `mapping_key` filter works。
- [ ] `is_active` filter works。
- [ ] pagination 不破版。
- [ ] empty state 合理。

## Create Mapping QA

### A. Company Default Mapping

- [ ] 新增 `vehicle_sale_completed` / `accounts_receivable_account`。
- [ ] branch scope 選 company default。
- [ ] account 選 asset account。
- [ ] 儲存成功。
- [ ] 新增 `vehicle_sale_completed` / `sales_revenue_account`。
- [ ] branch scope 選 company default。
- [ ] account 選 revenue account。
- [ ] 儲存成功。

### B. Branch Override Mapping

- [ ] 若目前 user 有 `branch_id`，建立 current branch mapping。
- [ ] branch-specific mapping 顯示為分店覆寫。
- [ ] branch-specific mapping 優先於 company default 的規則在文件中確認。

## Validation QA

- [ ] `accounts_receivable_account` 不可選錯 revenue account；後端應拒絕。
- [ ] `sales_revenue_account` 不可選錯 asset / expense account；後端應拒絕。
- [ ] inactive account 不可使用或後端拒絕。
- [ ] cross-company account 不可使用。
- [ ] wrong branch account 不可使用。
- [ ] duplicate active mapping for same company + branch + event_type + mapping_key 應被拒絕。
- [ ] inactive mapping 與 replacement 行為依目前實作確認。
- [ ] frontend 嘗試送 `company_id` / `created_by` / `updated_by` / `source_type` 不應覆寫後端資料。

## Edit Mapping QA

- [ ] 可進入 edit page。
- [ ] 可變更 `account_id`。
- [ ] 可變更 `is_active`。
- [ ] 可變更 `notes`。
- [ ] 不可改成錯誤 account type。
- [ ] 不可改成 cross-tenant account。
- [ ] update 後回列表顯示正確。
- [ ] cross tenant mapping 直接 URL edit/update 應 404。

## Accounting Event Preflight Boundary QA

目前 `config/accounting_event_mappings.php` 仍保持 `vehicle_sale_completed.enabled = false`，所以 expected behavior 是 fail-safe。

- [ ] 建好 AR / Sales Revenue mapping 後，回到 reviewed Accounting Event。
- [ ] 點 convert / 轉傳票。
- [ ] 系統仍不建立 journal draft。
- [ ] 系統仍不建立 journal lines。
- [ ] 系統仍不改 accounting event status converted。
- [ ] 系統仍不寫 `converted_journal_entry_id`。
- [ ] 系統仍不寫 `accounting_event.converted` audit。
- [ ] 若畫面出現 mapping disabled fail-safe，這是目前正確行為。
- [ ] 真正 convert success 留到 Phase 4D-2B。

## Negative Security QA

- [ ] viewer 直接 POST store route 應 403。
- [ ] viewer 直接 PATCH update route 應 403。
- [ ] sales / inventory 直接 URL 應不可進入。
- [ ] `module.accounting.view` alone 不應授權 mapping management。
- [ ] frontend route visibility 不可作為安全來源，後端 policy/request/controller 必須拒絕。
- [ ] payload 不應輸出 sensitive customer data。
- [ ] payload 不應輸出 profit / gross margin / purchase_cost / cogs_amount。

## Accounting Boundary Confirmation

- No journal draft generation.
- No journal lines generation.
- No converted status.
- No `converted_journal_entry_id` write.
- No `accounting_event.converted` audit.
- No automatic posting.
- No revenue recognition runtime.
- No COGS recognition runtime.
- No tax runtime.
- No overpayment / refund / reversal.
- No AR / AP / Cash / Bank / Invoice / Reports.
- No profit / gross margin payload.

## Pass Criteria

- [ ] 所有 permission / sidebar checks pass。
- [ ] admin/accounting 能完成 AR + Sales Revenue mapping。
- [ ] invalid account / wrong tenant / duplicate active mapping rejected。
- [ ] reviewed Accounting Event convert still does not generate journal draft while config disabled。
- [ ] full test suite remains green。
- [ ] npm build remains green。

## Next Step After QA

- 若 QA pass，下一步才進 Accounting Event Phase 4D-2B Revenue-side Journal Draft Generation only。
- 4D-2B 只允許建立 draft journal + two lines：
- Debit `accounts_receivable_account` = `event.amount`。
- Credit `sales_revenue_account` = `event.amount`。
- 4D-2B still must not post journal。
- COGS / tax / overpayment / refund / reversal remain Phase 5 backlog。
