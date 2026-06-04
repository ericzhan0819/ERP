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

目前開發中（Early Development），Vehicle Sales + Receivables + Customer Transaction + Audit Display MVP completed；Vehicle Cost Management Phase 1 completed。

目前已完成後台基礎架構、模組系統、權限地基、車輛管理 MVP、車輛價格、車輛成本、車輛成本管理 Phase 1 獨立入口、車輛銷售、銷售收款 / 應收、Receivables mark-sold action、客戶管理 MVP、客戶交易紀錄、Audit log display localization、系統稽核紀錄與登入紀錄。

目前穩定節點：

```txt
main / origin main
Vehicle Sales + Receivables + Customer Transaction + Audit Display MVP completed
Vehicle Cost Management Phase 1 completed
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

最新完整測試：待重新執行 full test。

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
- Vehicle Cost Management Phase 1：獨立入口 `/employee-system/vehicle-costs`，使用既有 `vehicle_costs`，提供 tenant scoped 成本列表、篩選、摘要與連回車輛。
- Vehicle Sales：銷售新增、更新、active sale guard 與 lifecycle sync。
- Vehicle Sales customer linking foundation：可關聯 Customer 主檔並保留交易 snapshot，銷售 payload 不暴露 Customer sensitive 欄位。
- Vehicle Payment / Receivable Foundation：每筆銷售可記錄多筆收款，收款編號 `PAY-YYYYMM-0001` 依公司月份遞增，已作廢收款不計入已收金額。
- 收款狀態：`unpaid`、`partial`、`paid`、`overpaid`；超收先允許並於 UI 提示。
- Staff Permission matrix 支援 `vehicles.pricing`、`vehicles.costs`、`vehicles.sales` nested permissions。
- 後端 payload 必須依權限控制；前端隱藏不等於安全；無權限者不能取得價格、成本、銷售、佣金等敏感資料。
- 無 `module.vehicles.sales.payments.view` 時，不回傳 payment summary / payment records；無 `module.vehicles.sales.view` 時，即使有 payments.view 也不回傳 sales / payments payload。
- 車輛成本管理 Phase 1 不是完整會計；不做應付帳款、付款沖帳、成本報表、PDF / Excel 或 profit / gross margin payload。

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

## Current Business Flow

```txt
Customer → Vehicle Sale → Receivables / Payments → Mark Sold → Customer Transaction History → Audit Logs
```

- Customer 主檔可被 Vehicle Sale 關聯，並保留交易當下 customer snapshot。
- Receivables / Payments 管理應收、已收、未收、收款狀態與收款紀錄。
- Mark Sold 動作銜接收款 / 應收流程與車輛售出狀態。
- Customer Transaction History 顯示客戶關聯銷售與收款摘要。
- Audit Logs 顯示主要業務事件，並已完成顯示標籤在地化。

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
3. 後續再選擇租賃 / 合約 / 完整 CRM / 報表 / 圖片等模組。
4. 完整資安 hardening 之後再做。

暫緩事項：

- Leasing module
- Refund / return / void flow
- Full accounting
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

