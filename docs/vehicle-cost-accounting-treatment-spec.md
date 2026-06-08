# Vehicle Cost Accounting Treatment Spec

## 1. 目的

本文件定義 `vehicle_costs.cost_type` 對應的會計處理方向，作為後續 Vehicle Cost → Accounting Treatment 的規格基礎。

本文件只描述規格與判斷原則，不代表本階段要實作自動分錄、完整會計、收入認列或 COGS 認列。

## 2. 背景

目前專案已完成 Vehicle Cost Management Phase 2、Accounting Accounts、Accounting Journals、Journal Posting / Voiding。車輛成本、會計科目與會計傳票已具備獨立基礎能力，但尚未把 `vehicle_costs` 自動串接到會計分錄。

目前會計與業務流程原則如下：

- 業務單據優先。
- 會計分錄在背後。
- 手動傳票是例外 / 進階操作。
- `mark sold` 不等於收入認列。
- 未來 `Confirm Delivery` / `Complete Transaction` 才可能成為收入與 COGS 認列候選點。

## 3. 目前 vehicle cost 類型

目前 `config/vehicles.php` 定義的 `vehicle_cost_types` 如下：

| cost_type | 中文名稱 |
| --- | --- |
| `purchase_price` | 採購價 |
| `repair` | 維修 |
| `detailing` | 美容整備 |
| `tax` | 稅費 |
| `transport` | 運輸 |
| `inspection` | 檢驗 |
| `management` | 管理費 |
| `other` | 其他 |

## 4. 會計處理分類

### 4.1 Capitalized Vehicle Cost

此類成本應納入單車成本，短期可視為車輛存貨成本的一部分。未來當交易完成並達到收入與 COGS 認列條件時，這些成本才可能轉入 COGS。

包含：

- `purchase_price`
- `repair`
- `detailing`
- `inspection`

### 4.2 Usually Capitalized, Needs Review

此類成本通常可能資本化到車輛成本，但實務情境可能不同，需要人工判斷後才能決定是否納入單車成本。

包含：

- `transport`
- `tax`
- `other`

`tax` 可能包含取得車輛所必要的稅費，也可能是期間稅費、罰鍰、規費或其他稅務性質。短期不可只依 `cost_type = tax` 自動判斷會計處理。

`other` 必須人工分類，不可預設自動入帳。未來若要自動處理，必須先要求使用者選擇更明確的分類或人工覆核結果。

### 4.3 Period Expense

此類成本通常不納入單車成本，而是期間費用。若未來有特殊情境需要資本化，應透過人工覆核或更細的成本政策處理，不應直接預設自動納入車輛成本。

包含：

- `management`

## 5. 建議對應表

以下對應表只定義概念，不代表目前資料庫已存在這些 account code，也不要求 seeder 新增任何科目。

| cost_type | 中文名稱 | default_treatment | 是否納入車輛成本 | 是否需要人工覆核 | 未來可能借方科目 | 未來可能貸方科目 / 清算科目 | 備註 |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `purchase_price` | 採購價 | `capitalized` | 是 | 否 | Vehicle Inventory / 車輛存貨 | Cash / Bank / Payable / Clearing | 車輛取得主成本，未來交易完成時可作為 COGS 來源。 |
| `repair` | 維修 | `capitalized` | 是 | 否 | Repair Cost Capitalized / 資本化整備成本 | Cash / Bank / Payable / Clearing | 為使車輛達可銷售狀態的整備成本，預設納入車輛成本。 |
| `detailing` | 美容整備 | `capitalized` | 是 | 否 | Repair Cost Capitalized / 資本化整備成本 | Cash / Bank / Payable / Clearing | 與銷售前整備直接相關，預設納入車輛成本。 |
| `tax` | 稅費 | `review_required` | 待判斷 | 是 | Vehicle Inventory / 車輛存貨 或 Tax Expense / 稅費 | Cash / Bank / Payable / Clearing | 可能是取得車輛必要成本，也可能是期間稅費或其他稅務性質，短期不可自動判斷。 |
| `transport` | 運輸 | `review_required` | 通常是 | 是 | Vehicle Inventory / 車輛存貨 或 Transport Expense / 運輸費用 | Cash / Bank / Payable / Clearing | 若為取得或整備車輛必要運輸，通常可資本化；其他情境需人工確認。 |
| `inspection` | 檢驗 | `capitalized` | 是 | 否 | Vehicle Inventory / 車輛存貨 或 Repair Cost Capitalized / 資本化整備成本 | Cash / Bank / Payable / Clearing | 若為銷售前必要檢驗，預設納入車輛成本。 |
| `management` | 管理費 | `expense` | 否 | 否 | Management Expense / 管理費用 | Cash / Bank / Payable / Clearing | 通常屬期間費用，不預設納入單車成本。 |
| `other` | 其他 | `review_required` | 待判斷 | 是 | Vehicle Inventory / 車輛存貨 或 Expense / 費用 | Cash / Bank / Payable / Clearing | 必須人工分類，不可預設自動入帳。 |

## 6. 未來資料模型方向

未來若要讓 `vehicle_costs` 更明確銜接會計處理，可考慮在 `vehicle_costs` 或相關設定中加入下列欄位或設定概念：

- `accounting_treatment`
- `capitalization_policy`
- `requires_manual_review`
- `expense_account_id`
- `inventory_cost_account_id`
- `clearing_account_id`
- `reviewed_by`
- `reviewed_at`
- `accounting_event_id`

本階段不新增欄位。

本階段不改 migration。

本階段不自動產生 journal entry。

## 7. 未來流程銜接

未來可能流程如下：

```txt
Vehicle Cost created / updated
→ 根據 cost_type 標記 accounting treatment
→ 若 requires_manual_review，等待人工確認
→ Confirm Delivery / Complete Transaction
→ 彙總 capitalized vehicle costs
→ 認列 COGS
→ 產生 accounting event 或 journal draft
→ 由會計人員確認 / 過帳
```

短期不做自動化，只保留規格方向。任何自動分錄、COGS 認列或 accounting event 串接，都應在交易完成節點與成本覆核規則明確後小步實作。

## 8. 與 Receivables / Payments 的邊界

Vehicle Cost 是成本側。Receivables / Payments 是收款側。兩者可以在同一筆交易週期中相關，但不應在 MVP 中混成同一個會計處理。

邊界原則如下：

- 不要把收款視為收入認列。
- 不要把付款紀錄和成本會計處理混在同一個 MVP 裡。
- 成本付款狀態不等於正式會計入帳狀態。
- Receivables / Payments 可先維持業務收款紀錄，不代表已完成 AR、Cash、Bank 或 revenue accounting。
- Vehicle Cost 可先維持業務成本紀錄，不代表已完成 AP、payment clearing 或 COGS accounting。

## 9. 明確不做的功能

本文件不做下列功能：

- 不新增欄位。
- 不新增資料表。
- 不改成本 CRUD。
- 不改會計科目。
- 不改會計傳票。
- 不產生自動分錄。
- 不產生 COGS。
- 不計算 profit / gross margin。
- 不做 AR / AP。
- 不做 Cash / Bank。
- 不做 Invoice。
- 不做 Reports。
- 不做 Tax Engine。
- 不做完整庫存會計。

## 10. 後續小步實作建議

以下只作 backlog，不屬於本次工作範圍：

- Phase A：完成本文件。
- Phase B：在 UI 顯示成本分類提示，不改資料庫。
- Phase C：新增 `cost_type` → accounting treatment 設定檔或 service。
- Phase D：新增人工覆核欄位。
- Phase E：`Confirm Delivery` / `Complete Transaction` 規格。
- Phase F：Accounting Event / Journal Draft 串接。

Phase B 以後都不是本次工作。
