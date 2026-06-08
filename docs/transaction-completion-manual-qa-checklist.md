# Transaction Completion Manual QA Checklist

## 1. 目的

本文件用於手動驗證 `Customer → Vehicle Sale → Receivables / Payments → Mark Sold → Complete Transaction → Audit Logs` 的實際操作流程。

本文件只記錄 manual QA checklist documented，不代表 manual QA passed。

## 2. 測試前提

- 使用 admin 或具有必要權限的測試帳號。
- 權限至少包含 `module.vehicles.view`。
- 權限至少包含 `module.vehicles.sales.view`。
- 權限至少包含 `module.vehicles.sales.create`。
- 權限至少包含 `module.receivables.view`。
- 權限至少包含 `module.receivables.create`。
- 權限至少包含 `module.receivables.mark-sold`。
- 權限至少包含 `module.vehicles.sales.completion.confirm`。
- 權限至少包含 `module.audit.view`。
- 已執行 migrations。
- 已執行 seeders。
- 前端 build 可正常載入。
- 測試前確認沒有同車 active sale 衝突。

## 3. Happy Path QA

1. 建立 Customer。

   預期：`customer_number` 自動產生。

2. 建立 Vehicle。

   預期：`stock_number` 自動產生，狀態 `in_stock`。

3. 建立 reserved Vehicle Sale。

   預期：`sale_status = reserved`，vehicle `lifecycle_status = reserved`。

4. 前往 Receivables Show。

   預期：顯示應收金額，completion status 尚不可完成交易。

5. 新增收款直到 paid 或 overpaid。

   預期：`receivable_status = paid / overpaid`。

6. 執行 Mark Sold。

   預期：`sale_status = sold`，vehicle `lifecycle_status = sold`。

7. Receivables Show 顯示可完成交易。

   預期：`completion.status = ready_to_complete`，顯示完成交易 action。

8. 輸入 `completion_note` 並完成交易。

   預期：`completed_at` 有值，`completed_by` 顯示操作者，note 正確。

9. 前往 Vehicle Show。

   預期：顯示已完成交易 summary，沒有完成交易 action。

10. 前往 Vehicle Edit。

    預期：顯示唯讀交易完成 summary，沒有 completion 表單或 action。

11. 前往 Audit Logs。

    預期：存在 `vehicle_sale.transaction_completed` event。

12. 確認不產生 accounting event / journal draft。

    預期：沒有會計事件、沒有會計傳票自動新增、沒有 revenue / COGS / profit 顯示。

## 4. Blocked Path QA

### 4.1 未收款完成

- 情境：sale sold / vehicle sold 但 payment unpaid / partial。
- 預期：Receivables Show 不顯示完成交易 action，block reason 為「收款尚未完成，無法完成交易。」

### 4.2 尚未 mark sold

- 情境：sale reserved / vehicle reserved，即使收款 paid。
- 預期：不能完成交易，提示必須先完成成交狀態銜接。

### 4.3 沒有 completion.confirm 權限

- 情境：使用只有 view 權限的角色。
- 預期：看得到狀態但不能執行完成交易，沒有 complete route action。

### 4.4 已完成交易不可重複完成

- 情境：對已 completed sale 再次嘗試完成。
- 預期：UI 不顯示 action；若直接打 route，後端 422。

### 4.5 跨 tenant

- 情境：使用其他 company / branch 使用者直接打 URL。
- 預期：404 或 403，且不寫入 `completed_at`。

### 4.6 Payload tampering

直接打 PATCH route 夾帶：

- `company_id`
- `branch_id`
- `completed_by`
- `completed_at`
- `sale_status`
- `accounting_event_id`
- `journal_entry_id`
- `revenue_amount`
- `cogs_amount`
- `gross_profit`
- `gross_margin`

預期：

- 後端 403。
- `completed_at` 仍為 null。
- 不產生 audit event。

## 5. UI 檢查

- Receivables Index 是否顯示 completion status summary。
- Receivables Show 是否顯示完整 completion block。
- Receivables Show 的 completion form 是否只送 `completion_note`。
- Vehicle Show 是否只讀顯示 completion summary。
- Vehicle Edit 是否只讀顯示 completion summary。
- Customer Transaction History 不應出現 completion action。
- 沒有 profit / gross margin 顯示。
- 沒有 revenue / COGS 顯示。
- 沒有 accounting journal 自動產生提示。

## 6. Audit 檢查

- `vehicle_sale.transaction_completed` 是否出現在 Audit Logs。
- audit module 是否顯示為 `vehicle_sales` 或對應中文。
- audit snapshot 不應包含 `company_id`。
- audit snapshot 不應包含 `branch_id`。
- audit snapshot 不應包含 `customer_phone`。
- audit snapshot 不應包含 `id_number`。
- audit snapshot 不應包含 `birthday`。
- audit snapshot 不應包含 `address`。
- audit snapshot 不應包含 `gross_profit`。
- audit snapshot 不應包含 `gross_margin`。
- audit snapshot 不應包含 `profit`。
- audit snapshot 不應包含 `accounting_event_id`。
- audit snapshot 不應包含 `journal_entry_id`。
- audit snapshot 可包含 `vehicle_sale_id`。
- audit snapshot 可包含 `vehicle_stock_number`。
- audit snapshot 可包含 `customer_number`。
- audit snapshot 可包含 `customer_name`。
- audit snapshot 可包含 `completed_at`。
- audit snapshot 可包含 `completed_by`。
- audit snapshot 可包含 `completion_note`。
- audit snapshot 可包含 `receivable_status`。

## 7. 不在本次 QA 範圍

- Accounting Event。
- Journal Draft generation。
- Revenue Recognition。
- COGS Recognition。
- Profit / Gross Margin。
- AR / AP。
- Cash / Bank。
- Invoice。
- Reports。
- Refund / Return / Reversal。
- Delivery checklist / documents / photos。

## 8. 自動化驗證命令

建議命令：

```bash
npm run build
./vendor/bin/sail artisan test --filter=StaffPermissionRoleMatrixTest
./vendor/bin/sail artisan test --filter=VehicleSaleTest
./vendor/bin/sail artisan test --filter=ReceivableTest
./vendor/bin/sail artisan test
```

若只改文件，可以不執行 build / tests；但本次建議執行 full test 與 build 來建立穩定節點。

## 9. QA 結果紀錄區

| 日期 | 測試帳號 | 環境 | 測試項目 | 結果 | 備註 |
| --- | --- | --- | --- | --- | --- |
| 2026-06-08 | admin@example.com | local Sail / 192.168.0.10 | Transaction Completion Happy Path | PASS_WITH_NOTE | Customer → Vehicle Sale → Receivables → Mark Sold → Complete Transaction → Audit Logs 大致通過；交易完成狀態與 UI flow 正常。 |
| 2026-06-08 | admin@example.com | local Sail / 192.168.0.10 | Audit Localization | FIX_NEEDED | `vehicle_sale.transaction_completed`、`accounting_journal.posted`、`accounting_journal.voided` 顯示層中文化不足，本次補 AuditLogDisplay mapping。 |
| 2026-06-08 | admin@example.com | local Sail / 192.168.0.10 | Audit Localization Mapping | FIXED_BY_CODE | 已補顯示層 event / description mapping；仍需瀏覽器重新整理確認畫面顯示。 |
