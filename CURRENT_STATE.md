# 目前專案狀態

## 狀態摘要

- 專案狀態：Early Development，Vehicle Module 第一代 MVP 已完成，Customer Module MVP foundation 已加入。
- 穩定節點：Customer Transaction History MVP 已完成，Vehicle Payment / Receivable Foundation MVP 已完成。
- 最新驗證狀態：最近分段驗證包含 `CustomerTest + ReceivableTest：27 passed / 359 assertions`、`VehicleSaleTest + VehicleSalePaymentTest：30 passed / 407 assertions`、`npm run build` 通過；最新完整測試待重新執行 full test。
- 本文件為 Vehicle Module MVP Final Review 封版整理；目前不實作新功能、不做完整會計、不做報表、不做 PDF / Excel、不做圖片上傳、不新增 profit / gross margin / 毛利 payload。

## 技術棧

- Laravel
- Inertia.js
- React
- TailwindCSS
- MySQL
- Laravel Sail
- Spatie Permission

## 已完成模組

- Dashboard foundation
- RBAC / Module Registry
- Staff Permission matrix
- Audit foundation
- Login logs
- Company settings foundation
- Vehicle foundation
- Vehicle pricing
- Vehicle costs
- Vehicle sales
- Vehicle payment / receivable foundation
- Customer management foundation

## Customer Module MVP 能力

- Customer CRUD foundation：Index / Show / Create / Edit / Store / Update。
- `customer_number` 自動產生：格式為 `CU-YYYYMM-0001`，依 company 與月份遞增。
- Tenant scope：以 `company_id` / `branch_id` 作為客戶主檔資料邊界。
- RBAC / Policy：客戶一般 CRUD 與敏感個資由後端 Policy / FormRequest 控制。
- Sensitive data isolation：`id_number`、`birthday`、`address` 僅在 `module.customers.sensitive.view` 下輸出，建立 / 更新需 `module.customers.sensitive.update`。
- 基本搜尋篩選：`q` 搜尋 `name`、`phone`、`secondary_phone`、`customer_number`；`status` 篩選 `lead`、`active`、`archived`。
- Customer audit events：`customer.created`、`customer.updated`；audit snapshot 不記敏感個資與 tenant / actor 欄位。
- Customer Show 已支援「客戶交易紀錄」：僅顯示 `vehicle_sales.customer_id = customers.id` 的購車 / 銷售紀錄，不使用 `customer_name` / `customer_phone` snapshot 模糊歸戶。
- 客戶交易紀錄使用 customer / sale / vehicle / payment 的 company / branch tenant scope；跨 tenant sale 與 snapshot-only sale 不會顯示。
- 交易紀錄權限隔離：`module.vehicles.sales.view` 才回傳銷售摘要；`module.receivables.view` 才回傳應收 / 已收 / 未收與收款狀態，摘要計算沿用 `ReceivableSummaryService`。
- 限制：Customer Show 不做客戶總消費、報表、lifetime value、毛利 / 利潤、發票、退款、PDF 或 Excel。

## Vehicle Module MVP 能力

- Vehicle CRUD：Index / Show / Create / Edit / Store / Update。
- Tenant scope：以 `company_id` / `branch_id` 作為車輛、成本、銷售資料邊界。
- RBAC / Policy：車輛、價格、成本、銷售皆由後端權限與 Policy 控制。
- IDOR 防護：車輛、成本、銷售查詢皆先套用 tenant scope；跨 company / branch 以 404 優先阻斷。
- `stock_number` 自動產生：格式為 `VH-YYYYMM-0001`，依 company 與月份遞增。
- `lifecycle_status` 白名單：目前包含 `draft`、`in_stock`、`reserved`、`sold`、`archived`。
- Pricing / Costs / Sales 權限隔離：價格、成本、銷售 payload 依後端權限輸出，前端隱藏不作為安全依據。
- Sales lifecycle sync：銷售狀態會同步車輛 lifecycle。
- Active sale guard：每台車只允許一筆 active sale。
- Vehicle Sales customer linking：銷售可 nullable 關聯 `customer_id`，`customer_name` / `customer_phone` 保留為交易當下 snapshot。
- Vehicle Sales customer payload 僅輸出 `id`、`customer_number`、`name`、`phone` 基本顯示資訊，不暴露 Customer sensitive 欄位。
- Vehicle Payment / Receivable Foundation：每筆 Vehicle Sale 可記錄多筆收款，Show / Edit 顯示應收、已收、未收、收款狀態與收款紀錄；已作廢收款不計入已收金額，超收僅提示不阻擋。
- Audit events：車輛、成本、銷售與公司設定異動已記錄 operation audit。
- Show / Edit UI split：Show 偏只讀展示；Edit 承載可編輯流程與 mutation UI 所需選項。

## 車輛流程

- 主要流程：`in_stock` → `reserved` → `sold`。
- `reserved` 可取消並回到 `in_stock`。
- `sold` 不可任意退回 `reserved`。
- `sold vehicle` 不可建立新 sale。
- 每台車只允許一筆 active sale；active sale 目前包含 `draft`、`reserved`、`sold`，`cancelled` 不算 active。
- 已成交銷售取消不會自動把車輛回到 `in_stock`，避免 MVP 簡化流程誤導退車 / 退款 / 作廢邏輯。

## 車輛權限清單

### 一般車輛

- `module.vehicles.view`
- `module.vehicles.create`
- `module.vehicles.update`
- `module.vehicles.delete`
- `module.vehicles.export`

### 車輛價格

- `module.vehicles.pricing.view`
- `module.vehicles.pricing.update`

### 車輛成本

- `module.vehicles.costs.view`
- `module.vehicles.costs.create`
- `module.vehicles.costs.update`

### 車輛銷售

- `module.vehicles.sales.view`
- `module.vehicles.sales.create`
- `module.vehicles.sales.update`
- `module.vehicles.sales.payments.view`
- `module.vehicles.sales.payments.create`
- `module.vehicles.sales.payments.void`

### 權限狀態

- `admin` 預設取得車輛、價格、成本、銷售與銷售收款完整權限。
- `staff` / `viewer` 不預設取得價格、成本、銷售等敏感權限。
- `pricing` / `costs` / `sales` 為 `module.vehicles.*` 下的 nested permissions。
- Staff Permission matrix 已支援 `vehicles.pricing`、`vehicles.costs`、`vehicles.sales` 與 `vehicles.sales.payments` 權限分組。

## Audit Events

目前整理的 operation audit events：

- `vehicle.created`
- `vehicle.updated`
- `vehicle_cost.created`
- `vehicle_cost.updated`
- `vehicle_sale.created`
- `vehicle_sale.updated`
- `vehicle_sale_payment.created`
- `vehicle_sale_payment.voided`
- `company_settings.updated`
- `customer.created`
- `customer.updated`

Audit 資料原則：

- Vehicle audit 僅記錄主要業務欄位，避免將內部備註等潛在敏感內容寫入快照。
- Vehicle costs audit 只記錄白名單欄位，不記 `internal_notes`。
- Vehicle sales audit 只記錄白名單欄位，不記 tenant / actor / internal-only sensitive 欄位。
- Vehicle sale payments audit 只記錄收款白名單欄位，不記 tenant / actor / vehicle / 毛利欄位。
- Customer audit 只記錄一般白名單欄位，不記 `id_number`、`birthday`、`address`。
- Audit snapshot 不記 `company_id`、`branch_id`、`vehicle_id`、`created_by`、`updated_by` 等系統欄位。
- `auth.*` 不進一般 operation audit；登入成功、登入失敗、停用帳號登入、登出等事件保留在 Login logs。

## Vehicle Module Manual QA Checklist

1. 使用 `admin@example.com` / `password` 登入。
2. 確認 Sidebar 顯示車輛管理入口，且入口來自後端 module / permission 資料。
3. 進入 Vehicle Index，確認列表可正常載入。
4. 使用 stock number 搜尋車輛，確認只回傳符合資料。
5. 使用 VIN 搜尋車輛，確認 VIN 正規化後仍可查詢。
6. 使用 license plate 搜尋車輛，確認篩選結果正確。
7. 使用 brand / model 搜尋車輛，確認結果正確。
8. 使用 lifecycle status 篩選 `in_stock` / `reserved` / `sold`，確認篩選與 URL query 正常。
9. 建立車輛，確認 `stock_number` 自動產生且前端傳入 stock number 不會生效。
10. 建立車輛時確認 company / branch / created_by / updated_by 由後端決定。
11. 編輯一般車輛欄位，確認可更新且不改變 tenant 欄位。
12. 測試無 `module.vehicles.pricing.view` 時，Index / Show 不顯示 `asking_price` 與 `floor_price`。
13. 測試有 `module.vehicles.pricing.update` 時，可建立 / 更新價格欄位。
14. 測試無 `module.vehicles.costs.view` 時，Show / Edit 不回傳成本 payload。
15. 測試有 `module.vehicles.costs.create` 時，可新增成本並回到車輛 Show。
16. 測試有 `module.vehicles.costs.update` 時，可更新成本且產生 audit event。
17. 測試無 `module.vehicles.sales.view` 時，Show / Edit 不回傳銷售、成交價、客戶電話、佣金或毛利相關 payload。
18. 測試有 `module.vehicles.sales.create` 時，可建立 `reserved` sale，車輛 lifecycle 同步為 `reserved`。
19. 將 sale 更新為 `sold`，確認車輛 lifecycle 同步為 `sold`，且該車不可再建立新 sale。
20. 將非 sold 的 `reserved` sale 取消，確認車輛 lifecycle 回到 `in_stock`；確認 sold sale 不可改回 `reserved`。
21. 以無權限或跨 company / branch 使用者直接開 URL / mutation，確認回 403 或 404，且敏感 payload 不外洩。

## 已知限制

- 尚未做完整會計。
- 尚未做租賃模組。
- 客戶模組目前已提供 Vehicle Sales customer linking foundation；尚未串接合約或完整 CRM。
- 尚未做完整會計分錄、退款流程、發票、報表 / PDF / Excel。
- 尚未做毛利 payload（profit / gross_profit / gross_margin）。
- 尚未做收款流程。
- 尚未做圖片上傳。
- 尚未做報表。
- 尚未做 PDF / Excel。
- 尚未做完整 security hardening。
- 尚未做全站 audit middleware。
- 尚未做完整 profit / gross margin 計算，也未新增毛利 payload。

## 下一階段 Backlog（目前不實作，僅列為 backlog）

### A. 車輛模組後續 polish

- 車輛 Show / Edit 表單體驗細節整理。
- 成本與銷售區塊 UX 文案精修。
- 車輛狀態切換規則補強與人工操作警示。
- 車輛列表欄位密度與行動版呈現優化。

### B. 業務模組

- 租賃流程。
- 客戶資料。
- 收款 / 應收款。
- 退車 / 退款 / 作廢流程。

### C. 管理與報表

- 報表 dashboard。
- Export / PDF / Excel。
- 圖片上傳與車輛相簿。
- 更完整的管理者檢視與營運彙總。

### D. 系統安全與維護

- 完整 security hardening。
- 全站 audit middleware。
- 更完整的 destructive action audit。
- Production deployment hardening。
- 權限、tenant scope、敏感 payload 的 regression test 擴充。

## Receivables Module MVP completed

- 新增「收款管理」模組，Module key：`receivables`，入口：`/employee-system/receivables`。
- 新權限：`module.receivables.view`、`module.receivables.create`、`module.receivables.void`。
- 使用既有資料表：`vehicle_sales` 作為交易來源、`vehicle_sale_payments` 作為收款紀錄。
- 限制：不是完整會計；不做退款、發票、報表、PDF、Excel；不產生 profit / gross margin payload。
- Vehicle 頁面保留舊 `module.vehicles.sales.payments.*` 相容入口，但主要操作導向收款管理頁。
- `vehicle_sales.deposit_amount` 僅作「訂金快照」語意；真正已收金額只由 `vehicle_sale_payments.status = received` 計算。

