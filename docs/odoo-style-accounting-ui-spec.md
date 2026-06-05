# Odoo-style Accounting UI Spec

> 狀態：規格文件
> 目標：先定義會計模組 UI / UX 與操作邏輯，不改任何程式碼。
> 範圍：Accounting Accounts、Accounting Journals，以及未來延伸到收款、成本、銷售、交車完成流程的方向。

---

## 1. 背景與目前決策

本專案是中古車 ERP / 未來 SaaS，採用 company / branch tenant scope。

目前已完成的主流程：

```txt
Customer
→ Vehicle Sale
→ Receivables / Payments
→ Mark Sold
→ Customer Transaction History
→ Audit Logs
```

目前會計相關模組已完成：

```txt
Accounting Accounts
Accounting Journals
Journal Draft Create / Update
Debit / Credit Balance Validation
JE Numbering
Post / Void
Posted / Voided Immutable
Audit Events
Accounting Sidebar Boundary Cleanup
```

目前重要決策：

```txt
Accounting 是分類 / 業務區域，不是單一大型 module。
會計科目與會計傳票是獨立 module。
module.accounting.view 僅保留作為相容 / 分類概念，不能單獨授權進入會計科目或會計傳票。
```

目前 Sidebar 不應再出現：

```txt
會計管理
```

Sidebar 應顯示：

```txt
會計科目
會計傳票
```

---

## 2. Reference Summary

以下是已整理好的 Odoo accounting 參考規格，不需要 Roo Code 讀 Odoo repo 或大型外部專案。

參考來源只作為產品邏輯與 UI 操作模式參考：

- Odoo Accounting / Finance documentation
- Odoo Chart of Accounts documentation
- Odoo Journals documentation
- Odoo Payments documentation
- Odoo Bank Reconciliation documentation

重要限制：

```txt
不要直接複製 Odoo code。
不要直接複製 Odoo CSS。
不要直接複製 Odoo XML。
不要直接複製 Odoo Python。
不要要求 Roo Code 閱讀 Odoo repo。
```

只採用以下概念：

```txt
成熟 ERP 的 accounting 操作邏輯
List view + Form view
文件狀態驅動操作
會計分錄 lines 內嵌於單據
Post 後不可直接修改
Void / Cancel 必須保留原因與軌跡
業務單據優先，會計分錄在背後
手動傳票是例外 / 進階操作
```

---

## 3. Accounting UI 核心方向

目前不要把會計 UI 做成普通 CRUD：

```txt
Index
Create
Show
Edit
```

應改成成熟 ERP 操作模型：

```txt
List view
→ Form view / Workbench
→ Header status bar
→ Right-side action buttons
→ State-driven allowed actions
→ Lines table inside same document
→ Footer totals / difference
→ Audit trace
```

### 3.1 設計目標

會計模組的 UI 應達成：

```txt
清楚知道目前文件狀態
清楚知道下一個可執行動作
避免使用者誤改已正式入帳資料
讓會計師 / 記帳士看得懂
讓後續自動傳票、應收應付、收款對帳可以銜接
```

### 3.2 不追求完整 Odoo

短期不要做完整 Odoo accounting。

目前只做：

```txt
會計模組 UI / UX 規格
會計科目頁調整方向
會計傳票頁調整方向
狀態列與操作按鈕規則
分錄 lines 操作規則
未來與業務流程串接的方向
```

---

## 4. 會計工作流原則

### 4.1 權責基礎

本 ERP 採用權責基礎觀念。

```txt
成交不一定等於已收款。
客戶可能貸款或分期。
不能把「收到錢」直接視為「收入發生」。
```

目前方向：

```txt
交車完成 / 交易完成
→ 認列收入與應收

收款
→ 沖應收
```

### 4.2 mark sold 不等於收入認列

目前 `mark sold` 的實務意義較接近：

```txt
收款完成後，把 sale 與 vehicle 推到 sold 狀態。
```

但不應直接視為完整收入認列點。

未來更合理的流程：

```txt
Vehicle Sale reserved / sold candidate
→ Receivables paid / overpaid
→ Mark Sold
→ Confirm Delivery / Complete Transaction
→ Recognize Revenue / COGS
```

短期先不要做自動收入認列。

---

## 5. 會計科目 UI 規格

會計科目不是日常作業工作台，而是會計設定頁。

### 5.1 頁面定位

```txt
Accounting Accounts = Chart of Accounts / 會計設定
```

它應該像設定頁，不是高頻交易頁。

### 5.2 List view 欄位

建議列表欄位：

```txt
Code              科目編號
Name              科目名稱
Type              科目類型
Normal Balance    正常餘額方向：Debit / Credit
Reconcile         是否允許對帳
Active            啟用 / 停用
Opening Balance   期初餘額
Notes             備註摘要
Updated At         最近更新
```

如果目前資料表尚未支援全部欄位，短期文件先定義方向，不急著補欄位。

### 5.3 Filter / Search

應支援：

```txt
搜尋：code / name / notes
類型篩選：asset / liability / equity / revenue / expense / cogs / other
狀態篩選：active / inactive / all
是否可對帳：reconcile / not reconcile / all
```

### 5.4 Create / Edit 行為

會計科目可以建立與編輯，但需避免破壞既有資料。

基本規則：

```txt
尚未被 journal lines 使用的 account：可較自由修改。
已被 journal lines 使用的 account：不應刪除，只能停用。
已被 journal lines 使用的 account：code / type 修改需謹慎，未來可加限制。
```

### 5.5 Active / Deprecated 概念

不要真的刪除已使用過的會計科目。

應採用：

```txt
active = true / false
或 deprecated = true / false
```

短期如果已有 `is_active`，沿用即可。

### 5.6 Reconcile flag

`allow_reconciliation` 或類似欄位可作為未來對帳基礎。

適合開啟 reconcile 的科目：

```txt
Accounts Receivable
Accounts Payable
Bank Clearing
Outstanding Receipts
Outstanding Payments
```

短期如果還沒有完整 bank / AR / AP，不需要立刻實作對帳功能，但欄位方向可以先定義。

---

## 6. 會計傳票 UI 規格

會計傳票是日常 / 進階會計操作工作台。

### 6.1 頁面定位

```txt
Accounting Journals = Journal Entry Workbench
```

不應只是普通 CRUD。

### 6.2 List view 欄位

建議欄位：

```txt
Journal Number     傳票編號，例如 JE-YYYYMM-0001
Date               傳票日期
Status             draft / posted / voided
Memo               摘要
Debit Total        借方合計
Credit Total       貸方合計
Difference         差額
Source Type        來源類型：manual / vehicle_sale / payment / cost / delivery
Source Number      來源單號
Created By         建立者
Posted At          過帳時間
Voided At          作廢時間
```

如果目前資料表尚未支援 source fields，短期先作為未來擴充方向。

### 6.3 List view 操作

列表應提供：

```txt
搜尋：journal number / memo / source number
狀態篩選：draft / posted / voided / all
日期區間篩選
來源類型篩選
排序：date desc / created_at desc / journal number desc
```

### 6.4 Form view / Workbench 結構

傳票表單應由上到下分成：

```txt
Status Bar
Action Buttons
Header Fields
Journal Lines Table
Footer Totals
Audit / Metadata Panel
```

#### Status Bar

狀態列顯示：

```txt
草稿 Draft
已過帳 Posted
已作廢 Voided
```

顯示規則：

```txt
目前狀態高亮
未來狀態可灰階
不可逆狀態需明顯提示
```

#### Action Buttons

右上角 actions：

```txt
draft:
- Save
- Post
- Delete 或 Cancel（視目前後端是否支援）

posted:
- Void
- View Audit

voided:
- View Audit
```

不建議在 posted / voided 狀態顯示 edit mutation。

### 6.5 Header fields

Header 欄位：

```txt
Journal Number  傳票編號，posted 後固定
Date            傳票日期
Memo            摘要
Status          狀態
Source Type     未來來源類型
Source Number   未來來源單號
```

草稿狀態可編輯：

```txt
Date
Memo
Lines
```

posted / voided 狀態不可編輯。

### 6.6 Journal Lines table

分錄 lines 應在同一張傳票內操作。

欄位：

```txt
Account      科目
Description  說明
Debit        借方
Credit       貸方
```

未來可加：

```txt
Partner / Customer
Vehicle
Cost Type
Tax
Branch
Department
```

短期先不要加太多維度，避免 scope 擴大。

### 6.7 Lines 操作規則

Draft 狀態：

```txt
可新增 line
可移除 line
可修改 account / description / debit / credit
```

Posted 狀態：

```txt
不可新增 line
不可移除 line
不可修改 line
只能 void 或查看
```

Voided 狀態：

```txt
不可新增 line
不可移除 line
不可修改 line
只能查看
```

### 6.8 借貸驗證

保存或過帳時必須驗證：

```txt
至少兩行分錄
每行必須有 account
每行 debit / credit 不可同時大於 0
每行 debit / credit 不可同時為 0
debit total 必須等於 credit total
difference 必須為 0
```

Footer 應清楚顯示：

```txt
Debit Total
Credit Total
Difference
```

當 difference 不為 0 時：

```txt
Post button disabled
顯示明確錯誤提示
```

### 6.9 Post 行為

Post 是正式入帳動作。

過帳後：

```txt
status = posted
posted_at 記錄時間
posted_by 記錄使用者
journal number 固定
header / lines 不可修改
寫入 audit event
```

### 6.10 Void 行為

Void 是撤銷 / 作廢正式傳票。

作廢時應要求：

```txt
void_reason
```

作廢後：

```txt
status = voided
voided_at 記錄時間
voided_by 記錄使用者
void_reason 保留
不可再修改
寫入 audit event
```

短期可先保留既有 void 行為。如果目前已經有 void reason，就沿用；如果沒有，列為後續小步補強。

---

## 7. 未來與業務單據的銜接方向

核心原則：

```txt
業務單據優先
會計分錄在背後
手動傳票是例外 / 進階功能
```

### 7.1 Vehicle Sale

未來 Vehicle Sale 不應要求使用者手動打收入傳票。

合理方向：

```txt
Confirm Delivery / Complete Transaction
→ 產生 accounting event
→ 可由會計人員確認 / 過帳
```

### 7.2 Receivables / Payments

收款不是收入認列。

收款比較像：

```txt
收到現金 / 銀行款項
→ 沖應收
```

短期 Payments 模組保持業務收款紀錄即可。

未來可接：

```txt
payment registered
payment confirmed
payment reconciled
```

但目前不要做完整 bank reconciliation。

### 7.3 Vehicle Costs

成本類型應逐步接會計分類。

目前初步分類：

```txt
資本化到車輛成本：
- purchase_price
- repair
- detailing
- inspection

偏資本化，但可能有例外：
- transport

期間費用：
- management

需要人工判斷：
- tax
- other
```

未來可加：

```txt
capitalization_policy
accounting_treatment
expense_account_id
inventory_cost_account_id
requires_manual_review
```

短期不要一次補完整會計自動化。

### 7.4 Confirm Delivery / Complete Transaction

未來可能需要新增流程節點：

```txt
Confirm Delivery
或
Complete Transaction
```

此節點才是收入與銷貨成本認列候選點。

可能動作：

```txt
Vehicle Sale status becomes completed
Vehicle lifecycle remains sold
Revenue recognized
COGS recognized
Accounting event created
Audit event created
```

目前只定義方向，不實作。

---

## 8. 目前明確不做的功能

短期不要做：

```txt
AR / AP 完整模組
Cash / Bank
Invoice
Reports
Dashboard
Bank Reconciliation
Automatic Journals
Automatic Revenue Recognition
Automatic COGS Recognition
Full Tax Engine
Full Profit & Loss
Balance Sheet
Whole ERP UI Rewrite
```

原因：

```txt
目前目標是先把業務流程、文件狀態、會計 UI 操作模型定穩。
完整會計自動化需要更多業務節點與資料模型支撐，不應現在一次大改。
```

---

## 9. 建議實作順序

### Phase A：文件與 UI 規格

```txt
只新增 / 維護 docs/odoo-style-accounting-ui-spec.md
不改程式碼
```

### Phase B：會計傳票 UI 小改

```txt
改 Journal list / form 的 UI 結構
加入 status bar
調整 actions 位置
強化 footer totals / difference 顯示
不改資料模型或只做極小修改
```

### Phase C：會計科目 UI 小改

```txt
把 Accounts 做成設定頁
強化 filters
active / inactive 顯示更明確
reconcile flag 如果已有欄位就顯示，沒有就先不做
```

### Phase D：成本分類規格

```txt
先文件化 cost_type → accounting treatment
再決定是否補欄位
```

### Phase E：交車完成規格

```txt
定義 Confirm Delivery / Complete Transaction
定義它與 mark sold、receivables、revenue recognition 的關係
先文件化，不急著實作
```

---

## 10. Roo Code 工作限制

之後交給 Roo Code 時，請遵守：

```txt
不要讀 Odoo repo。
不要讀大型外部參考專案。
不要一次重構整個 accounting。
不要改 routes / policies / migrations，除非提示詞明確要求。
不要把 accounting-accounts / accounting-journals 合併回單一 accounting module。
不要讓 module.accounting.view 單獨授權進入 accounting accounts 或 journals。
```

每次修改都應該小步進行：

```txt
一次只處理一個頁面或一個 UI 區塊。
每次修改後跑 npm run build。
如果改後端或權限，再跑相關 Feature tests。
```

---

## 11. 本文件的完成標準

這份文件完成後，代表：

```txt
Odoo-style accounting UI 的方向已定義。
Accounts / Journals 的頁面定位已定義。
Status bar / actions / lines / totals 的操作模式已定義。
短期不做功能已明確排除。
未來擴展到 payments / costs / sales / delivery 的方向已保留。
```

此文件本身不要求任何程式碼修改。
