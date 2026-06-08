# Confirm Delivery Implementation Decision

## 1. 目的

本文件用來把 `Confirm Delivery` / `Complete Transaction` 從規格階段收斂到正式實作前決策草案，固定第一版要做什麼、不做什麼、採用哪種 permission 策略、資料模型方向、最小後端 / 前端 / 測試範圍。

本文件只做決策整理。

本次不實作任何功能。

本次不新增 permission。

本次不新增 route。

本次不新增資料表或欄位。

本次不新增 UI action。

本次不新增 accounting event。

本次不產生 journal draft。

本次不認列 revenue / COGS。

本次不計算 profit / gross margin。

## 2. 背景摘要

目前已完成或已文件化的基礎如下：

- Vehicle Sales。
- Receivables / Payments。
- Receivables mark-sold action。
- Customer Transaction History。
- Vehicle Cost Management。
- Accounting Accounts。
- Accounting Journals。
- Accounting Journal Workbench UI Polish。
- Vehicle Cost Accounting Treatment Spec。
- Confirm Delivery / Transaction Completion Spec。
- Confirm Delivery UI / Permission Spec。
- Staff Permission Delivery / Completion Spec。
- Sales / Payments / Delivery semantics UI hints。

目前實際流程仍是：

```txt
Customer
→ Vehicle Sale
→ Receivables / Payments
→ Mark Sold
→ Customer Transaction History
→ Audit Logs
```

未來方向才是：

```txt
Customer
→ Vehicle Sale
→ Receivables / Payments
→ Mark Sold
→ Confirm Delivery / Complete Transaction
→ Accounting Event / Journal Draft
→ Revenue / COGS Recognition
```

後半段目前尚未實作。現有 repo 目前只有 Receivables `mark-sold` action，尚未看到 `Confirm Delivery` / `Complete Transaction` route、permission、action、欄位、accounting event 或 journal draft generation。

## 3. 核心邊界

以下邊界固定為後續實作前提：

- `mark sold` 不等於 `Confirm Delivery`。
- `payment received` 不等於 `Confirm Delivery`。
- vehicle `sold` lifecycle 不等於 `Complete Transaction`。
- vehicle cost created 不等於 COGS。
- `Confirm Delivery` / `Complete Transaction` 是未來 revenue / COGS recognition 的候選節點，但第一版不做會計認列。
- Accounting Event / Journal Draft 是後續階段，不屬於第一版 Confirm Delivery。
- Confirm Delivery 完成後也不應直接 auto post journal。
- 前端 `can` flag 只作 UX，正式授權與狀態檢查必須由後端完成。

## 4. 第一版範圍決策

第一版只做：

- `Confirm Delivery` / `Complete Transaction` 的最小業務節點。
- 只處理「交易已交車 / 完成」的狀態記錄。
- 只做授權、tenant scope、狀態條件、audit log、基本 UI action。
- 只讓後端輸出 `can` flag / block reason 給前端。
- 只在 Vehicle Sale / Receivables / Vehicle 相關頁面顯示狀態與操作入口。
- 不接 accounting event。
- 不接 journal draft。
- 不接 revenue recognition。
- 不接 COGS recognition。
- 不接 profit / gross margin。

第一版不做：

- Accounting Event。
- Journal Draft generation。
- Auto-post journal。
- Revenue Recognition。
- COGS Recognition。
- Profit / Gross Margin。
- AR / AP。
- Cash / Bank。
- Invoice。
- Reports。
- Return / Refund / Reversal flow。
- Delivery checklist。
- 文件上傳。
- 交車照片。
- 複雜貸款 / 分期例外流程。

## 5. Delivery 與 Completion 是否拆開

### 5.1 選項 A：第一版合併

語意：

- 使用單一狀態代表交易已交車 / 完成。
- UI 可以顯示「確認交車 / 完成交易」。
- 後端只做一個 action。

優點：

- MVP 最小。
- 避免資料模型過早複雜化。
- 適合目前尚未有交車文件、貸款、保險、過戶、領牌等細流程時使用。

缺點：

- 未來若交車與交易完成要拆開，需 migration / data transition。
- 會計事件可能更接近 transaction completion，而不是 physical delivery。
- 營運交車與會計完成責任可能不同。

### 5.2 選項 B：第一版拆成 delivery confirmed + transaction completed

語意：

- `delivery confirmed` 表示車輛交付完成。
- `transaction completed` 表示交易流程完成並可進入後續 accounting event。
- 可分開授權。

優點：

- 長期語意更精準。
- 比較適合未來接 accounting event / revenue / COGS。
- 可支援交車與文件完成不同步的實務情境。

缺點：

- MVP scope 增加。
- 需要更多狀態、權限、UI、測試。
- 現階段容易過度設計。

### 5.3 建議決策

第一版採「合併節點」策略，但命名保留未來拆分空間。

- 第一版以 `complete_transaction` 作為主語意。
- UI 顯示可使用「確認交車 / 完成交易」。
- 文件保留 future split：未來可拆成 `delivery_confirmed` 與 `transaction_completed`。
- 第一版不新增兩套狀態，不做雙節點流程。

## 6. Permission 策略決策

前一份 Staff Permission 規格整理兩個策略：

- 策略 A：新增語意清楚的 action，例如 `confirm` / `complete`。
- 策略 B：沿用既有 `update` / `manage` 等 action whitelist。

建議第一版採策略 A，也就是新增語意清楚的 action：

- `confirm`
- `complete`

但實作順序必須小步：

第一步只改 RBAC 基礎：

- 在 `RolePermissionSeeder::ACTION_WHITELIST` 加入 `confirm` / `complete`。
- 在 `StaffPermissionController` matrix actions 加入 `confirm` / `complete`。
- 在 `actionLabels` 加入 `confirm`：確認。
- 在 `actionLabels` 加入 `complete`：完成。
- 補 `SUB_SCOPE_LABELS`：`vehicles.sales.delivery`：車輛交車。
- 補 `SUB_SCOPE_LABELS`：`vehicles.sales.completion`：交易完成。

第二步才新增實際 permission definitions。

本文件不實作上述修改。

第一版 permission 候選：

短期最小：

- `module.vehicles.sales.completion.view`
- `module.vehicles.sales.completion.confirm`

若仍想保留 delivery 語意：

- `module.vehicles.sales.delivery.view`
- `module.vehicles.sales.delivery.confirm`
- `module.vehicles.sales.completion.view`
- `module.vehicles.sales.completion.confirm`

建議第一版採：

- `module.vehicles.sales.completion.view`
- `module.vehicles.sales.completion.confirm`

理由：

- 第一版以 `complete_transaction` 為主語意。
- delivery 可先作 UI 文案，不必先成為獨立權限。
- 避免第一版權限過多。
- 未來若拆 delivery，可再新增 `vehicles.sales.delivery.*`。

現有 repo 目前 `RolePermissionSeeder::ACTION_WHITELIST` 尚未包含 `confirm` / `complete`，`StaffPermissionController` actions 與 `actionLabels` 也尚未包含這兩個 action；上述皆為未來實作事項，不代表已存在。

## 7. 資料模型決策

### 7.1 選項 A：直接加欄位到 vehicle_sales

可能欄位：

- `completed_at`
- `completed_by`
- `completion_note`
- `completion_status`

優點：

- MVP 簡單。
- 查詢容易。
- UI 顯示容易。
- 不需要額外關聯表。

缺點：

- 稽核歷史有限。
- 未來 reversal / re-confirm / multi-event 會不夠彈性。
- 如果未來拆 delivery / completion，欄位會變多。

### 7.2 選項 B：新增獨立 event table

可能資料表：

- `vehicle_sale_completion_events`
- `transaction_completion_events`

可能欄位：

- `vehicle_sale_id`
- `event_type`
- `status`
- `note`
- `created_by`
- `created_at`
- `voided_by`
- `voided_at`
- `void_reason`

優點：

- 稽核軌跡更完整。
- 未來 reversal / re-confirm / accounting event 連接較彈性。
- 比較適合交易完成是敏感業務事件。

缺點：

- MVP 複雜度較高。
- 需要額外 model / policy / relation / tests。
- UI 需要多處讀取 latest event。

### 7.3 建議決策

第一版採「直接欄位」策略，作為 MVP。

建議第一版最小欄位方向：

- `completed_at`
- `completed_by`
- `completion_note`

`completion_status` 判斷：

- 如果只有未完成 / 已完成，`completed_at` nullable 已足夠。
- 如果未來要支持 blocked / reversed / cancelled，才需要 status。
- 第一版建議不新增 `completion_status`，避免狀態過早膨脹。

本文件不新增 migration。

正式實作前仍需確認欄位命名與 rollback 策略。

## 8. Backend Action 決策

第一版未來可能 action 只定義語意，不在本文件實作。

route 語意可採 `POST` 或 `PATCH` 到 vehicle sale completion action。

命名候選：

- `employee-system.vehicles.sales.complete`
- `employee-system.vehicle-sales.complete`
- `employee-system.receivables.complete-transaction`

根據目前 repo 結構，現有 Vehicle Sale mutation 掛在 `employee-system.vehicles.sales.*`，而 Receivables 另有 `employee-system.receivables.mark-sold`。交易完成不是收款動作，因此不宜直接假設放在 `ReceivableController`。

建議第一版 action 語意：

- action 名稱以 `completeTransaction` 或 `complete` 為主。
- 由 `VehicleSaleController` 或專門 controller 處理需再決定。
- 若目前 mark sold 在 `ReceivableController`，交易完成不一定要放在 `ReceivableController`，因為它不是收款動作。
- 第一版建議傾向放在 Vehicle Sale domain，而不是 Receivables domain。

本文件不新增 route / controller action。

## 9. Backend Validation / Guard 決策

第一版後端正式檢查條件如下：

- 使用者已登入。
- 使用者屬於同 company / branch tenant。
- Vehicle Sale 屬於同 tenant。
- Vehicle 屬於同 tenant。
- Sale 未取消。
- Sale status 已為 `sold`，或符合明確允許條件。
- Vehicle `lifecycle_status` 已為 `sold`，或符合明確允許條件。
- Receivable status 為 `paid` / `overpaid`。
- Customer 已關聯或至少有 sale snapshot。
- 沒有 blocking cost review requirement；若 `review_required` 尚未實作，第一版只顯示提示，不阻擋，或明確列為後續。
- 使用者具備 `module.vehicles.sales.completion.confirm`。
- request 不可覆寫 tenant / actor / accounting 欄位。
- 不接受前端傳入 `completed_by`。
- 不接受前端傳入 accounting fields。
- 完成後需寫 audit event。

正式 guard 不可只寫在 React。後端需使用 Policy / Controller / Service / FormRequest 或等價層處理。

## 10. Audit Event 決策

第一版 audit event 方向只定義，不在本文件實作。

候選 event：

- `vehicle_sale.transaction_completed`
- `vehicle_sale.completed`

`vehicle_sale.transaction_completed` 語意較明確，且不會和未來 accounting event 混淆。

建議第一版採：

- `vehicle_sale.transaction_completed`

Audit snapshot whitelist 建議：

- sale id 或 sale number，如目前沒有 sale number 則記可安全顯示資訊。
- vehicle stock_number。
- customer display summary。
- completed_at。
- completion_note。
- actor display。
- 不記 tenant raw ids。
- 不記 profit / gross margin。
- 不記敏感個資。
- 不記 accounting journal fields。

本文件不新增 audit event。

## 11. Frontend UI 決策

第一版 UI 入口只定義，不在本文件實作。

### 11.1 Receivables Show

- 顯示 completion status。
- 如果 sale 已 `sold`、receivable `paid` / `overpaid`、且後端 `can` flag 允許，顯示「完成交易」或「確認交車 / 完成交易」按鈕。
- 若不可操作，顯示 block reason。

### 11.2 Vehicle Show

- 顯示唯讀 completion status。
- 不放主要 mutation，避免 Show 頁過度承載操作。

### 11.3 Vehicle Edit

- 可作為第二入口，但第一版建議先集中在 Receivables Show 或 Vehicle Sale domain 頁面。
- 若要放按鈕，必須吃後端 `can` flag。

建議第一版 UI：

- 主入口：Receivables Show。
- 補充顯示：Vehicle Show / Vehicle Edit 只讀顯示狀態與提示。
- 不在 Customer Transaction History 放 action。

本文件不修改 UI。

## 12. Backend Payload / can flag 決策

第一版未來 payload 方向只定義，不在本文件實作。

建議未來後端可輸出：

- `completion.status`
- `completion.completed_at`
- `completion.completed_by_name`
- `completion.note`
- `completion.can_complete`
- `completion.block_reason`

或放在 sale payload 下：

- `sale.completion_summary`

建議第一版採：

- `completion` object

理由：

- 和 `payment_summary` 分離。
- 語意清楚。
- 未來可擴展 `accounting_event_summary`。

本文件不改 payload shape。

## 13. Request / Form 決策

第一版 request payload 只定義，不在本文件實作。

建議第一版只接受：

- `completion_note`

是否接受 `completed_at`：

- 若要避免使用者回填日期，第一版可由後端使用 `now()`。
- 若業務需要補登，未來再開放 `completed_at`，並加權限。
- 建議第一版不接受 `completed_at`，使用後端 `now()`。

不接受：

- `completed_by`
- `company_id`
- `branch_id`
- `vehicle_id`
- `customer_id`
- accounting fields
- revenue fields
- cogs fields
- profit / gross margin fields

## 14. 測試範圍決策

第一版最小測試範圍只定義，不在本文件實作。

### Permission / RBAC

- 無 `completion.confirm` 權限不可完成交易。
- 有權限但跨 tenant 不可完成交易。
- admin 可完成交易。
- viewer 不可完成交易。

### State guard

- unpaid / partial receivable 不可 complete。
- cancelled sale 不可 complete。
- unsold sale 不可 complete。
- sold + paid 可 complete。
- already completed 不可重複 complete。

### Payload security

- 前端嘗試傳 `company_id` / `branch_id` / `completed_by` / accounting fields 被忽略或拒絕。
- 不產生 profit / gross margin payload。

### Audit

- complete 後產生 `vehicle_sale.transaction_completed` audit event。
- audit 不記敏感個資、不記 profit / gross margin、不記 tenant raw ids。

### UI

- `can` flag false 時不顯示 action 或顯示 blocked reason。
- `can` flag true 時顯示完成交易 action。
- completed 後顯示完成時間與操作者。

## 15. 實作分階段決策

後續正式實作應拆成小步，不要一次完成全部。

### Phase 1：RBAC Foundation

- 新增 `confirm` / `complete` whitelist。
- 新增 completion permission definitions。
- 更新 `StaffPermissionController` actions / labels / sub scope。
- 更新 role template。
- 補 `StaffPermissionRoleMatrixTest`。
- 不新增 completion action。

### Phase 2：Data Model

- 新增 `vehicle_sales` completion 欄位。
- 更新 `VehicleSale` model casts / fillable 或 guarded 策略。
- 不新增 accounting event。

### Phase 3：Backend Completion Action

- 新增 request / policy / controller action / route。
- 實作 tenant guard / state guard / payload guard。
- 實作 audit event。
- 補 Feature tests。

### Phase 4：Frontend UI

- Receivables Show 加 completion status / action。
- Vehicle Show / Edit 加唯讀 status。
- 不接 accounting。

### Phase 5：Docs Update

- 更新 README / CURRENT_STATE。
- 確認 full test / build。

### Phase 6：Accounting Event Later

- 另開規格與實作。
- 不和 Phase 1-4 混在一起。

## 16. 明確不做的功能

本決策文件不做：

- 不新增 migration。
- 不新增 route。
- 不新增 controller action。
- 不新增 request。
- 不新增 policy method。
- 不新增 permission。
- 不修改 seeder。
- 不修改 Staff Permission Matrix。
- 不新增 UI action。
- 不新增 accounting event。
- 不新增 journal draft。
- 不自動 post journal。
- 不認列 revenue。
- 不認列 COGS。
- 不計算 profit / gross margin。
- 不做 AR / AP。
- 不做 Cash / Bank。
- 不做 Invoice。
- 不做 Reports。
- 不做 refund / return / reversal。

## 17. 開工前待確認事項

正式寫程式前要確認：

- 第一版是否採 `completion` 單一節點。
- 第一版 permission 是否採 `module.vehicles.sales.completion.view` / `module.vehicles.sales.completion.confirm`。
- 是否接受 `completed_at` 前端輸入，或由後端 `now()` 決定。
- completion action 放在 `VehicleSaleController` 還是獨立 controller。
- completion UI 主入口是否放在 Receivables Show。
- completion 是否需要 reversal；若需要，是否延期。
- cost `review_required` 尚未實作時，第一版是否只提示不阻擋。
- audit snapshot whitelist 最終欄位。
