# Accounting Event Journal Draft Manual QA Checklist

## Status

- Accounting Event Phase 4D-2B Revenue-side Journal Draft Generation completed。
- This checklist is docs-only。
- Runtime now creates draft journal and two revenue-side lines。
- Journal remains draft and unposted。
- COGS / tax / refund / reversal remain backlog。

## Preconditions

- 已登入 `admin@example.com` 或 accounting role user。
- user 必須有 `module.accounting.events.view`。
- user 必須有 `module.accounting.events.review`。
- user 必須有 `module.accounting.events.convert`。
- user 必須有 `module.accounting.journals.create`。
- user 必須有 `module.accounting.journals.view`。
- 已有 active asset accounting account，用於 `accounts_receivable_account`。
- 已有 active revenue accounting account，用於 `sales_revenue_account`。
- 已在「會計事件映射」設定 `vehicle_sale_completed` / `accounts_receivable_account` -> asset account。
- 已在「會計事件映射」設定 `vehicle_sale_completed` / `sales_revenue_account` -> revenue account。
- 已有完成交易後建立的 pending Accounting Event。
- Accounting Event 可被 review。
- config currently enabled for `vehicle_sale_completed`。

## End-to-End Happy Path QA

- [ ] 建立或找到一筆已完成交易的 Vehicle Sale。
- [ ] 確認系統建立 pending Accounting Event。
- [ ] 進入 Accounting Event show。
- [ ] 點擊 review / 覆核。
- [ ] 確認 status 變為 reviewed。
- [ ] 點擊 convert / 轉傳票。
- [ ] 系統顯示成功訊息：會計事件已產生傳票草稿。
- [ ] Accounting Event status 變為 converted。
- [ ] Accounting Event 顯示 `converted_journal_entry`。
- [ ] `converted_journal_entry` 顯示 `journal_number`。
- [ ] `converted_journal_entry` status = draft。
- [ ] `converted_journal_entry` entry_date 正確。

## Journal Draft QA

- [ ] 進入會計傳票列表。
- [ ] 找到剛產生的 `journal_number`。
- [ ] Journal status = draft。
- [ ] Journal source_type = accounting_event。
- [ ] Journal source_id 對應 Accounting Event id。
- [ ] Journal entry_date 等於 Accounting Event event_date。
- [ ] Journal summary 類似：車輛交易完成轉傳票：`{source_number}`。
- [ ] Journal 不應自動 posted。
- [ ] `posted_at` 應為空。
- [ ] `posted_by` 應為空。

## Journal Lines QA

- [ ] Journal 只有兩條 lines。
- [ ] 第一條為 AR debit line。
- [ ] AR debit line account = `accounts_receivable_account` 對應科目。
- [ ] AR debit line debit = `event.amount`。
- [ ] AR debit line credit = 0。
- [ ] 第二條為 Sales Revenue credit line。
- [ ] Sales Revenue credit line account = `sales_revenue_account` 對應科目。
- [ ] Sales Revenue credit line debit = 0。
- [ ] Sales Revenue credit line credit = `event.amount`。
- [ ] total_debit = total_credit。
- [ ] total_debit = `event.amount`。
- [ ] total_credit = `event.amount`。
- [ ] lines 不包含 COGS。
- [ ] lines 不包含 inventory。
- [ ] lines 不包含 tax。
- [ ] lines 不包含 overpayment。
- [ ] lines 不包含 rounding adjustment。

## Duplicate Convert QA

- [ ] 對同一筆 converted Accounting Event 再按一次 convert。
- [ ] 系統應拒絕或不顯示 convert action。
- [ ] 不建立第二張 journal。
- [ ] 不建立額外 journal lines。
- [ ] `converted_journal_entry_id` 不變。
- [ ] 原 `journal_number` 不變。

## Missing / Invalid Mapping QA

- [ ] 停用 `accounts_receivable_account` mapping 後嘗試 convert，應失敗。
- [ ] 停用 `sales_revenue_account` mapping 後嘗試 convert，應失敗。
- [ ] AR mapping 指到 inactive account，應失敗。
- [ ] Sales Revenue mapping 指到 inactive account，應失敗。
- [ ] AR mapping 指到 wrong account type，例如 revenue，應失敗。
- [ ] Sales Revenue mapping 指到 wrong account type，例如 asset / expense，應失敗。
- [ ] cross-company account 不可被使用。
- [ ] wrong-branch account 不可被使用。
- [ ] 失敗時不建立 journal。
- [ ] 失敗時不建立 lines。
- [ ] 失敗時 event status 保持 reviewed。
- [ ] 失敗時 `converted_journal_entry_id` 仍為空。

## Permission QA

- [ ] viewer 看得到允許的頁面時仍不可 convert。
- [ ] sales 不可 convert accounting event。
- [ ] inventory 不可 convert accounting event。
- [ ] 只有 `module.accounting.view` 不可 convert。
- [ ] 只有 `module.accounting.events.view` 不可 convert。
- [ ] 只有 `module.accounting.events.review` 不可 convert。
- [ ] 有 `module.accounting.events.convert` 但沒有 `module.accounting.journals.create` 時不可 convert。
- [ ] 無權限 direct PATCH convert route 應 403。
- [ ] cross-tenant direct PATCH convert route 應 404。

## Audit QA

- [ ] convert success 後產生 `accounting_event.converted` audit。
- [ ] Audit display event label 顯示「會計事件轉傳票」。
- [ ] Audit display module label 顯示「會計事件」。
- [ ] Audit display description label 顯示「會計事件已轉傳票」。
- [ ] Audit new_values 包含 safe fields：`source_type`。
- [ ] Audit new_values 包含 safe fields：`source_id`。
- [ ] Audit new_values 包含 safe fields：`source_number`。
- [ ] Audit new_values 包含 safe fields：`event_type`。
- [ ] Audit new_values 包含 safe fields：`old_status` / `new_status`。
- [ ] Audit new_values 包含 safe fields：`converted_journal_entry_id`。
- [ ] Audit new_values 包含 safe fields：`journal_number`。
- [ ] Audit new_values 包含 safe fields：`amount`。
- [ ] Audit new_values 包含 safe fields：`currency`。
- [ ] Audit 不包含 full payload。
- [ ] Audit 不包含 `customer_phone`。
- [ ] Audit 不包含 `id_number`。
- [ ] Audit 不包含 `birthday`。
- [ ] Audit 不包含 `address`。
- [ ] Audit 不包含 `profit`。
- [ ] Audit 不包含 `gross_profit`。
- [ ] Audit 不包含 `gross_margin`。
- [ ] Audit 不包含 `purchase_cost`。
- [ ] Audit 不包含 `cogs_amount`。
- [ ] Audit 不包含 `revenue_amount`。

## Payload / Sensitive Data QA

- [ ] Accounting Event show payload 不顯示 `profit`。
- [ ] Accounting Event show payload 不顯示 `gross_margin`。
- [ ] Accounting Event show payload 不顯示 `purchase_cost`。
- [ ] Accounting Event show payload 不顯示 `cogs_amount`。
- [ ] Convert request 不接受前端傳入 `account_id`。
- [ ] Convert request 不接受前端傳入 `journal_number`。
- [ ] Convert request 不接受前端傳入 journal lines。
- [ ] Convert request 不接受前端傳入 `converted_journal_entry_id`。
- [ ] Convert request 不接受前端傳入 amount / status / payload。
- [ ] Convert request 不接受 customer sensitive fields。

## Accounting Boundary Confirmation

- Journal is draft only。
- No automatic posting。
- No posted journal generation。
- No COGS recognition runtime。
- No inventory recognition runtime。
- No tax runtime。
- No overpayment runtime。
- No refund / reversal runtime。
- No AR / AP / Cash / Bank / Invoice / Reports integration。
- No profit / gross margin payload。
- No PDF / Excel。
- No full accounting reports。
- No dashboard integration。

## Pass Criteria

- [ ] reviewed event with valid mapping converts successfully。
- [ ] exactly one draft journal created。
- [ ] exactly two journal lines created。
- [ ] debit and credit are balanced。
- [ ] event status becomes converted。
- [ ] `converted_journal_entry_id` points to created journal。
- [ ] audit event `accounting_event.converted` exists。
- [ ] duplicate convert blocked。
- [ ] invalid mapping blocked。
- [ ] unauthorized users blocked。
- [ ] journal remains draft。
- [ ] no COGS / tax / profit behavior appears。
- [ ] full test suite remains green。
- [ ] npm build remains green。

## Next Step After QA

- 若 QA pass，下一步可以做 Phase 4D-2B UI polish 或 Phase 5 COGS / vehicle cost basis decision spec。
- 建議先做 Phase 4D-2B UI polish。
- Accounting Event show 加強 converted journal link / status hint。
- Journal show 顯示 source accounting event reference。
- 不新增會計邏輯。
- Phase 5 才討論 vehicle cost basis。
- Phase 5 才討論 COGS。
- Phase 5 才討論 inventory。
- Phase 5 才討論 tax。
- Phase 5 才討論 refund / reversal。
