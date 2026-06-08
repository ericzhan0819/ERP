# Confirm Delivery / Transaction Completion Spec

## 1. 目的

本文件定義中古車交易中的 `Confirm Delivery` / `Complete Transaction` 業務節點，作為後續小步實作交車完成、交易完成、會計事件、收入認列與 COGS 認列流程的規格基礎。

本文件只定義規格。

本次不實作任何功能。

本次不改資料庫。

本次不產生自動分錄。

本次不認列收入或 COGS。

## 2. 背景

目前專案已完成下列能力：

- Customer Management
- Vehicle Sales
- Receivables / Payments
- Receivables mark-sold action
- Customer Transaction History
- Vehicle Cost Management
- Accounting Accounts
- Accounting Journals
- Journal Posting / Voiding
- Vehicle Cost Accounting Treatment Spec

目前完整業務流大致如下：

```txt
Customer
→ Vehicle Sale
→ Receivables / Payments
→ Mark Sold
→ Customer Transaction History
→ Audit Logs
```

目前流程已能建立客戶、建立車輛銷售、管理應收與收款、在符合條件時執行 `mark sold`，並在客戶交易紀錄與稽核紀錄中呈現主要業務事件。

但目前缺少「交車完成 / 交易完成」這個明確節點。缺少此節點時，`mark sold`、收款完成、車輛售出狀態、收入認列、COGS 認列與會計分錄觸發時點容易被混為同一件事。

本文件用來先固定語意邊界，避免後續實作直接把 UI 狀態、收款狀態或車輛 lifecycle 誤當作正式會計事件。

## 3. 核心原則

- `mark sold` 不等於收入認列。
- `payment received` 不等於收入認列。
- `vehicle cost created` 不等於 COGS 認列。
- `sold` lifecycle status 代表車輛狀態，不等於完整交易完成。
- `Confirm Delivery` / `Complete Transaction` 才是未來認列 revenue / COGS 的候選節點。
- 業務單據優先，會計分錄在背後。
- 手動傳票是例外 / 進階操作。
- Accounting event / journal draft 應該由明確業務事件觸發，而不是由 UI 狀態誤判。

`Confirm Delivery` / `Complete Transaction` 不應在短期 MVP 中被簡化成「按下 mark sold 後自動認列收入與 COGS」。它應該是獨立、可稽核、可授權、可覆核的業務節點。

## 4. 現有狀態語意整理

### 4.1 Vehicle lifecycle_status

目前車輛 lifecycle status 的實務語意如下：

- `in_stock`：車輛在庫，可銷售。
- `reserved`：車輛被銷售流程保留。
- `sold`：車輛已售出或銷售流程已完成 `mark sold`，但不必然代表收入已正式認列。
- `archived`：封存，不作為一般營運流程。

目前專案文件亦提到 `draft` 作為 lifecycle whitelist 之一，但主要營運流程聚焦在 `in_stock` → `reserved` → `sold`。本文件聚焦交易完成節點，不重新定義既有 lifecycle 狀態。

### 4.2 Vehicle Sale sale_status

目前 Vehicle Sale `sale_status` 的語意可整理如下：

- `draft`：銷售草稿或尚未進入正式保留流程。
- `reserved`：銷售已保留車輛，車輛通常同步為 reserved。
- `sold`：銷售已完成售出狀態銜接，車輛通常同步為 sold。
- `cancelled`：銷售取消，不作為 active sale。

若後續實際 config 或程式碼標籤有所調整，應以現有程式碼為準。本文件不新增或修改任何 `sale_status`。

### 4.3 Receivables / Payments

Receivables 管理應收、已收、未收與收款狀態。

Payments 是收款紀錄。

`received` payment 只代表收到款項。

`voided` payment 不計入已收。

`paid` / `overpaid` 可作為 `mark sold` 的條件，但不等於收入認列。

目前 `vehicle_sales.deposit_amount` 僅作訂金快照語意，真正已收金額應由有效收款紀錄計算。Receivables / Payments 仍屬業務收款紀錄，不代表已完成 AR、Cash、Bank、Invoice 或 revenue accounting。

### 4.4 Mark Sold

目前 `mark sold` 的實務語意如下：

- 當 receivable `paid` / `overpaid`，且 sale / vehicle 狀態允許時，把 `sale_status` 與 vehicle `lifecycle_status` 推到 `sold`。
- 它是銷售流程中的狀態銜接，不是完整會計事件。
- 它不應直接產生收入認列或 COGS 認列。

`mark sold` 解決的是銷售流程與車輛售出狀態銜接問題，不解決交車是否完成、文件是否完成、成本是否覆核、收入是否應認列、COGS 是否應結轉等問題。

## 5. Confirm Delivery / Complete Transaction 定義

`Confirm Delivery` / `Complete Transaction` 表示：

- 車輛已交付或交易已正式完成。
- 客戶資料、車輛資料、銷售資料、收款狀態已確認。
- 必要文件與交付流程已完成或由使用者確認。
- 此節點可作為未來 revenue recognition / COGS recognition 的候選點。
- 此節點應產生 audit event。
- 未來可能產生 accounting event 或 journal draft，但不一定直接自動 post。

命名方向如下：

- UI 可稱為「確認交車」或「完成交易」。
- 程式語意可考慮 `confirm_delivery` 或 `complete_transaction`。
- 短期文件不決定最終 route / method / database 欄位名稱，只定義語意。

`Confirm Delivery` 偏向交付車輛這個營運動作；`Complete Transaction` 偏向整筆交易已完成的業務結論。若後續流程需要同時支援「先交車、後補文件」或「文件完成但尚未交車」等情境，可能需要拆成兩個狀態；本階段只先定義共同候選節點。

## 6. 建議前置條件

未來執行 `Confirm Delivery` / `Complete Transaction` 前，可能需要滿足下列條件：

- Vehicle Sale 存在且屬於同 tenant。
- Vehicle 已經是 `reserved` 或 `sold` 狀態。
- Sale 未取消。
- Receivable 狀態為 `paid` 或 `overpaid`，或由有權限角色允許例外處理。
- Customer 已關聯或至少有交易 snapshot。
- Vehicle cost records 已完成基本確認。
- 若有 `review_required` cost，應提示人工覆核。
- 使用者具有對應權限，例如未來可能的 `module.vehicles.sales.delivery.confirm` 或 `module.vehicles.sales.complete`。
- 操作需記錄 audit log。

本階段只是規格，不新增權限。

前置條件必須由後端作為安全與資料一致性的唯一判斷來源。前端提示只能作為 UX，不可被視為授權或資料有效性保證。

## 7. 建議後置效果

未來完成交易後，可能造成下列效果：

- Vehicle Sale status 可能進入 `completed` 或 `delivered`。
- Vehicle `lifecycle_status` 維持 `sold`。
- 記錄 `delivered_at` / `completed_at`。
- 記錄 `delivered_by` / `completed_by`。
- 記錄 confirmation note。
- 產生 audit event，例如 `vehicle_sale.delivery_confirmed` 或 `vehicle_sale.transaction_completed`。
- 未來可產生 accounting event。
- 未來可產生 journal draft。
- 未來可觸發 revenue / COGS recognition。

本階段不新增欄位、不新增狀態、不新增 audit event。

若未來導入 accounting event，建議先產生可覆核的 event 或 draft，而不是在使用者確認交車時直接產生 posted journal。正式過帳仍應保留會計人員覆核與 journal post 流程。

## 8. Revenue Recognition 邊界

收到款項不是收入認列。

`mark sold` 不是收入認列。

`Confirm Delivery` / `Complete Transaction` 才可能是收入認列候選點。

未來收入認列可能借記 Accounts Receivable / Cash / Clearing，貸記 Vehicle Sales Revenue。

但本階段不實作任何 journal entry。

不要在目前 MVP 中把 `sale_price` 直接當作已正式認列收入。

收入認列應由明確業務事件、授權檢查、tenant scope、資料完整性檢查與會計覆核流程支撐。即使未來交易完成時產生 accounting event，也不代表必須直接自動 post journal。

## 9. COGS Recognition 邊界

Vehicle Cost created 只是成本資料建立。

Capitalized vehicle costs 應在交易完成時才可能轉為 COGS。

COGS 應依 `docs/vehicle-cost-accounting-treatment-spec.md` 的分類彙總。

`review_required` 成本不可自動認列。

未來 COGS 可能借記 COGS / Vehicle Cost of Goods Sold，貸記 Vehicle Inventory / Capitalized Vehicle Cost。

但本階段不實作 COGS、不計算毛利、不新增 profit / gross margin payload。

COGS recognition 不應只依 `vehicle.lifecycle_status = sold` 或 `sale_status = sold` 自動觸發。它應該依交易完成節點與成本分類覆核結果小步銜接。

## 10. 與 Receivables / Payments 的關係

Receivables / Payments 解決「有沒有收到錢」。

`Confirm Delivery` / `Complete Transaction` 解決「交易是否正式完成」。

Accounting recognition 解決「是否正式入帳」。

三者不可混為同一個狀態。

`paid` / `overpaid` 可以是 confirm delivery 的條件之一，但不是唯一語意。

若未來有貸款、分期、保留款、尾款，可能需要例外流程，但本階段不實作。

短期應避免把 Receivables 的付款狀態直接映射成交易完成狀態。付款完成只能表示收款面滿足條件，不能替代交車確認、文件確認、成本覆核與會計認列判斷。

## 11. 與 Vehicle Cost Accounting Treatment 的關係

`Confirm Delivery` / `Complete Transaction` 是未來彙總 capitalized vehicle costs 的時間點。

`purchase_price` / `repair` / `detailing` / `inspection` 等 capitalized cost 未來可進入 COGS 計算。

`transport` / `tax` / `other` 需依 review 結果處理。

`management` 通常是 period expense，不應進入單車 COGS，除非未來規格另行定義。

本階段不自動彙總、不自動產生分錄。

成本資料建立、成本付款、成本覆核、成本資本化與 COGS 認列是不同層次。未來若新增成本會計事件，仍應以 `docs/vehicle-cost-accounting-treatment-spec.md` 的分類規則作為基礎。

## 12. 未來資料模型方向

以下只寫方向，不實作。

Vehicle Sale 可能增加：

- `delivery_status`
- `delivered_at`
- `delivered_by`
- `delivery_note`
- `completed_at`
- `completed_by`
- `completion_note`
- `revenue_recognition_status`
- `cogs_recognition_status`

或可能新增獨立表：

- `vehicle_sale_deliveries`
- `transaction_completion_events`
- `accounting_events`

Accounting event 可能欄位：

- `source_type`
- `source_id`
- `event_type`
- `status`
- `reviewed_by`
- `reviewed_at`
- `journal_entry_id`

本階段不新增欄位。

本階段不新增資料表。

本階段不改 migration。

本階段不改 model。

本階段不產生 journal entry。

資料模型方向需要在後續實作前再決定採用「直接欄位」或「獨立事件表」。若交易完成可能有覆核、撤銷、重送 accounting event、分階段交付等需求，獨立事件表會比單純欄位更容易保留稽核軌跡。

## 13. 權限與稽核方向

以下只寫方向，不實作。

未來可能權限：

- `module.vehicles.sales.delivery.view`
- `module.vehicles.sales.delivery.confirm`
- `module.vehicles.sales.complete`
- `module.accounting.events.view`
- `module.accounting.events.review`

未來 audit events 可能包含：

- `vehicle_sale.delivery_confirmed`
- `vehicle_sale.transaction_completed`
- `vehicle_sale.completion_reversed`
- `accounting_event.created`
- `accounting_event.reviewed`

本階段不新增 permissions，不修改 seeder，不新增 audit event。

未來權限應集中於既有 RBAC / Module Registry / Policy / Middleware 架構，不應在 React component 內以角色名稱硬判斷交車或交易完成權限。交易完成屬敏感業務節點，後端必須檢查 tenant scope、銷售狀態、付款狀態、成本覆核狀態與操作者權限。

## 14. 明確不做的功能

本文件不做下列功能：

- 不新增欄位。
- 不新增資料表。
- 不改 Vehicle Sale CRUD。
- 不改 Receivables。
- 不改 Payments。
- 不改 Vehicle Costs。
- 不改 Accounting Accounts。
- 不改 Accounting Journals。
- 不新增權限。
- 不新增 audit event。
- 不產生自動分錄。
- 不認列 revenue。
- 不認列 COGS。
- 不計算 profit / gross margin。
- 不做 AR / AP。
- 不做 Cash / Bank。
- 不做 Invoice。
- 不做 Reports。
- 不做完整稅務處理。
- 不做退車 / 退款 / 作廢交易完成流程。

本文件也不修改 routes、controllers、models、requests、policies、migrations、seeders、config、README.md 或 CURRENT_STATE.md。

## 15. 後續小步實作建議

以下只作 backlog，Phase B 以後都不是本次工作。

- Phase A：完成本文件。
- Phase B：在 Vehicle Sale / Receivables UI 顯示 `mark sold` 與 `confirm delivery` 的語意提示，不改資料庫。
- Phase C：新增 `Confirm Delivery` / `Complete Transaction` 規格對應的權限命名與 UI 草圖。
- Phase D：新增 delivery / completion 欄位或獨立 delivery event 表。
- Phase E：新增 delivery confirmation action。
- Phase F：新增 accounting event draft，不直接產生 posted journal。
- Phase G：由 accounting event 產生 journal draft。
- Phase H：Revenue / COGS recognition。
- Phase I：毛利報表與正式 accounting reports。

每一個 phase 都應維持小步實作，並在進入程式修改前先確認資料模型、權限、tenant scope、audit event 與測試範圍。
