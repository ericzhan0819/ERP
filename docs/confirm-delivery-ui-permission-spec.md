# Confirm Delivery UI / Permission Spec

## 1. 目的

本文件用來定義 `Confirm Delivery` / `Complete Transaction` 未來需要的權限命名、UI 入口、按鈕顯示條件、不可操作提示、後端檢查條件與使用者操作流程，作為正式實作前的內部工程規格與後續 Roo Code 任務依據。

- 本文件只定義規格。
- 本次不實作任何功能。
- 本次不新增 permission。
- 本次不新增 route。
- 本次不新增資料欄位。
- 本次不新增 action。
- 本次不產生 accounting event。
- 本次不產生 journal draft。
- 本次不認列 revenue / COGS。

## 2. 背景

目前專案已完成下列能力或規格基礎：

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

未來規格方向是：

```txt
Customer
→ Vehicle Sale
→ Receivables / Payments
→ Mark Sold
→ Confirm Delivery / Complete Transaction
→ Accounting Event / Journal Draft
→ Revenue / COGS Recognition
```

後半段目前只是規格 / backlog，尚未實作。現有 repo 內目前未見 `Confirm Delivery` / `Complete Transaction` 的 route、permission、action、狀態欄位、accounting event 或 journal draft generation。

## 3. 核心原則

- `mark sold` 不等於 `Confirm Delivery`。
- `payment received` 不等於 `Confirm Delivery`。
- `sold` lifecycle_status 不等於 `Complete Transaction`。
- `Confirm Delivery` / `Complete Transaction` 應是獨立、可授權、可稽核的業務節點。
- UI 顯示不等於授權。
- 前端 `can` flag 只能作為 UX。
- 後端 Policy / Controller / tenant scope 才是正式安全邊界。
- Accounting event / journal draft 應由明確業務事件觸發，不應由單純 UI 狀態推斷。
- Confirm Delivery 完成後也不應直接 auto post journal。

## 4. 命名方向

### 4.1 UI 命名

未來 UI 文案可使用：

- 確認交車。
- 完成交易。
- 交車 / 交易完成。
- 交車完成狀態。

「確認交車」偏營運動作，表示車輛與交付流程已由授權人員確認。「完成交易」偏整筆交易結論，表示銷售、收款、交付與必要覆核已達成交易完成條件。

如果未來需要拆細流程，可以分成 `delivery confirmed` 與 `transaction completed`。本文件不要求短期一次拆成兩個可寫入狀態。

### 4.2 程式語意命名

未來 action / method / enum 命名候選：

- `confirm_delivery`
- `complete_transaction`
- `delivery_confirmed`
- `transaction_completed`

本文件不決定最終 route / method / enum / database 欄位名稱，只定義語意與候選命名。正式實作前需再依資料模型決策、route 結構與權限策略確認最終命名。

## 5. 未來 Permission 命名建議

目前專案主要權限命名風格為：

```txt
module.{module-key}.{action}
```

現有 nested 權限也包含 `module.vehicles.sales.payments.view`、`module.vehicles.sales.payments.create`、`module.vehicles.sales.payments.void` 這類較深層命名。以下只提出候選，不代表已實作。

### 5.1 放在 Vehicle Sales 底下

候選 permission：

- `module.vehicles.sales.delivery.view`
- `module.vehicles.sales.delivery.confirm`
- `module.vehicles.sales.complete`
- `module.vehicles.sales.completion.view`
- `module.vehicles.sales.completion.confirm`

適合理由：

- `Confirm Delivery` / `Complete Transaction` 是銷售交易的一部分。
- 操作來源與 Vehicle Sale 強相關。
- 短期比較不需要新增獨立 module entry。

風險：

- `vehicles.sales` 權限層級會越來越深。
- Staff Permission matrix 需要清楚分組，避免 sales 權限過度肥大。
- 若 seeder 的 action whitelist 未來仍限制最後一段 action，需先確認 `confirm` / `complete` 是否被允許，不能直接假設可新增。

### 5.2 未來獨立成 Delivery / Completion 子功能

候選 permission：

- `module.vehicles.delivery.view`
- `module.vehicles.delivery.confirm`
- `module.transactions.completion.view`
- `module.transactions.completion.confirm`

適合理由：

- 如果交車流程未來有文件、保險、領牌、過戶、貸款、驗車等複雜流程，獨立 module 可能更清楚。
- Delivery / Completion 若成為跨頁工作台，獨立 module registry entry 會比塞在 Vehicle Sales 底下容易維護。

風險：

- 現階段可能過度設計。
- 需要更多 sidebar / module registry 設計。
- 需要先定義新 module key、base permission、route_name、active_patterns 與 Staff Permission matrix 分組。

短期建議優先採用候選語意：

- `module.vehicles.sales.delivery.confirm`
- `module.vehicles.sales.complete`

本階段不新增 permission，不修改 seeder，不更新 permission cache，不宣稱上述權限已存在。

## 6. 角色預設方向

以下只寫方向，不實作。

- `admin`：未來可 view / confirm / complete。
- `sales`：未來可能可查看 delivery 狀態，但不一定可 confirm complete。
- `accounting`：未來可能可查看 completion 狀態，因後續會計事件與收入 / COGS 相關；不一定可執行交車確認。
- `inventory`：未來可能可查看或協助交車狀態，但不一定可 complete transaction。
- `viewer`：通常只能查看，不可操作。

角色預設要等正式實作前再決定。本階段不改 `RolePermissionSeeder`，不改 Staff Permission matrix，不新增角色權限模板。

## 7. UI 入口位置

以下只定義未來可能入口，不實作任何 UI。

### 7.1 Receivables Show

建議入口位置：

- 收款摘要區塊下方。
- mark sold 區塊之後。
- 流程語意提示區塊旁。

顯示邏輯方向：

- 如果 receivable `paid` / `overpaid` 且 sale / vehicle 已 `sold`，可顯示「可進行交車確認」提示。
- 如果 mark sold 尚未完成，只顯示「需先完成 mark sold」提示。
- 如果 `Confirm Delivery` 尚未實作，短期只能顯示說明，不顯示可操作按鈕。

### 7.2 Vehicle Show

建議入口位置：

- 銷售摘要區塊。
- 收款摘要區塊。
- 車輛狀態區塊附近。

顯示邏輯方向：

- sold vehicle 可以顯示「售出不等於交車完成」提示。
- 未來可顯示 delivery / completion status。
- 不應在 Show 頁做複雜 mutation，除非未來明確決定。

### 7.3 Vehicle Edit

建議入口位置：

- 銷售流程區塊。
- 收款摘要或銷售操作區塊附近。

顯示邏輯方向：

- Edit 頁可作為後續操作入口候選。
- 若使用者有 delivery confirm permission 且後端 `can` flag 允許，才顯示 Confirm Delivery action。
- posted / completed / locked 狀態未來需限制操作。

### 7.4 Customer Transaction History

只列為未來方向：

- 未來可顯示交易是否 completed / delivered。
- 不建議在 Customer Show 直接放確認交車 action。
- Customer 頁偏查詢與關聯交易摘要，不是交車操作主工作台。

## 8. 按鈕與狀態顯示規則

以下只定義未來 UI 狀態，不實作。

可能狀態：

- `not_ready`
- `ready_to_confirm`
- `confirmed`
- `completed`
- `blocked`
- `reversed` / `cancelled`，只作遠期可能，不實作。

### not_ready

顯示提示：

- 尚未符合交車確認條件。
- 可能原因：收款未完成、sale 未 sold、成本需覆核、權限不足。

不顯示可操作按鈕。

### ready_to_confirm

顯示：

- 可確認交車 / 完成交易。
- 需有後端 `can` flag。
- 操作前應顯示確認視窗或確認頁。

### confirmed / completed

顯示：

- 已確認交車 / 已完成交易。
- 顯示完成時間與操作人員。
- 不顯示重複確認按鈕。

### blocked

顯示：

- 目前不可確認。
- 顯示後端提供的 block reason。
- 前端不得自行推斷所有原因。

正式狀態來源必須由後端 payload 提供。前端不可只靠目前已有欄位自行推斷正式 completion status。

## 9. 後端 can flag 方向

以下只寫方向，不實作。

未來 controller payload 可考慮輸出：

- `can.confirm_delivery`
- `can.complete_transaction`
- `delivery.can_confirm`
- `delivery.block_reason`
- `completion.can_complete`
- `completion.block_reason`
- `delivery.status`
- `completion.status`

本階段不改 payload shape。本階段不新增 `can` flag。若未來實作，前端只能消費後端結果，不自行判斷授權。

## 10. 後端檢查條件方向

以下只寫方向，不實作。

未來 `Confirm Delivery` / `Complete Transaction` action 後端應檢查：

- user authenticated。
- user `company_id` / `branch_id` tenant scope。
- Vehicle Sale 屬於同 tenant。
- Vehicle 屬於同 tenant。
- Sale 未取消。
- Sale / Vehicle 狀態允許。
- Receivable `paid` / `overpaid`，或由特權角色允許例外。
- Customer 已關聯或至少有 snapshot。
- Vehicle cost records 沒有 blocking `review_required`，或已人工覆核。
- 使用者有對應 permission。
- request 不可覆寫 tenant / actor / accounting 欄位。
- 操作需產生 audit event。

這些條件不得只放在 React。正式檢查必須在 Controller / Policy / Service / FormRequest 或等價後端層執行。

## 11. 操作流程草圖

以下流程只作未來操作草圖，不新增 UI，不實作 action。

### 11.1 Happy Path

```txt
Customer / Vehicle Sale exists
→ Receivables paid / overpaid
→ Mark Sold
→ UI shows ready for Confirm Delivery
→ Authorized user clicks Confirm Delivery
→ Backend validates tenant / permission / state / cost review
→ Confirmation note submitted
→ Delivery / Completion status saved
→ Audit event created
→ Optional accounting event draft created later
→ Accounting user reviews accounting event
→ Journal draft generated later
→ Journal posted manually later
```

本階段不實作以上流程，只作規格。

### 11.2 Blocked Path

```txt
Receivable unpaid / partial
→ UI shows payment not complete
→ Confirm Delivery action hidden or disabled
→ Block reason shown
```

### 11.3 Review Required Cost Path

```txt
Cost has review_required
→ UI warns cost needs review
→ Confirm Delivery blocked or requires override
→ Authorized user resolves review
→ Continue flow
```

### 11.4 Accounting Later Path

```txt
Completion confirmed
→ Accounting event may be created
→ Journal draft may be generated
→ Revenue / COGS recognition may happen later
→ No automatic posted journal
```

## 12. 確認視窗 / 確認頁文案方向

以下只寫文案方向，不實作。

未來按下 Confirm Delivery 前，應顯示明確警示：

- 此動作表示交車 / 交易完成。
- 此動作不等於直接過帳。
- 此動作可能成為未來 revenue / COGS recognition 的依據。
- 確認後會留下 audit log。
- 若資料仍需修正，請先返回檢查銷售、收款與成本資料。

建議確認輸入：

- `confirmation_note`
- `delivered_at` 或 `completed_at`，若未來需要。
- optional checklist，遠期才考慮。

本階段不新增欄位。

## 13. Staff Permission Matrix UI 方向

以下只寫方向，不實作。

若未來新增 permission，Staff Permission matrix 應支援：

- `vehicles.sales.delivery`
- `vehicles.sales.completion`

或等價分組。

UI label 候選：

- 車輛交車。
- 交易完成。
- 交車確認。
- 完成交易。

不要把 delivery / completion 權限混在一般 `module.vehicles.sales.update` 裡。不要只靠 `module.vehicles.sales.view` 判斷是否可確認交車。不要只靠角色名稱判斷權限。

## 14. Audit Event 方向

以下只寫方向，不實作。

未來可能 audit events：

- `vehicle_sale.delivery_confirmed`
- `vehicle_sale.transaction_completed`
- `vehicle_sale.completion_reversed`
- `accounting_event.created`
- `accounting_event.reviewed`

Audit snapshot 原則：

- 不記 tenant raw ids，除非系統既有規格需要。
- 不記 profit / gross margin。
- 不記不必要敏感資料。
- 記錄 sale number / vehicle stock number / customer display summary / actor / timestamp / confirmation note。
- 具體 whitelist 等正式實作前再定義。

本階段不新增 audit event。

## 15. 明確不做的功能

本文件不做下列功能：

- 不新增 permission。
- 不修改 `RolePermissionSeeder`。
- 不修改 Staff Permission matrix。
- 不新增 route。
- 不新增 controller action。
- 不新增 policy method。
- 不新增 request。
- 不新增 migration。
- 不新增 model 欄位。
- 不新增 delivery/completion 狀態。
- 不新增 Confirm Delivery button。
- 不新增 Complete Transaction button。
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
- 不做退款 / 退車 / reversal flow。

## 16. 後續小步實作建議

以下只列 backlog，不實作。

- Phase A：完成本文件。
- Phase B：人工檢查目前 UI hints 是否足夠，不改 code 或只做極小文案修正。
- Phase C：決定 permission 命名與角色預設策略。
- Phase D：補 Staff Permission matrix 分組規格。
- Phase E：決定資料模型採直接欄位或獨立 event table。
- Phase F：新增 migration / model / policy / request 的正式實作計畫。
- Phase G：新增後端 `can` flag 與 block reason。
- Phase H：新增 Confirm Delivery UI action。
- Phase I：新增 audit event。
- Phase J：新增 Accounting Event draft。
- Phase K：由 Accounting Event 產生 Journal Draft。
- Phase L：Revenue / COGS recognition。
- Phase M：profit / gross margin reports。

Phase B 以後都不是本次工作。
