# 參考專案 Accounting Module 盤點規格

> 來源專案：`/home/z/Material/ERP-inventory-management-copy`  
> 本文件僅盤點參考專案會計邏輯與 UI 行為，供 Laravel + Inertia + React + MySQL 專案後續分 Phase 移植。  
> 本次不移植功能、不修改任何程式碼。

## 盤點範圍

已閱讀 / 盤點：

- `README.md` 會計模組說明
- `prisma/schema.prisma` accounting 相關 models
- `src/app/(app)/accounting/accounts/page.tsx`
- `src/app/(app)/accounting/accounts/client.tsx`
- `src/app/(app)/accounting/journals/page.tsx`
- `src/app/(app)/accounting/journals/client.tsx`
- `src/app/(app)/accounting/receivables/page.tsx`
- `src/app/(app)/accounting/payables/page.tsx`
- `src/app/(app)/accounting/cash/page.tsx`
- `src/app/(app)/accounting/invoices/page.tsx`
- `src/app/api/accounting/accounts/route.ts`
- `src/app/api/accounting/accounts/[id]/route.ts`
- `src/app/api/accounting/accounts/import/route.ts`
- `src/app/api/accounting/journals/route.ts`
- `src/app/api/accounting/journals/[id]/route.ts`
- `src/lib/permissions.ts`
- `src/lib/api.ts`

---

## 一、參考專案會計模組總覽

### accounts

- 對應 Chart of Accounts（會計科目）。
- 支援六大類：資產、負債、權益、收入、成本、費用。
- 支援科目編號、名稱、類型、期初餘額、啟用狀態。
- API 使用 `accounting.*` 權限。
- 以 `tenantId` 做資料隔離。
- 有 CSV 匯入與模板下載。

### journals

- 對應 Journal Entry（傳票）與 Journal Entry Lines（分錄）。
- 支援搜尋、日期篩選、分頁、CSV / Excel / PDF 匯出、列印。
- 新增 / 編輯時檢查借貸平衡且借方總額不可為 0。
- 狀態：`DRAFT`、`SUBMITTED`、`APPROVED`、`POSTED`、`VOIDED`、`REJECTED`。
- 傳票編號透過 `nextNumber("JE", tenantId)` 產生。
- API 使用 `journals.*` 權限。

### receivables

- README 說明：由銷售單自動產生，支援部分沖帳與逾期追蹤。
- Prisma model：`AccountsReceivable`、`ReceivePayment`。
- UI PageShell：`應收帳款`，description 為「由銷售單自動產生；支援部分沖帳與逾期追蹤」。
- 本次只盤點，不納入 Phase 1 / Phase 2。

### payables

- README 說明：由採購單自動產生，支援部分沖帳與逾期追蹤。
- Prisma model：`AccountsPayable`、`SupplierPayment`。
- UI PageShell：`應付帳款`，description 為「由採購單自動產生；支援部分沖帳與逾期追蹤」。
- 本次只盤點，不納入 Phase 1 / Phase 2。

### cash

- README 說明：現金帳戶、銀行帳戶、轉帳、對帳。
- Prisma model：`CashAccount`、`BankAccount`、`CashTransaction`、`BankTransaction`。
- UI PageShell：`現金銀行`，description 為「現金帳戶與銀行帳戶、轉帳與對帳」。
- 本次只盤點，不納入 Phase 1 / Phase 2。

### invoices

- README 說明：銷項 / 進項發票、稅額與單據關聯。
- Prisma model：`Invoice`、`InvoiceItem`、`InvoiceTrack`、`TaxRate`。
- UI PageShell：`發票管理`，description 為「銷項 / 進項發票。可手動新增，或從銷售 / 採購單一鍵開立。」
- 本次只盤點，不納入 Phase 1 / Phase 2。

### reports

- README 說明：損益表、資產負債表、試算表、營運摘要。
- 另有列印路徑如 trial balance / income statement / balance sheet。
- 本次只盤點，不納入 Accounting Foundation 初期移植。

---

## 二、會計科目 Accounts 規格

### UI 頁面標題

- PageShell title：`會計科目`
- PageShell description：`維護科目表、科目類型與期初餘額`

### 欄位

`ChartOfAccount`：

- `id`
- `tenantId`
- `code`
- `name`
- `type`
- `parentId`
- `openingBalance`
- `isActive`
- `updatedBy`
- `createdAt`
- `updatedAt`

### 表格欄位

參考 UI 顯示欄位：

- 編號：`code`，mono 字體
- 名稱：`name`
- 類型：`type`，使用 badge
- 期初餘額：`openingBalance`，money format
- 狀態：`isActive`，啟用 / 停用 badge
- 操作人員：`updatedBy`

### 表單欄位

新增 / 編輯 dialog：

- 科目編號 `code`，必填
- 科目名稱 `name`，必填
- 科目類型 `type`
  - `ASSET`：資產
  - `LIABILITY`：負債
  - `EQUITY`：權益
  - `REVENUE`：收入
  - `COST`：成本
  - `EXPENSE`：費用
- 期初餘額 `openingBalance`
- 啟用 `isActive`

### 權限

- 頁面檢查：`accounting.view`
- GET：`accounting.view`
- POST：`accounting.create`
- PUT：`accounting.edit`
- DELETE：`accounting.delete`
- Import：`accounting.create`

### API endpoint

- `GET /api/accounting/accounts`
- `POST /api/accounting/accounts`
- `POST /api/accounting/accounts?upsert=1`
- `PUT /api/accounting/accounts/{id}`
- `DELETE /api/accounting/accounts/{id}`
- `POST /api/accounting/accounts/import`

### tenant scope

- 所有查詢與寫入都依 `requireTenantId()` 取得 `tenantId`。
- `ChartOfAccount` unique key：`tenantId + code`。
- 查詢條件固定包含 `tenantId`。

### 驗證規則

參考專案實作較薄，未使用 Zod / FormRequest 等集中驗證。實際規則可從 UI / DB / import 推導：

- `code` 必填。
- `name` 必填。
- `type` 必須為六大類之一。
- `openingBalance` 轉為 number，預設 0。
- `tenantId + code` 唯一。
- 匯入時：
  - 支援 header：`代碼 / 編號 / code / 名稱 / name / 類型 / type`。
  - 每列至少需有 code 與 name。
  - type 可用中文或英文。
  - type 缺漏時依 code 前綴推測：1 資產、2 負債、3 權益、4 收入、5 成本、6/7 費用。

### 匯入 / 匯出功能是否存在

- 匯入：存在。
  - UI：下載 CSV 範本、匯入 CSV。
  - API：`POST /api/accounting/accounts/import`。
- 匯出：存在於通用 `CrudTable`，並設定：
  - `exportName="accounts"`
  - `pdfTitle="會計科目"`
  - `templateHeaders=["編號", "名稱", "類型", "期初餘額"]`

### 本專案 Phase 1 應該移植哪些

- 建立 Laravel 資料表：`accounting_accounts`。
- 移植六大科目類型與中文 label。
- 移植 `tenant/company scope` 或本專案既有租戶邊界。
- 移植基本 CRUD：列表、建立、編輯、停用。
- 移植科目編號唯一限制。
- 移植 Accounts 頁面標題 / description / 類型 badge / 期初餘額顯示。
- 移植 CSV 匯入規則可作為可選子階段；若先求穩，先保留為 Phase 1b。

### 本專案 Phase 1 不應該移植哪些

- 不直接複製 Next.js / Prisma route code。
- 不使用 Prisma model 或 cuid ID 假設。
- 不在 Phase 1 串接銷售、採購、應收、應付、車輛成本。
- 不先做父子科目 UI，除非本專案已確認需要科目階層維護。
- 不先做刪除已被傳票使用的科目；Laravel 應以外鍵與軟停用優先。

---

## 三、傳票 Journals 規格

### UI 頁面標題

- PageShell title：`傳票管理`
- PageShell description：`建立、審核與作廢會計傳票，借貸必平衡`

### 搜尋 / 日期篩選

列表上方提供：

- 搜尋欄 placeholder：`搜尋傳票編號 / 摘要`
- 起始日期 `from`
- 結束日期 `to`
- 分頁：`page`、`pageSize`

API 查詢：

- q 搜尋 `number` / `summary`
- from / to 篩選 `entryDate`
- 預設依 `entryDate desc` 排序

### 表格欄位

- 編號 `number`
- 日期 `entryDate`
- 摘要 `summary`
- 借方合計：由 lines debit 加總
- 貸方合計：由 lines credit 加總
- 狀態 `status`
- 操作人員 `updatedBy`
- 操作：查看、編輯、刪除

### 傳票新增表單

- 傳票日期 `entryDate`
- 摘要 `summary`
- 分錄 lines，預設兩列
- 儲存按鈕在借貸不平衡時 disabled

### 傳票分錄欄位

`JournalEntryLine`：

- `accountId`
- `debit`
- `credit`
- `memo`

UI 行為：

- 科目下拉選單顯示 `{code} {name}`。
- 借方輸入後自動將同列 credit 設為 0。
- 貸方輸入後自動將同列 debit 設為 0。
- 可動態新增 / 移除分錄。
- 顯示借方合計、貸方合計、平衡狀態。

### 借貸平衡檢查

前端：

- `Math.abs(totalDebit - totalCredit) < 0.001`
- `totalDebit > 0`
- 若不平衡：toast `借貸必須平衡且金額不可為 0`

後端：

- POST：若 debit / credit 差額大於 0.001，拒絕。
- POST：totalDebit 等於 0，拒絕。
- PUT：同樣檢查 lines、平衡、金額不可為 0。

### 狀態流轉

Prisma enum：

- `DRAFT`
- `SUBMITTED`
- `APPROVED`
- `POSTED`
- `VOIDED`
- `REJECTED`

UI 操作：

- `DRAFT`：可修改、送出、刪除。
- `SUBMITTED`：可審核、駁回。
- `APPROVED`：可過帳。
- `POSTED`：可作廢。

API PATCH action：

- `submit` → `SUBMITTED`
- `approve` → `APPROVED`
- `reject` → `REJECTED`
- `post` → `POSTED`
- `void` → `VOIDED`
- `update-header` → 更新 summary / entryDate

### 過帳 / 作廢規則

參考專案目前僅更新狀態，未看到實際總帳餘額落帳 table：

- `post` 需要 `journals.post`，狀態改為 `POSTED`。
- `void` 需要 `journals.void`，狀態改為 `VOIDED`。
- README 說「已過帳不可修改」，但 route 目前 PUT 未明確阻擋 POSTED 編輯；本專案移植時應補強。
- 刪除 route 含大量對應收 / 應付 / 票據 / 採購 / 銷售關聯刪除邏輯；此段不適合初期移植，且應避免以 summary 文字反查來源單據。

### API endpoint

- `GET /api/accounting/journals`
- `POST /api/accounting/journals`
- `GET /api/accounting/journals/{id}`
- `PUT /api/accounting/journals/{id}`
- `PATCH /api/accounting/journals/{id}`
- `DELETE /api/accounting/journals/{id}`
- 額外存在但本次不建議移植：`POST /api/accounting/journals/from-source`

### tenant scope

- 所有 journal 查詢與寫入皆使用 `requireTenantId()`。
- `JournalEntry` unique key：`tenantId + number`。
- 查詢條件固定包含 `tenantId`。
- `JournalEntryLine` 透過 entry 關聯間接屬於 tenant；Laravel 建議在 Service / Policy 中同時檢查 entry tenant 與 account tenant，避免跨租戶 accountId 注入。

### 權限

- 頁面：`journals.view`
- GET list / detail：`journals.view`
- POST：`journals.create`
- PUT：`journals.edit`
- PATCH 基本入口：`journals.edit`
- PATCH action 額外檢查：
  - submit：`journals.submit`
  - approve：`journals.approve`
  - reject：`journals.reject`
  - post：`journals.post`
  - void：`journals.void`
- DELETE：`journals.delete`

### 編號規則

`src/lib/api.ts`：

- 由 `nextNumber("JE", tenantId)` 產生。
- `NumberSequence` 以 `tenantId + key` 唯一。
- JE 預設 format：`{roc}{mm}{dd}{seq:0000}`。
- 範例：民國年 3 碼 + 月日 + 4 碼流水號，如 `11501030001`。
- 非 JE 預設 format：`{prefix}{yyyy}{mm}-{seq:0000}`。

### audit 行為

- `POST /journals`：audit action `create`，module `journals`。
- `PUT /journals/{id}`：audit action `edit`，module `journals`。
- `PATCH /journals/{id}`：audit action 使用 action 值，例如 submit / approve / post / void。
- `DELETE /journals/{id}`：audit action `delete`，module `journals`。
- audit 失敗只寫 console，不阻斷主流程。

### 本專案 Phase 2 應該移植哪些

- 建立 `accounting_journal_entries` 與 `accounting_journal_entry_lines`。
- 建立 Journal Service，集中處理建立、更新、送審、核准、過帳、作廢。
- 建立 FormRequest，驗證 header 與 lines。
- 建立借貸平衡檢查，前後端都檢查，後端為唯一可信。
- 建立 tenant/company scope，所有 accountId 必須屬於同一 tenant。
- 建立 `DRAFT → SUBMITTED → APPROVED → POSTED → VOIDED / REJECTED` 狀態規則。
- 建立 JE 序號服務，沿用本專案既有序號服務風格。
- 建立 audit log：create / edit / submit / approve / reject / post / void / delete。
- UI 保留搜尋、日期篩選、動態分錄、合計差額、狀態 badge。

### 本專案 Phase 2 不應該移植哪些

- 不移植 `from-source` 自動由進銷存轉傳票。
- 不自動串接 Vehicle Sales / Receivables / Vehicle Costs。
- 不移植刪除傳票時連帶刪除銷售單、採購單、應收、應付、票據的邏輯。
- 不用 summary 文字反查來源單據。
- 不先做結帳 `closing`、報表、發票、現金銀行、票據。
- 不允許已過帳傳票被一般 PUT 修改；本專案需比參考專案更嚴格。

---

## 四、資料表對照

### ChartOfAccount → `accounting_accounts`

Laravel migration 建議：

- `id`
- tenant boundary：依本專案慣例，例如 `company_id` / `tenant_id`
- `code` string
- `name` string
- `type` enum-like string：`asset`、`liability`、`equity`、`revenue`、`cost`、`expense`
- `parent_id` nullable foreign id self reference（Phase 1 可先保留欄位、不做 UI）
- `opening_balance` decimal(18, 2) default 0
- `is_active` boolean default true
- `created_by` nullable
- `updated_by` nullable
- timestamps
- unique：`tenant_id + code`
- indexes：tenant、type、is_active、parent_id

### JournalEntry → `accounting_journal_entries`

Laravel migration 建議：

- `id`
- tenant boundary：`company_id` / `tenant_id`
- `number` string
- `entry_date` date
- `summary` string/text
- `status` string default `draft`
- `attachment` nullable string（Phase 2 可先不實作上傳）
- `created_by_id` nullable foreign id users
- `posted_at` nullable datetime（建議新增，參考專案沒有）
- `voided_at` nullable datetime（建議新增，參考專案沒有）
- `created_by` / `updated_by` nullable display/audit 欄位（依本專案慣例）
- timestamps
- unique：`tenant_id + number`
- indexes：tenant、entry_date、status、created_at

### JournalEntryLine → `accounting_journal_entry_lines`

Laravel migration 建議：

- `id`
- `journal_entry_id` foreign id cascade/restrict 依狀態規則
- `account_id` foreign id to `accounting_accounts`
- `debit` decimal(18, 2) default 0
- `credit` decimal(18, 2) default 0
- `memo` nullable string/text
- timestamps（可選）
- indexes：journal_entry_id、account_id
- CHECK 建議：debit >= 0、credit >= 0、不得同列借貸皆大於 0（MySQL 8 可支援 CHECK，但仍需後端驗證）

### NumberSequence → `accounting_journal_number_sequences` 或沿用本專案序號服務風格

參考欄位：

- `tenantId`
- `key`
- `prefix`
- `nextNo`
- `format`
- `updatedAt`

本專案建議：

- 若已有序號服務，新增 accounting journal key 即可。
- 若需新表，可命名 `accounting_journal_number_sequences`：
  - tenant boundary
  - `key` default `JE`
  - `prefix` nullable
  - `next_number` unsigned integer default 1
  - `format` nullable
  - timestamps
  - unique：tenant + key
- JE 預設格式可採民國年 + 月日 + 流水號，但需確認本專案既有車輛付款序號風格後再定案。

---

## 五、UI 對照

### PageShell 標題與 description

保留：

- Accounts：`會計科目` / `維護科目表、科目類型與期初餘額`
- Journals：`傳票管理` / `建立、審核與作廢會計傳票，借貸必平衡`

### Account 類型 badge

保留六大類 badge：

- 資產
- 負債
- 權益
- 收入
- 成本
- 費用

本專案可依 Tailwind / Swiss dashboard 設計重新定義顏色，但需保持低飽和、功能性、易辨識。

### Account 新增 / 編輯 dialog 或本專案應改成獨立頁

參考專案使用 dialog。  
本專案可採：

- Phase 1：若現有 CRUD 多為獨立頁，建議使用獨立 create/edit 頁以符合 Laravel + Inertia 表單與驗證錯誤呈現。
- 若現有後台已有穩定 Modal pattern，才採 dialog。

### Journal 搜尋欄

保留：

- placeholder：`搜尋傳票編號 / 摘要`
- 查詢 number / summary

### Journal 日期篩選

保留：

- from date
- to date
- 對應 `entry_date`

### Journal lines 動態新增 / 移除

保留：

- 預設兩列。
- 可新增分錄。
- 可移除分錄。
- 借方輸入時清空貸方；貸方輸入時清空借方。

### total debit / total credit / difference

參考專案顯示：

- 借方合計
- 貸方合計
- 狀態：已平衡 / 未平衡

本專案建議加上：

- 差額 `difference = total_debit - total_credit`
- 未平衡時用高對比但克制的警示色。

---

## 六、重要限制

- 不直接複製 Next.js / Prisma 程式碼。
- 只移植會計邏輯與 UI 行為。
- 本專案使用 Laravel / Inertia / React / MySQL。
- 不做 receivables / payables / cash / invoices / reports，除非進入後續 phase。
- 不自動串接 Vehicle Sales / Receivables / Vehicle Costs。
- Backend 必須是權限、資料範圍與狀態流轉的唯一可信來源。
- Frontend visibility 只能當 UX，不可當安全控制。
- 不使用 `$request->all()`；後續實作需用 FormRequest 與 validated allowlist。
- 財務資料操作需 audit log。
- 已過帳傳票不可修改；若要作廢，應走明確作廢流程並留稽核紀錄。

---

## 建議 Laravel 專案 Phase 拆分

### Phase 1：Accounting Accounts Foundation

- `accounting_accounts` migration / model / policy / request / service。
- Accounts CRUD。
- 科目類型 enum-like validation。
- tenant/company scope。
- 權限：view / create / update / delete 或 deactivate。
- audit：create / update / deactivate / import。
- UI：Accounts index + create/edit。

### Phase 1b：Accounts Import / Export

- CSV 匯入範本。
- 匯入時 upsert by tenant + code。
- 匯入錯誤列回報。
- 匯出 CSV / Excel 視本專案既有工具決定。

### Phase 2：Journal Entries Foundation

- `accounting_journal_entries`。
- `accounting_journal_entry_lines`。
- JournalNumberService 或整合既有序號服務。
- JournalEntryService：create / update draft / submit / approve / reject / post / void。
- 借貸平衡檢查。
- 已過帳不可修改。
- tenant scope + account scope 防 IDOR。
- audit log。
- UI：列表、搜尋、日期篩選、新增、編輯、查看、狀態操作。

### Phase 3：Reporting Foundation（後續）

- Trial balance。
- General ledger。
- Income statement / balance sheet。
- 僅基於已過帳且未作廢傳票。

### Phase 4：AR / AP / Cash / Invoice Integration（後續）

- 應收 / 應付。
- 收款 / 付款。
- 現金銀行。
- 發票與稅務。
- 與 Vehicle Sales / Vehicle Costs 的自動傳票整合需另開規格，不在 Accounting Foundation 初期自動串接。
