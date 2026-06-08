# ERP SaaS Platform

企業內部營運與管理系統（ERP SaaS Platform）。

本專案以 Laravel、Inertia.js、React、TailwindCSS、MySQL 與 Laravel Sail 建構，目標是建立一套可模組化擴充的中古車業 ERP / SaaS 後台系統。

系統核心設計重點：

- Module Registry：以資料庫集中管理模組 metadata
- RBAC：以 Spatie Permission 建立角色與權限系統
- Tenant Scope：以 company / branch 作為多租戶資料邊界
- Policy / Middleware：後端作為授權與資料存取唯一來源
- Audit Logs：記錄登入、操作與重要業務事件
- Inertia + React：提供單頁式後台體驗與現代化介面

---

# System Status

目前開發中（Early Development），Vehicle Sales + Receivables + Customer Transaction + Audit Display MVP completed；Vehicle Cost Management Phase 2 completed；Accounting Phase 1 / 2 / 3 completed；Accounting Journal Workbench UI Polish completed；Vehicle Cost Accounting Treatment Spec completed；Transaction Completion MVP completed through UI；Accounting Event Foundation Phase 1 completed；Accounting Event Phase 2 readonly workspace completed；Accounting Event Phase 3 completion integration completed；Accounting Event Phase 4A Review Workflow completed；Accounting Event Phase 4B Void Workflow completed；Accounting Event Phase 4C Account Mapping Spec completed；Accounting Event Phase 4C-2 Config-based Mapping Foundation completed。

目前已完成後台基礎架構、模組系統、權限地基、車輛管理 MVP、車輛價格、車輛成本、車輛成本管理 Phase 2 獨立入口與 create / edit 工作台、車輛銷售、銷售收款 / 應收、Receivables mark-sold action、Transaction Completion MVP through UI、客戶管理 MVP、客戶交易紀錄、Audit log display localization、Accounting Phase 1 Chart of Accounts、Accounting Phase 2 Journal Draft Foundation、Accounting Phase 3 Journal Posting / Voiding、Accounting Journal Workbench UI Polish、Vehicle Cost Accounting Treatment Spec、Accounting Event Foundation Phase 1、Accounting Event Phase 2 readonly workspace、Accounting Event Phase 3 completion integration、Accounting Event Phase 4A Review Workflow、Accounting Event Phase 4B Void Workflow、Accounting Event Phase 4C Account Mapping Spec、Accounting Event Phase 4C-2 Config-based Mapping Foundation、Sales / Payments / Delivery semantics UI hints、系統稽核紀錄與登入紀錄。

Transaction Completion remains non-recognition。完成交易目前會記錄交易完成狀態、建立一筆 pending Accounting Event、寫入 audit event，但不會自動產生 revenue / COGS / journal behavior。

Accounting is now split by functional module boundaries：會計科目與會計傳票已拆成 `accounting-accounts`、`accounting-journals` 兩個獨立 module entries；`module.accounting.view` 僅保留為相容 / 分類概念，不作為功能入口唯一安全依據。

目前穩定節點：

```txt
main / origin main
Vehicle Sales + Receivables + Customer Transaction + Audit Display MVP completed
Vehicle Cost Management Phase 2 completed
Accounting Phase 1 completed
Accounting Phase 2 completed
Accounting Phase 3 completed
Accounting Journal Workbench UI Polish completed
Vehicle Cost Accounting Treatment Spec completed
Transaction Completion MVP completed through UI
Accounting Event Foundation Phase 1 completed
Accounting Event Phase 2 readonly workspace completed
Accounting Event Phase 3 completion integration completed
Accounting Event Phase 4A Review Workflow completed
Accounting Event Phase 4B Void Workflow completed
Accounting Event Phase 4C Account Mapping Spec completed
Accounting Event Phase 4C-2 Config-based Mapping Foundation completed
Sales / Payments / Delivery semantics UI hints completed
```

最近分段驗證結果：

```bash
./vendor/bin/sail artisan test tests/Feature/CustomerTest.php tests/Feature/ReceivableTest.php
# 27 passed, 359 assertions

./vendor/bin/sail artisan test tests/Feature/VehicleSaleTest.php tests/Feature/VehicleSalePaymentTest.php
# 30 passed, 407 assertions

npm run build
# Vite build success
```

最新完整測試：曾通過 `./vendor/bin/sail artisan test`；exact count not updated。

---

# Completed Scope

## Frontend Foundation

- Welcome Landing Page
- Hidden Login Entry
- Responsive Dashboard Layout
- Sidebar Navigation
- Mobile Sidebar
- Hamburger Menu
- Dashboard KPI / Status / Quick Action UI
- Swiss / International style UI foundation
- Light / Dark mode compatible styling foundation

## Module Registry

系統模組由資料庫 `modules` table 管理，避免前後端重複定義模組資訊。

目前模組資料包含：

- key
- label
- section
- parent_id
- route_name
- base_permission
- permission_prefix
- icon_key
- sort_order
- active_patterns
- is_enabled
- is_active

支援能力：

- DB-based module registry
- Dynamic sidebar rendering
- Dynamic module visibility
- Module enable / disable
- Route access control
- Active route pattern matching

## RBAC / Permission Foundation

系統採用 Spatie Laravel Permission。

權限設計原則：

- Role = 主要身份或職務層級
- Permission = 可執行的具體能力
- Direct Permission = 特例覆蓋
- Backend = 權限與資料存取唯一來源
- Frontend visibility = 僅作 UX，不作安全依據

主要權限命名規則：

```txt
module.{module-key}.{action}
```

範例：

```txt
module.dashboard.view
module.vehicles.view
module.vehicles.create
module.vehicles.update
module.audit.view
module.vehicles.sales.payments.view
module.vehicles.sales.payments.create
module.vehicles.sales.payments.void
```

## Staff Permission Management

已完成員工權限管理基礎：

- Role / Permission 矩陣頁面
- 角色權限更新
- 使用者角色更新
- 使用者直接權限更新
- 角色 metadata 更新
- 角色建立 / 刪除基礎流程
- Staff Permission feature tests

預設角色模板重點：

- `admin`：保留完整 dashboard、車輛、價格、成本、銷售、收款、客戶、稽核、公司設定與 deprecated compatibility 權限。
- `sales`：可維護客戶與車輛、建立 / 查看銷售、查看收款狀態；不預設操作收款、成交確認、敏感個資、價格、成本或稽核。
- `accounting`：可查看客戶、車輛、銷售與收款，並可建立 / 作廢收款與執行 receivables mark-sold；不預設建立銷售、查看敏感個資、成本或稽核。
- `inventory`：可建立 / 更新車輛並查看成本；不預設接觸銷售、收款、客戶或稽核。
- `viewer`：僅保留 dashboard、車輛與客戶最小只讀，不預設查看銷售、收款、敏感個資或成本。

## Vehicle Foundation

已完成車輛管理基礎模組：

- Vehicle Index
- Vehicle Show
- Vehicle Create
- Vehicle Edit
- Vehicle Store / Update
- Search / Filter / Pagination
- Tenant-scoped query
- Policy authorization
- IDOR protection
- FormRequest validation
- Stock number auto-generation
- Lifecycle status whitelist
- Creator / Updater tracking
- Company / Branch relationship display
- Minimal vehicle detail payload
- Vehicle CRUD / access tests

## Vehicle Pricing / Costs / Sales

已完成 Vehicle Module MVP 延伸能力：

- Vehicle Pricing：`asking_price` / `floor_price` 依權限輸出與更新。
- Vehicle Costs：成本新增、更新、摘要與 audit logging。
- Vehicle Cost Management Phase 1：獨立入口 `/employee-system/vehicle-costs`，使用既有 `vehicle_costs`，提供 tenant scoped 成本列表、篩選、摘要與連回車輛；Vehicle Costs Index 預設顯示本月資料，並可切換上月、近 90 天、今年、全部或自訂期間。
- Vehicle Cost Management Phase 2：新增獨立 create / edit 工作台 `/employee-system/vehicle-costs/create` 與 `/employee-system/vehicle-costs/{vehicleCost}/edit`；mutation 仍沿用既有 `VehicleCostController` 的 `employee-system.vehicles.costs.store` / `employee-system.vehicles.costs.update`，不新增成本寫入路由。
- Vehicle Costs Summary 不是正式報表，只是目前期間與篩選條件下的查詢摘要。
- Vehicle Cost accounting treatment 已文件化於 `docs/vehicle-cost-accounting-treatment-spec.md`；只定義 `vehicle_costs.cost_type` 對應方向，不新增欄位、不自動分錄、不產生 COGS、不新增 profit / gross margin payload。
- Vehicle Sales：銷售新增、更新、active sale guard 與 lifecycle sync。
- Vehicle Sales customer linking foundation：可關聯 Customer 主檔並保留交易 snapshot，銷售 payload 不暴露 Customer sensitive 欄位。
- Vehicle Payment / Receivable Foundation：每筆銷售可記錄多筆收款，收款編號 `PAY-YYYYMM-0001` 依公司月份遞增，已作廢收款不計入已收金額。
- Complete Transaction / Confirm Delivery 已形成完整 MVP path。
- Receivables Show 提供完成交易 action。
- Vehicle Show / Edit 顯示唯讀交易完成狀態。
- 完成交易會寫 `vehicle_sale.transaction_completed` audit event。
- completion 不等於 accounting recognition。
- 收款狀態：`unpaid`、`partial`、`paid`、`overpaid`；超收先允許並於 UI 提示。
- Staff Permission matrix 支援 `vehicles.pricing`、`vehicles.costs`、`vehicles.sales` nested permissions。
- 後端 payload 必須依權限控制；前端隱藏不等於安全；無權限者不能取得價格、成本、銷售、佣金等敏感資料。
- 無 `module.vehicles.sales.payments.view` 時，不回傳 payment summary / payment records；無 `module.vehicles.sales.view` 時，即使有 payments.view 也不回傳 sales / payments payload。
- 車輛成本管理 Phase 2 不是完整會計；不做應付帳款、付款沖帳、成本報表、PDF / Excel 或 profit / gross margin payload。
- Receivables / Vehicle Sale UI 已補語意提示，避免把收款、mark sold、交車完成與會計認列混為同一件事；提示僅為 frontend UX，正式權限、狀態、tenant scope 與資料一致性仍由後端負責。

車輛庫存編號格式：

```txt
VH-YYYYMM-0001
```

目前 lifecycle status：

```txt
draft      草稿
in_stock   在庫
reserved   已保留
sold       已售出
archived   已封存
```

## Customer Management Foundation

已完成 Customer Module MVP：

- Customer CRUD foundation
- Customer number auto-generation：`CU-YYYYMM-0001`
- Tenant-scoped query：`company_id` / `branch_id`
- RBAC / Policy authorization
- Sensitive data permission isolation：`id_number` / `birthday` / `address`
- Search / status filter / pagination
- Customer audit events：`customer.created` / `customer.updated`
- Vehicle sale payment audit events：`vehicle_sale_payment.created` / `vehicle_sale_payment.voided`
- Staff Permission matrix 支援 `customers.sensitive` nested permissions
- Customer Show 支援「客戶交易紀錄」：只顯示 `vehicle_sales.customer_id = customers.id` 的關聯交易，不以 `customer_name` / `customer_phone` snapshot 模糊比對。
- 客戶交易紀錄受 tenant scope 與權限隔離：`module.vehicles.sales.view` 才回傳銷售資料，`module.receivables.view` 才回傳由 `ReceivableSummaryService` 計算的收款摘要。
- 此功能不做客戶總消費、報表、lifetime value、毛利 / 利潤、退款、發票、PDF 或 Excel。

## Accounting Phase 1

- Chart of Accounts（會計科目）
- Module Registry entry：`accounting-accounts`
- Module access gate：`module.access:accounting-accounts`
- Account types aligned with reference project：asset / liability / equity / revenue / cost / expense
- 獨立 Inertia 頁面：Index / Create / Edit
- Tenant scope：以 `company_id` / `branch_id` 作為資料邊界
- 後端權限：`module.accounting.accounts.view`、`module.accounting.accounts.create`、`module.accounting.accounts.update`
- `module.accounting.view` 僅保留相容 / 分類概念，不可單獨進入會計科目入口。
- Opening balance stored but not used as official balance yet
- Audit events：`accounting_account.created`、`accounting_account.updated`
- Chart of Accounts 已可作為 Journal Draft 科目來源
- No AR/AP/cash/invoice/report integration yet

## Accounting Phase 2

- Journal Draft Foundation
- Module Registry entry：`accounting-journals`
- Module access gate：`module.access:accounting-journals`
- 資料表：`accounting_journal_entries`
- 資料表：`accounting_journal_entry_lines`
- 資料表：`accounting_journal_number_sequences`
- JE 編號規則：`JE-YYYYMM-0001`
- 獨立 Inertia 頁面：Index / Create / Show / Edit
- 後端權限：`module.accounting.journals.view`、`module.accounting.journals.create`、`module.accounting.journals.update`
- Journal create 讀取 active account options 是傳票建立必要資料，不要求 `module.accounting.accounts.view`。
- 借貸平衡驗證：
  - 至少兩列
  - total debit = total credit
  - total debit > 0
  - 單列 debit / credit 不可同時大於 0
  - 單列 debit / credit 不可同時為 0
  - account 必須屬於同 company 且為 active account
- Audit events：`accounting_journal.created`、`accounting_journal.updated`
- 目前僅支援 draft 建立與編輯
- Journal posting / voiding completed。
- Posted / voided lock rules completed。
- No AR/AP/cash/invoice/report integration yet
- No automatic Receivables / Vehicle Costs integration yet
- No profit / gross margin payload added

## Accounting Module Boundaries

- Accounting is now split by functional module boundaries.
- 會計科目 module：`accounting-accounts`，route：`employee-system.accounting.accounts.index`，base permission：`module.accounting.accounts.view`。
- 會計傳票 module：`accounting-journals`，route：`employee-system.accounting.journal-entries.index`，base permission：`module.accounting.journals.view`。
- `module.accounting.view` 只保留為相容 / 分類概念，不作為功能入口唯一安全依據。
- Sidebar visibility 由後端 `visibleModules` 依 module base permission 輸出；可只顯示會計科目或只顯示會計傳票。
- 未來 `accounting-receivables`、`accounting-payables`、`accounting-cash`、`accounting-invoices`、`accounting-reports` 也應獨立成 module。

## Accounting Phase 3

- Journal posting completed。
- Journal voiding completed。
- Posted journals cannot be updated。
- Voided journals cannot be updated。
- Draft journals cannot be voided。
- Voided journals cannot be posted。
- No AR/AP/cash/invoice/report integration yet。
- No automatic journals from sales/costs yet。
- No profit / gross margin payload added。

## Accounting Journal Workbench UI Polish

- Accounting Journal UI 已往 workbench 型操作模式整理。
- 傳票列表、建立、編輯、明細、分錄表格、status bar、actions、totals / difference 顯示已完成 UI polish。
- 這是前端 UI polish，不改 journal backend、routes、policies、migrations。
- Journal posting / voiding 規則仍由後端控制；posted / voided journals 仍不可修改。

## Accounting Event Foundation Phase 1

- Accounting Event Foundation Phase 1 completed。
- 已完成 `accounting_events` table。
- 已完成 `app/Models/AccountingEvent.php`。
- 已完成 `config/accounting_events.php`。
- 已完成 `tests/Feature/AccountingEventTest.php`。

## Accounting Event Phase 2 Readonly Workspace

- Accounting Event Phase 2 readonly workspace completed。
- 已完成 `accounting-events` module registry entry。
- 已完成 `module.accounting.events.view`。
- 已完成 readonly Index / Show routes。
- 已完成 readonly Accounting Event workspace UI。
- 已完成 `tests/Feature/AccountingEventWorkspaceTest.php`。
- Accounting Event readonly workspace 目前已完成 index / show。
- No create。
- No convert。
- Void mutation route 已由 Phase 4B 完成。
- Review mutation route 已由 Phase 4A 完成。
- Completion → pending Accounting Event 已完成。
- Accounting Event → Journal Draft 尚未完成。
- Revenue Recognition 尚未完成。
- COGS Recognition 尚未完成。
- Profit / Gross Margin payload 尚未完成。
- AR / AP / Cash / Bank / Invoice / Reports 尚未完成。

## Accounting Event Phase 3 Completion Integration

- Accounting Event Phase 3 completion integration completed。
- 已完成 `app/Services/AccountingEventService.php`。
- successful Complete Transaction / Confirm Delivery 會建立一筆 pending Accounting Event。
- 已完成 `tests/Feature/AccountingEventCompletionIntegrationTest.php`。
- `source_type = vehicle_sale_completion`。
- `event_type = vehicle_sale_completed`。
- `status = pending`。
- `amount = sale.sale_price`。
- `currency = TWD`。
- `source_id = vehicle_sales.id`。
- `source_number = vehicle.stock_number`，fallback 為 `SALE-{sale.id}`。
- payload 是 backend-controlled safe allowlist。
- received_amount / receivable_status 沿用 `ReceivableSummaryService`。
- idempotency guard 防止同一 sale 重複建立 event。
- `VehicleSaleController::complete()` 在同一 DB transaction 內完成 sale update、Accounting Event creation、audit log。
- completion integration 目前只建立 `pending` accounting event。
- 不自動產生 journal draft。
- 不自動建立 journal lines。
- 不自動 post journal。
- 不自動認列 revenue。
- 不自動認列 COGS。
- 不新增 profit / gross margin payload。
- Accounting Event readonly workspace 已提供 index / show。
- No create。
- No convert。
- Review mutation route 已由 Phase 4A 完成。
- Void mutation route 已由 Phase 4B 完成。

## Accounting Event Phase 4A Review Workflow

- Accounting Event Phase 4A Review Workflow completed。
- 已完成 `accounting_events.reviewed_at` data field。
- 已完成 `module.accounting.events.review`。
- 已完成 review route：`PATCH /employee-system/accounting/events/{accountingEvent}/review`，route name：`employee-system.accounting.events.review`。
- 已完成 `ReviewAccountingEventRequest` review request deny-list。
- 已完成 `AccountingEventPolicy::review`。
- 已完成 `AccountingEventController::review`。
- 已完成 Accounting Event Show page review UI。
- 已完成 `tests/Feature/AccountingEventReviewTest.php`。
- only pending events can be reviewed。
- review only updates `status = reviewed`、`review_note`、`reviewed_by`、`reviewed_at`。
- review does not generate journal draft。
- review does not generate journal lines。
- review does not post journal。
- review does not recognize revenue。
- review does not recognize COGS。
- review does not add profit / gross margin payload。
- view-only / `module.accounting.view` / cross-tenant users cannot review。
- Accounting Event convert 尚未完成。
- Accounting Event void 已由 Phase 4B 完成。
- Accounting Event → Journal Draft 尚未完成。
- Journal Lines generation 尚未完成。
- Revenue Recognition 尚未完成。
- COGS Recognition 尚未完成。
- Profit / Gross Margin payload 尚未完成。
- AR / AP / Cash / Bank / Invoice / Reports 尚未完成。
- Refund / reversal 尚未完成。

## Accounting Event Phase 4B Void Workflow

- Accounting Event Phase 4B Void Workflow completed。
- 已完成 `module.accounting.events.void`。
- 已完成 void route：`PATCH /employee-system/accounting/events/{accountingEvent}/void`，route name：`employee-system.accounting.events.void`。
- 已完成 `VoidAccountingEventRequest` void request deny-list。
- 已完成 `AccountingEventPolicy::void`。
- 已完成 `AccountingEventController::void`。
- 已完成 Accounting Event Show page void UI。
- 已完成 `tests/Feature/AccountingEventVoidTest.php`。
- only pending / reviewed events can be voided。
- void only updates `status = voided`、`void_reason`、`voided_by`、`voided_at`。
- void does not clear `review_note`、`reviewed_by`、`reviewed_at`。
- void does not modify `converted_journal_entry_id`。
- converted events cannot be voided。
- already voided events cannot be voided again。
- void does not cancel journal draft。
- void does not reverse posted journal。
- void does not process refund / return。
- void does not generate journal draft。
- void does not generate journal lines。
- void does not post journal。
- void does not recognize revenue。
- void does not recognize COGS。
- void does not add profit / gross margin payload。
- view-only / review-only / `module.accounting.view` / cross-tenant users cannot void。
- Accounting Event convert 尚未完成。
- Accounting Event → Journal Draft 尚未完成。
- Journal Lines generation 尚未完成。
- Revenue Recognition 尚未完成。
- COGS Recognition 尚未完成。
- Profit / Gross Margin payload 尚未完成。
- AR / AP / Cash / Bank / Invoice / Reports 尚未完成。
- Refund / reversal 尚未完成。

## Accounting Event Phase 4C Account Mapping Spec

- Accounting Event Phase 4C Account Mapping Spec completed。
- 已完成 `docs/accounting-event-account-mapping-spec.md`。
- 此階段只定義 account mapping 與未來 convert 邊界。
- 不新增 mapping runtime。
- 不新增 route / controller / permission / UI。
- 不產生 journal draft / journal lines。

## Accounting Event Phase 4C-2 Config-based Mapping Foundation

- Accounting Event Phase 4C-2 Config-based Mapping Foundation completed。
- 已完成 `config/accounting_event_mappings.php`。
- 已完成 `tests/Feature/AccountingEventMappingConfigTest.php`。
- `vehicle_sale_completed` mapping metadata 已存在。
- required mapping keys：`accounts_receivable_account`、`sales_revenue_account`。
- optional mapping keys：`vehicle_inventory_account`、`cogs_account`、`tax_payable_account`、`overpayment_account`、`rounding_adjustment_account`。
- 已包含 account type compatibility metadata。
- 已包含 disabled journal line template metadata：`receivable_debit`、`sales_revenue_credit`、`cogs_debit`、`vehicle_inventory_credit`。
- 已包含 mapping non-goals metadata。
- Mapping foundation 目前只提供 config metadata。
- `enabled = false`。
- all template `enabled = false`。
- no runtime account IDs。
- no fixed account codes。
- no route。
- no permission。
- no controller。
- no UI。
- no convert behavior。
- no journal draft generation。
- no journal lines generation。
- no posting。
- no revenue recognition。
- no COGS recognition。
- no profit / gross margin payload。
- Accounting Event convert 尚未完成。
- Account Mapping UI 尚未完成。
- database-backed mapping 尚未完成。
- Accounting Event → Journal Draft 尚未完成。
- Journal Lines generation 尚未完成。
- Revenue Recognition 尚未完成。
- COGS Recognition 尚未完成。
- Profit / Gross Margin payload 尚未完成。
- Tax runtime 尚未完成。
- AR / AP / Cash / Bank / Invoice / Reports 尚未完成。
- Refund / reversal 尚未完成。

## Delivery / Accounting Specs

- Vehicle Cost Accounting Treatment Spec completed：`docs/vehicle-cost-accounting-treatment-spec.md` 已文件化成本類型與會計處理方向。
- Transaction Completion MVP completed through UI：完成交易目前已有 RBAC、data fields、backend action、payload、React UI 與 audit event。
- Receivables Show 是目前完成交易主要操作入口；Vehicle Show / Edit 只顯示唯讀交易完成狀態。
- Transaction Completion remains non-accounting。
- No automatic revenue recognition。
- No automatic COGS recognition。
- Accounting Event Foundation Phase 1、Phase 2 readonly workspace、Phase 3 completion integration、Phase 4A Review Workflow、Phase 4B Void Workflow、Phase 4C Account Mapping Spec 與 Phase 4C-2 Config-based Mapping Foundation 已存在。
- Completion → pending Accounting Event 已完成；pending → reviewed 已完成；pending / reviewed → voided 已完成；mapping config metadata 已完成但 disabled；No journal draft generation yet。
- No AR / AP / Cash / Bank / Invoice / Reports integration yet。

## Current Business Flow

目前實際流程仍是：

```txt
Customer → Vehicle Sale → Receivables / Payments → Mark Sold → Complete Transaction / Confirm Delivery → Customer Transaction History → Audit Logs
```

- Customer 主檔可被 Vehicle Sale 關聯，並保留交易當下 customer snapshot。
- Receivables / Payments 管理應收、已收、未收、收款狀態與收款紀錄。
- Mark Sold 動作銜接收款 / 應收流程與車輛售出狀態。
- Complete Transaction / Confirm Delivery 目前代表交易完成狀態記錄，並建立一筆 pending Accounting Event 與寫入 audit event。
- Complete Transaction / Confirm Delivery 不會自動產生 journal draft。
- Complete Transaction / Confirm Delivery 不會自動認列 revenue / COGS，也不計算 profit / gross margin。
- Customer Transaction History 顯示客戶關聯銷售與收款摘要。
- Audit Logs 顯示主要業務事件，並已完成顯示標籤在地化。

未來規格方向是：

```txt
Customer → Vehicle Sale → Receivables / Payments → Mark Sold → Complete Transaction / Confirm Delivery → Accounting Event / Journal Draft → Revenue / COGS Recognition
```

- Accounting Event Foundation Phase 1、Phase 2 readonly workspace、Phase 3 completion integration、Phase 4A Review Workflow、Phase 4B Void Workflow、Phase 4C Account Mapping Spec 與 Phase 4C-2 Config-based Mapping Foundation 已存在；`Accounting Event → Journal Draft → Revenue / COGS Recognition` 目前仍是 backlog。
- 收款完成只代表款項已記錄，mark sold 只代表銷售與車輛售出狀態銜接。
- 交車完成 / 完成交易目前已作為 completion 狀態節點；收入與 COGS 認列目前不會自動產生。

## Audit Foundation

已完成系統稽核基礎：

- Login logs
- Activity logs
- Successful login logging
- Failed login logging
- Inactive account login logging
- Logout logging
- Vehicle created logging
- Vehicle updated logging with old/new values
- Auth events separated from activity audit page
- Audit pages protected by module.audit.view
- Company scoped audit query
- Audit foundation tests

---

# Architecture Principles

## Tenant Boundary

目前以 `company_id` / `branch_id` 作為資料邊界。

業務資料查詢必須先套用 tenant scope，再進行授權與回傳。

核心原則：

- 不信任 URL id
- 不信任前端 state
- 不信任 hidden input
- 不允許前端覆寫 tenant key
- 跨 company / branch 資料應回傳 404 或 403
- 前端不需要的 raw FK 不應暴露

## Authorization Flow

一般流程：

```txt
Route Middleware
→ Controller / Policy
→ Tenant-scoped Query
→ Inertia Payload
```

原則：

- 模組入口由 `module.access:{module-key}` 控制
- Model-level 操作由 Policy 控制
- Controller 只回傳前端必要資料
- Sidebar 僅消費後端提供的 visible modules
- React component 不自行判斷安全權限

## Data Exposure

Inertia payload 必須保持最小化。

應避免暴露：

- 不必要的 raw FK
- 不必要的 internal IDs
- permission internals
- debug data
- stack traces
- secrets
- 前端未使用的敏感欄位

---

# Tech Stack

## Backend

- Laravel
- Laravel Sail
- MySQL
- Spatie Laravel Permission
- Inertia.js server adapter
- Pest / PHPUnit tests

## Frontend

- React
- Inertia.js
- TailwindCSS
- Vite

## Development Environment

- Docker / Laravel Sail
- Adminer
- Node / npm

---

# Development Commands

## Start Sail

```bash
./vendor/bin/sail up -d
```

## Stop Sail

```bash
./vendor/bin/sail down
```

## Start Vite

```bash
npm run dev
```

## Build Frontend

```bash
npm run build
```

## Run Tests

```bash
./vendor/bin/sail artisan test
```

## Fresh Migration + Seed

```bash
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan db:seed
```

## Reset Permission Cache

```bash
./vendor/bin/sail artisan permission:cache-reset
```

## Clear Laravel Cache

```bash
./vendor/bin/sail artisan optimize:clear
```

---

# Default Test Accounts

## Admin

```txt
admin@example.com
password
```

## Staff

```txt
staff@example.com
password
```

---

# Git Workflow

目前以小步提交為主。

建議流程：

```bash
git status
git add <changed-files>
git commit -m "type: concise message"
git push
```

常用 commit 類型：

```txt
feat: 新功能
fix: 修 bug
polish: 小型 UI / 資料輸出 / 體驗收尾
test: 測試補強
refactor: 不改行為的重構
```

---

# Current Development Direction

短期優先事項：

1. 維持 Vehicle Sales + Receivables + Customer Transaction + Audit Display MVP 穩定。
2. 維持 RBAC / tenant scope / audit foundation 穩定。
3. Accounting Event Foundation Phase 1、Phase 2 readonly workspace、Phase 3 completion integration、Phase 4A Review Workflow、Phase 4B Void Workflow、Phase 4C Account Mapping Spec 與 Phase 4C-2 Config-based Mapping Foundation 已完成；Journal Draft generation、Revenue Recognition、COGS Recognition 仍待後續小步實作。
4. 後續再選擇租賃 / 合約 / 完整 CRM / 報表 / 圖片等模組。
5. 完整資安 hardening 待核心 workflows 更完整後再做。

暫緩事項：

- Leasing module
- Refund / return / reversal flow
- Full accounting
- Accounting Event completion runtime integration completed
- Accounting Event review completed
- Accounting Event void completed
- Accounting Event account mapping spec completed
- Accounting Event config-based mapping foundation completed
- Accounting Event convert pending
- Account Mapping UI pending
- Database-backed mapping pending
- Journal Draft generation pending
- Journal Lines generation pending
- Automatic revenue recognition is pending
- Automatic COGS recognition is pending
- Profit / gross margin payload is still excluded
- AR / AP / Cash / Bank / Invoice / Reports are still deferred
- Full security hardening still deferred until core workflows are more complete
- Invoice flow
- Image upload
- Reports / exports
- Global audit middleware
- Full security hardening review
- Production deployment hardening

以上事項待專案架構與核心功能更完整後再進行。

---

# Security Notes

目前已完成基礎防護：

- RBAC
- Module access middleware
- Policy authorization
- Tenant-scoped vehicle query
- IDOR protection
- FormRequest validation
- Login log separation
- Activity audit foundation

## Current Vehicle Payment Limitations

- 尚未做完整會計分錄。
- Accounting Event Foundation Phase 1、Phase 2 readonly workspace、Phase 3 completion integration、Phase 4A Review Workflow、Phase 4B Void Workflow、Phase 4C Account Mapping Spec 與 Phase 4C-2 Config-based Mapping Foundation 已存在；completion 已建立 pending Accounting Event，review 可標記 reviewed，void 可作廢 pending / reviewed event，mapping config metadata 已存在但 disabled，尚未實作 journal draft。
- 尚未自動 revenue recognition。
- 尚未自動 COGS recognition。
- 尚未做 AR / AP、Cash / Bank、Invoice、Reports。
- 尚未做退款流程。
- 尚未做發票。
- 尚未做報表 / export / PDF / Excel。
- 尚未新增 profit / gross_profit / gross_margin payload。
- Minimal vehicle detail payload
- 完整 security hardening 尚未執行。

完整資安審查與 production hardening 尚未執行，會在功能與架構更完整後集中處理。

---

# License

(c) 2026 OO INTERNATIONAL. All rights reserved.

## Receivables Module MVP completed

- 新增「收款管理」模組，Module key：`receivables`，入口：`/employee-system/receivables`。
- 新權限：`module.receivables.view`、`module.receivables.create`、`module.receivables.void`。
- 使用既有資料表：`vehicle_sales` 作為交易來源、`vehicle_sale_payments` 作為收款紀錄。
- 限制：不是完整會計；不做退款、發票、報表、PDF、Excel；不產生 profit / gross margin payload。
- Vehicle 頁面保留舊 `module.vehicles.sales.payments.*` 相容入口，但主要操作導向收款管理頁。
- `vehicle_sales.deposit_amount` 僅作「訂金快照」語意；真正已收金額只由 `vehicle_sale_payments.status = received` 計算。
