# 目前專案狀態

## 狀態摘要

- 專案狀態：Early Development，Vehicle Sales + Receivables + Customer Transaction + Audit Display MVP completed；Vehicle Cost Management Phase 2 completed；Accounting Phase 1 / 2 / 3 completed；Accounting Journal Workbench UI Polish completed；Vehicle Cost Accounting Treatment Spec completed；Transaction Completion MVP completed through UI；Accounting Event Foundation Phase 1 completed；Accounting Event Phase 2 readonly workspace completed；Accounting Event Phase 3 completion integration completed；Accounting Event Phase 4A Review Workflow completed；Accounting Event Phase 4B Void Workflow completed；Accounting Event Phase 4C Account Mapping Spec completed；Accounting Event Phase 4C-2 Config-based Mapping Foundation completed；Accounting Event Phase 4D-1 Convert Skeleton completed；Accounting Event Phase 4D-2 Journal Draft Generation Spec completed；Accounting Event Phase 4D-2A Convert Preflight Service completed；Accounting Event Phase 4D-2A-1 Runtime Mapping Decision Spec completed；Accounting Event Phase 4D-2A-2 Database-backed Mapping Foundation completed；Accounting Event Phase 4D-2A-3 Minimal Mapping Management UI completed；Accounting Event Phase 4D-2A-3 Manual QA Checklist completed。
- 穩定節點：Transaction Completion MVP completed through UI，已涵蓋 RBAC foundation、Data model foundation、Backend completion action、Backend completion payload、React UI、Manual QA checklist documented。
- 最新驗證狀態：`./vendor/bin/sail artisan test tests/Feature/AccountingEventConvertTest.php` passed：15 tests / 140 assertions；`./vendor/bin/sail artisan test tests/Feature/AccountingEventReviewTest.php tests/Feature/AccountingEventVoidTest.php tests/Feature/AccountingEventMappingConfigTest.php tests/Feature/StaffPermissionRoleMatrixTest.php` passed：58 tests / 631 assertions；`./vendor/bin/sail artisan test` passed：384 tests / 3567 assertions；`npm run build` passed。
- 本文件為目前穩定節點同步整理；目前不實作退款、不做 AR / AP / cash / invoice / reports 整合、不做 PDF / Excel、不做圖片上傳、不新增 profit / gross margin / 毛利 payload，完整 security hardening 之後再做。

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
- Vehicle Cost Management Phase 1 independent index
- Vehicle Cost Management Phase 2 independent create / edit workspace
- Vehicle sales
- Vehicle payment / receivable foundation
- Customer management foundation
- Customer transaction history
- Receivables mark-sold action
- Audit log display localization
- Accounting Phase 1：Chart of Accounts
- Accounting Phase 2：Journal Draft Foundation
- Accounting Phase 3：Journal Posting / Voiding
- Accounting Module Boundary Polish：會計科目與會計傳票已拆成獨立 module entries
- Accounting Journal Workbench UI Polish
- Vehicle Cost Accounting Treatment Spec
- Accounting Event Foundation Phase 1
- Accounting Event Phase 2 readonly workspace
- Accounting Event Phase 3 completion integration
- Accounting Event Phase 4A Review Workflow
- Accounting Event Phase 4B Void Workflow
- Accounting Event Phase 4C Account Mapping Spec
- Accounting Event Phase 4C-2 Config-based Mapping Foundation
- Accounting Event Phase 4D-1 Convert Skeleton
- Accounting Event Phase 4D-2 Journal Draft Generation Spec
- Accounting Event Phase 4D-2A Convert Preflight Service
- Accounting Event Phase 4D-2A-1 Runtime Mapping Decision Spec
- Accounting Event Phase 4D-2A-2 Database-backed Mapping Foundation
- Accounting Event Phase 4D-2A-3 Minimal Mapping Management UI
- Accounting Event Phase 4D-2A-3 Manual QA Checklist
- Confirm Delivery / Transaction Completion Spec
- Sales / Payments / Delivery semantics UI hints
- Transaction Completion / Confirm Delivery MVP：Completion RBAC、Completion data fields、Completion backend action、Completion payload、Completion UI、Completion audit event、Manual QA checklist

## Accounting Phase 1

- Chart of Accounts completed。
- Module Registry entry：`accounting-accounts`，base permission 為 `module.accounting.accounts.view`。
- Account types aligned with reference project：資產、負債、權益、收入、成本、費用。
- Opening balance stored in `accounting_accounts.opening_balance`，但尚未作為正式餘額來源。
- 正式餘額後續應由 journal lines 計算。
- No journals yet。
- No AR/AP/cash/invoice/report integration yet。

## Accounting Phase 2

- Manual draft journal entries completed。
- Module Registry entry：`accounting-journals`，base permission 為 `module.accounting.journals.view`。
- Debit / credit balance validation completed。
- JE number generation completed：`JE-YYYYMM-0001`，依 `company_id + YYYYMM` 遞增。
- Journal Draft pages completed：Index / Create / Show / Edit。
- Journal Draft backend completed：migration / model / policy / request / validator / number service / controller。
- Audit events completed：`accounting_journal.created`、`accounting_journal.updated`。
- 目前僅支援 draft 建立與編輯。
- Journal posting / voiding completed。
- Posted / voided lock rules completed。
- No AR/AP/cash/invoice/report integration yet。
- No automatic Receivables / Vehicle Costs integration yet。
- No profit / gross margin payload added。

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

- Accounting Journal Workbench UI Polish completed。
- 傳票列表、建立、編輯、明細、分錄表格、status bar、actions、totals / difference 顯示已往 workbench 操作模式整理。
- 這是前端 UI polish，不改 journal backend、routes、policies、migrations。
- Journal posting / voiding 規則仍由後端控制。
- Posted / voided journals 仍不可修改。
- No accounting event / journal draft generation from business documents yet。
- No automatic revenue recognition。
- No automatic COGS recognition。

## Accounting Event Foundation Phase 1

- Accounting Event Foundation Phase 1 completed。
- 已完成 `accounting_events` table。
- 已完成 `app/Models/AccountingEvent.php`。
- 已完成 `config/accounting_events.php`。
- 已完成 `tests/Feature/AccountingEventTest.php`。
- `accounting_events` 目前已有 foundation domain object 與只讀 workspace。
- Completion → pending Accounting Event 已由 Phase 3 完成。
- 目前不會轉 journal draft。
- 目前不做 revenue / COGS / profit / gross margin。
- Focused test：`./vendor/bin/sail artisan test tests/Feature/AccountingEventTest.php`，5 passed / 32 assertions。

## Accounting Event Phase 2 Readonly Workspace

- Accounting Event Phase 2 readonly workspace completed。
- 已完成 `accounting-events` module entry。
- 已完成 `module.accounting.events.view`。
- 已完成 `AccountingEventController` index / show。
- 已完成 `AccountingEventPolicy` viewAny / view。
- 已完成 React readonly Index / Show。
- 已完成 tenant-scoped query。
- Show 不使用 implicit model binding，跨 tenant 優先 404。
- Index 不輸出 payload JSON。
- Show payload 會經 sanitizer 排除 sensitive / tenant raw ids / profit / gross margin / revenue / COGS recognition 相關 key。
- `module.accounting.view` 不可單獨授權 accounting events。
- `admin` 與 `accounting` 預設有 `module.accounting.events.view`。
- `viewer` 預設沒有 `module.accounting.events.view`。
- Focused test：`./vendor/bin/sail artisan test tests/Feature/AccountingEventWorkspaceTest.php`，12 passed / 166 assertions。
- Focused test：`./vendor/bin/sail artisan test tests/Feature/AccountingEventTest.php`，5 passed / 32 assertions。
- `npm run build` passed。
- Accounting Event readonly workspace 已完成 index / show；no create；review route 已由 Phase 4A 完成；void route 已由 Phase 4B 完成；convert skeleton 已由 Phase 4D-1 完成。

## Accounting Event Phase 3 Completion Integration

- Accounting Event Phase 3 completion integration completed。
- successful completion 現在會建立一筆 `pending` Accounting Event。
- `source_type = vehicle_sale_completion`。
- `event_type = vehicle_sale_completed`。
- `status = pending`。
- event root 使用 company / branch tenant 欄位。
- payload 是後端控制的 safe allowlist。
- payload 包含 sale、vehicle、customer display、completion、receivable summary 的非敏感摘要。
- received amount / receivable status 沿用 `ReceivableSummaryService`。
- 不使用 `vehicle_sales.paid_amount` 作為已收金額來源。
- failed / unauthorized / cross-tenant completion 不會建立 Accounting Event。
- idempotency guard 防止同一 sale 重複建立 Accounting Event。
- completion update、Accounting Event creation、audit log 在同一 DB transaction 內。
- Accounting Event review 已由 Phase 4A 完成。
- Accounting Event void 已由 Phase 4B 完成。
- Accounting Event convert skeleton 已由 Phase 4D-1 完成。
- Accounting Event → Journal Draft 仍未完成。
- Journal Lines generation 仍未完成。
- Revenue Recognition 仍未完成。
- COGS Recognition 仍未完成。
- Profit / Gross Margin payload 仍未完成。
- AR / AP / Cash / Bank / Invoice / Reports 仍未完成。
- Refund / reversal 仍未完成。
- Focused test：`./vendor/bin/sail artisan test tests/Feature/AccountingEventCompletionIntegrationTest.php`。
- Focused test：`./vendor/bin/sail artisan test tests/Feature/AccountingEventTest.php`。
- Focused test：`./vendor/bin/sail artisan test tests/Feature/AccountingEventWorkspaceTest.php`。
- Focused test：`./vendor/bin/sail artisan test tests/Feature/VehicleSaleTest.php`。

## Accounting Event Phase 4A Review Workflow

- Accounting Event review workflow completed。
- Added `accounting_events.reviewed_at`。
- Added `module.accounting.events.review`。
- Added PATCH review route。
- Added `ReviewAccountingEventRequest` deny-list。
- Added `AccountingEventPolicy::review`。
- Added `AccountingEventController::review`。
- Added review form on `Accounting/Events/Show.jsx`。
- Only pending events can be reviewed。
- Review updates status, review_note, reviewed_by, reviewed_at only。
- Review writes `accounting_event.reviewed` audit log with safe allowlist。
- View-only, `module.accounting.view`-only, cross-tenant, reviewed, converted, voided events cannot be reviewed。
- Review does not create journal draft。
- Review does not create journal lines。
- Review does not post journal。
- Review does not recognize revenue / COGS。
- Review does not add profit / gross margin payload。
- Focused test：`./vendor/bin/sail artisan test tests/Feature/AccountingEventReviewTest.php`。
- Focused test：`./vendor/bin/sail artisan test tests/Feature/AccountingEventWorkspaceTest.php`。
- Focused test：`./vendor/bin/sail artisan test tests/Feature/AccountingEventTest.php`。
- Focused test：`./vendor/bin/sail artisan test tests/Feature/AccountingEventCompletionIntegrationTest.php`。
- Focused test：`./vendor/bin/sail artisan test tests/Feature/StaffPermissionRoleMatrixTest.php`。
- Build verification：`npm run build`。

## Accounting Event Phase 4B Void Workflow

- Accounting Event void workflow completed。
- Added `module.accounting.events.void`。
- Added PATCH void route。
- Added `VoidAccountingEventRequest` deny-list。
- Added `AccountingEventPolicy::void`。
- Added `AccountingEventController::void`。
- Added void form on `Accounting/Events/Show.jsx`。
- Only pending / reviewed events can be voided。
- Void updates status, void_reason, voided_by, voided_at only。
- Void preserves review_note, reviewed_by, reviewed_at。
- Void writes `accounting_event.voided` audit log with safe allowlist。
- View-only, review-only, module.accounting.view-only, cross-tenant, converted, already voided events cannot be voided。
- Void does not create journal draft。
- Void does not create journal lines。
- Void does not post journal。
- Void does not recognize revenue / COGS。
- Void does not add profit / gross margin payload。
- Void does not handle journal draft cancellation, posted journal reversal, refund, or return。
- Focused test：`./vendor/bin/sail artisan test tests/Feature/AccountingEventVoidTest.php`。
- Focused test：`./vendor/bin/sail artisan test tests/Feature/AccountingEventReviewTest.php`。
- Focused test：`./vendor/bin/sail artisan test tests/Feature/AccountingEventWorkspaceTest.php`。
- Focused test：`./vendor/bin/sail artisan test tests/Feature/AccountingEventTest.php`。
- Focused test：`./vendor/bin/sail artisan test tests/Feature/AccountingEventCompletionIntegrationTest.php`。
- Focused test：`./vendor/bin/sail artisan test tests/Feature/StaffPermissionRoleMatrixTest.php`。
- Build verification：`npm run build`。

## Accounting Event Phase 4C-2 Config-based Mapping Foundation

- Config-based Accounting Event mapping foundation completed。
- Added `config/accounting_event_mappings.php`。
- Added `AccountingEventMappingConfigTest`。
- `vehicle_sale_completed` mapping metadata exists。
- Mapping is disabled for runtime conversion。
- Mapping does not contain actual account IDs。
- Mapping does not contain fixed account codes。
- Journal line templates are disabled metadata only。
- Convert route / permission / request / policy / controller skeleton exists。
- No `AccountingEventConvertService` exists。
- No journal draft or journal lines are generated。
- No revenue / COGS recognition runtime exists。
- No profit / gross margin payload exists。
- Focused test：`./vendor/bin/sail artisan test tests/Feature/AccountingEventMappingConfigTest.php`。
- Result：10 tests / 155 assertions。
- Focused test：`./vendor/bin/sail artisan test tests/Feature/AccountingEventTest.php`。
- Result：5 tests / 33 assertions。

## Accounting Event Phase 4D-1 Convert Skeleton

- Accounting Event Phase 4D-1 Convert Skeleton completed。
- Added `module.accounting.events.convert` permission。
- `admin` / `accounting` 預設有 convert permission；`viewer` 預設沒有 convert permission。
- Staff Permission matrix 已顯示 `accounting.events` convert action，label 為「轉傳票」。
- Added PATCH route：`/employee-system/accounting/events/{accountingEvent}/convert`，route name：`employee-system.accounting.events.convert`。
- Added `ConvertAccountingEventRequest`。
- Added `AccountingEventPolicy::convert()`。
- Added `AccountingEventController::convert()`。
- Show payload 已新增 `can.convert`。
- Convert skeleton 只允許 same tenant、reviewed、未 void、未 converted、且具備 `module.accounting.events.convert` 的 event 進入 convert guard。
- Cross tenant convert 先 404。
- view-only / review-only / void-only / `module.accounting.view` 都不能 convert。
- Forbidden payload 會 403。
- Convert 會檢查 mapping exists、source_type match、mapping enabled。
- `config/accounting_event_mappings.php` 的 `vehicle_sale_completed.enabled = false`，所以正常 convert 會 fail-safe 422：`會計事件映射尚未啟用，無法產生傳票草稿。`
- Mapping missing 會 422：`找不到會計事件映射設定，無法產生傳票草稿。`
- Mapping source_type mismatch 會 422：`會計事件映射與來源類型不一致，無法產生傳票草稿。`
- Phase 4D-1 不會建立 `AccountingJournalEntry`。
- Phase 4D-1 不會建立 `AccountingJournalEntryLine`。
- Phase 4D-1 不會寫入 `converted_journal_entry_id`。
- Phase 4D-1 不會把 `accounting_events.status` 改成 `converted`。
- Phase 4D-1 沒有新增 `AccountingEventConvertService`。
- Phase 4D-1 沒有 journal draft generation、journal line generation、automatic posting、revenue recognition、COGS recognition、profit / gross margin payload、mapping UI、database-backed mapping table、AR / AP / Cash / Bank / Invoice / Reports / Refund / reversal。
- Focused test：`./vendor/bin/sail artisan test tests/Feature/AccountingEventConvertTest.php` passed：15 tests / 140 assertions。
- Focused regression：`./vendor/bin/sail artisan test tests/Feature/AccountingEventReviewTest.php tests/Feature/AccountingEventVoidTest.php tests/Feature/AccountingEventMappingConfigTest.php tests/Feature/StaffPermissionRoleMatrixTest.php` passed：58 tests / 631 assertions。
- Full test：`./vendor/bin/sail artisan test` passed：384 tests / 3567 assertions。
- Build verification：`npm run build` passed。

## Accounting Event Phase 4D-2 Journal Draft Generation Spec

- Accounting Event Phase 4D-2 Journal Draft Generation Spec completed。
- `docs/accounting-event-journal-draft-generation-spec.md` added。
- This is docs-only。
- Defines future draft header / line / permission / transaction / audit / testing boundaries。
- Recommends future runtime split: 4D-2A preflight only, 4D-2B revenue-side draft generation only。
- No runtime code changed。
- No journal draft generation。
- No journal lines。
- No converted status。
- No `converted_journal_entry_id` write。
- No `AccountingEventConvertService`。
- No COGS / tax / overpayment / refund / AR / AP / Cash / Bank / Invoice / Reports。

## Accounting Event Phase 4D-2A Convert Preflight Service

- Accounting Event Phase 4D-2A Convert Preflight Service completed。
- Added `app/Services/AccountingEventConvertPreflightService.php`。
- Added `tests/Feature/AccountingEventConvertPreflightServiceTest.php`。
- Preflight only returns validated preview。
- Runtime still does not create journal draft。
- Runtime still does not create journal lines。
- Runtime still does not set status converted。
- Runtime still does not write `converted_journal_entry_id`。
- Runtime still does not write `accounting_event.converted` audit。
- Preview requires both `module.accounting.events.convert` and `module.accounting.journals.create`。
- Preview validates same tenant, reviewed / not voided / not converted state, positive amount, mapping exists / source_type / enabled, required runtime accounts, account company / branch / active / type, and `AccountingJournalValidator::validateDraftLines()`。
- Preview returns revenue-side header and two lines only：debit `accounts_receivable_account` and credit `sales_revenue_account`。
- Preview excludes customer sensitive keys, full payload JSON, profit / gross margin / purchase_cost / cogs_amount / revenue_amount。
- 4D-2B revenue-side draft generation remains backlog。
- COGS / tax / overpayment / refund / reversal remains backlog。
- Mapping config default remains disabled and no actual runtime account IDs in committed config。
- 4D-2B revenue-side draft generation 的真正阻塞點是 runtime account mapping 來源；正式 account IDs 不寫入 committed config，下一步建議先做 database-backed mapping foundation。

## Accounting Event Phase 4D-2A-1 Runtime Mapping Decision Spec

- Accounting Event Runtime Mapping Decision Spec completed。
- Added `docs/accounting-event-runtime-mapping-decision-spec.md`。
- Decision：`config/accounting_event_mappings.php` 短期只保留 event type / mapping key / line template metadata。
- Decision：不把正式 account IDs 寫死在 committed config，不把 config override 當 production runtime 設定。
- Next runtime recommendation：先做 database-backed mapping foundation，再做 4D-2B revenue-side journal draft generation。
- Future table candidate：`accounting_event_account_mappings`，company-scoped，branch nullable，支援 branch override company default。
- First supported event type remains `vehicle_sale_completed`。
- First required mapping keys remain `accounts_receivable_account` and `sales_revenue_account`。
- No journal draft generation、journal lines、converted status、`converted_journal_entry_id` write、`accounting_event.converted` audit、automatic posting、revenue / COGS / tax / refund / reversal runtime、profit / gross margin payload、mapping UI、route / permission / migration / model / policy / controller / request / seeder changes。

## Accounting Event Phase 4D-2A-3 Minimal Mapping Management UI

- Accounting Event Phase 4D-2A-3 Minimal Mapping Management UI completed。
- Added mapping module / routes / controller / policy / requests / React pages / tests。
- Mapping UI only manages DB-backed mapping records for Accounting Event preflight / future draft generation。
- First scope only supports `vehicle_sale_completed` + `accounts_receivable_account` / `sales_revenue_account` required keys。
- No journal draft / lines / converted status / posting / recognition runtime。
- 4D-2B revenue-side journal draft generation remains backlog。

## Accounting Event Phase 4D-2A-3 Manual QA Checklist

- Accounting Event Phase 4D-2A-3 Manual QA Checklist completed。
- Added `docs/accounting-event-mapping-manual-qa-checklist.md`。
- This is docs-only。
- No runtime code changed。
- Config remains disabled by default；convert should still fail safe without journal draft generation。
- No journal draft / lines / converted status / posting / recognition runtime。
- 4D-2B revenue-side journal draft generation remains backlog。

## Accounting Module Boundaries

- Accounting is now split by functional module boundaries。
- `accounting` 只保留為相容 / 分類概念；`module.accounting.view` 不作為會計科目或會計傳票功能入口的唯一安全依據。
- 會計科目 module key：`accounting-accounts`，route：`employee-system.accounting.accounts.index`，base permission：`module.accounting.accounts.view`。
- 會計傳票 module key：`accounting-journals`，route：`employee-system.accounting.journal-entries.index`，base permission：`module.accounting.journals.view`。
- Sidebar visibility 由後端 `visibleModules` 依各 module `base_permission` 輸出；只有 accounts.view 只看得到會計科目，只有 journals.view 只看得到會計傳票。
- Journal create 讀取 active account options 是傳票建立必要資料，不代表取得會計科目管理入口權限。
- 未來 `accounting-receivables`、`accounting-payables`、`accounting-cash`、`accounting-invoices`、`accounting-reports` 也應獨立成 module。
- 本次未新增 AR / AP / cash / invoice / report，未串接 Receivables / Vehicle Costs，未新增 profit / gross margin payload。

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
- Vehicle Cost Management Phase 1：新增獨立入口 `/employee-system/vehicle-costs`，使用既有 `vehicle_costs`、既有 `module.vehicles.costs.view` 權限與 tenant scope，提供成本列表、篩選、摘要與連回車輛；Vehicle Costs Index 預設顯示本月資料，並可切換上月、近 90 天、今年、全部或自訂期間。
- Vehicle Cost Management Phase 2：新增獨立 create / edit 工作台 `/employee-system/vehicle-costs/create` 與 `/employee-system/vehicle-costs/{vehicleCost}/edit`；mutation 仍沿用既有 `VehicleCostController` 的 `employee-system.vehicles.costs.store` / `employee-system.vehicles.costs.update`，不新增成本寫入路由。
- Vehicle Cost Management 仍不是會計、不是應付帳款、不是報表，不做 PDF / Excel，也不新增 profit / gross margin payload。
- Vehicle Costs Summary 不是正式報表，只是目前期間與篩選條件下的查詢摘要。
- Sales lifecycle sync：銷售狀態會同步車輛 lifecycle。
- Active sale guard：每台車只允許一筆 active sale。
- Vehicle Sales customer linking：銷售可 nullable 關聯 `customer_id`，`customer_name` / `customer_phone` 保留為交易當下 snapshot。
- Vehicle Sales customer payload 僅輸出 `id`、`customer_number`、`name`、`phone` 基本顯示資訊，不暴露 Customer sensitive 欄位。
- Vehicle Payment / Receivable Foundation：每筆 Vehicle Sale 可記錄多筆收款，Show / Edit 顯示應收、已收、未收、收款狀態與收款紀錄；已作廢收款不計入已收金額，超收僅提示不阻擋。
- Audit events：車輛、成本、銷售與公司設定異動已記錄 operation audit。
- Show / Edit UI split：Show 偏只讀展示；Edit 承載可編輯流程與 mutation UI 所需選項。

## Vehicle Cost Accounting Treatment Spec

- `docs/vehicle-cost-accounting-treatment-spec.md` 已建立。
- 該文件只定義 `vehicle_costs.cost_type` 對應 accounting treatment 方向。
- `purchase_price` / `repair` / `detailing` / `inspection` 傾向 capitalized vehicle cost。
- `transport` / `tax` / `other` 需要 review。
- `management` 通常是 period expense。
- 本階段沒有新增欄位、沒有自動分錄、沒有 COGS、沒有 profit / gross margin payload。
- Vehicle Cost Management 仍不是 AR / AP、Cash / Bank、Invoice 或 Reports。

## Transaction Completion / Confirm Delivery MVP

- Transaction Completion RBAC Foundation completed。
- `module.vehicles.sales.completion.view` / `module.vehicles.sales.completion.confirm` 已建立。
- Staff Permission Matrix 已支援 `post` / `confirm` / `complete` actions，`vehicles.sales.completion` label 為「交易完成」。
- `admin` 有 completion view + confirm；`sales` / `accounting` / `inventory` 有 completion view only；`viewer` 沒有 completion permission。
- Transaction Completion Data Model Foundation completed：`vehicle_sales` 已有 `completed_at`、`completed_by`、`completion_note`。
- `VehicleSale` 已有 completion casts 與 `completer()` relationship。
- Transaction Completion Backend Action completed：route `employee-system.vehicles.sales.complete`，method `PATCH /employee-system/vehicles/{vehicle}/sales/{vehicleSale}/complete`，request `CompleteVehicleSaleTransactionRequest`，policy `VehicleSalePolicy::complete`。
- 完成交易必須由使用者明確觸發，並使用 `vehicle_sales.completed_at`、`completed_by`、`completion_note` 記錄交易完成。
- 完成交易需通過後端 policy / request / tenant scope / state guard。
- Completion action 條件：`sale_status = sold`、vehicle `lifecycle_status = sold`、receivable status = `paid` / `overpaid`、`sale_price` exists and > 0、not already completed、not cancelled、vehicle not archived、user has `module.vehicles.sales.completion.confirm`。
- 完成後寫入 `vehicle_sale.transaction_completed` audit event。
- Audit payload 不包含 tenant raw ids、敏感個資、profit / gross margin、accounting journal fields。
- Transaction Completion Backend Payload completed：Receivables Show 已提供完整 `sale.completion` object；Receivables Index 已提供 lightweight completion summary；Vehicle Show / Edit 已提供 readonly completion summary。
- Transaction Completion React UI completed：Receivables Show 已有交易完成狀態、block reason、`completion_note` form、完成交易 action；Vehicle Show / Edit 只顯示唯讀 completion summary。
- Receivables Show 是目前主要操作入口；Vehicle Show / Edit 只顯示唯讀 completion summary。
- Accounting Event Foundation Phase 1、Phase 2 readonly workspace、Phase 3 completion integration、Phase 4A Review Workflow、Phase 4B Void Workflow 與 Phase 4C-2 Config-based Mapping Foundation 已存在，但目前沒有 convert、journal draft generation、revenue recognition、COGS recognition、profit / gross margin payload、return / refund / reversal flow。
- Accounting Event convert skeleton 已由 Phase 4D-1 完成。

## 車輛流程

- 主要流程：`in_stock` → `reserved` → `sold`。
- `reserved` 可取消並回到 `in_stock`。
- `sold` 不可任意退回 `reserved`。
- `sold vehicle` 不可建立新 sale。
- 每台車只允許一筆 active sale；active sale 目前包含 `draft`、`reserved`、`sold`，`cancelled` 不算 active。
- 已成交銷售取消不會自動把車輛回到 `in_stock`，避免 MVP 簡化流程誤導退車 / 退款 / 作廢邏輯。

## 目前完整業務流

```txt
Customer → Vehicle Sale → Receivables / Payments → Mark Sold → Complete Transaction / Confirm Delivery → Customer Transaction History → Audit Logs
```

- Customer 主檔可被 Vehicle Sale 關聯，並保留交易當下 customer snapshot。
- Vehicle Sale 建立後可透過 Receivables / Payments 管理應收、已收、未收與收款紀錄。
- Receivables mark-sold action 可在收款 / 應收流程中完成售出狀態銜接。
- Complete Transaction / Confirm Delivery 目前代表交易完成狀態記錄，並建立一筆 pending Accounting Event。
- Complete Transaction / Confirm Delivery 不會自動產生 journal draft。
- Complete Transaction / Confirm Delivery 不會自動認列 revenue / COGS。
- Complete Transaction / Confirm Delivery 不會計算 profit / gross margin。
- Customer Transaction History 顯示客戶關聯銷售與收款摘要，並受 tenant scope 與後端權限控制。
- Audit Logs 已支援主要業務事件與顯示標籤在地化，便於營運人員閱讀。

未來規格方向：

```txt
Customer → Vehicle Sale → Receivables / Payments → Mark Sold → Confirm Delivery / Complete Transaction → Accounting Event / Journal Draft → Revenue / COGS Recognition
```

- Accounting Event Foundation Phase 1、Phase 2 readonly workspace、Phase 3 completion integration、Phase 4A Review Workflow、Phase 4B Void Workflow 與 Phase 4C-2 Config-based Mapping Foundation 已存在。
- Completion → pending Accounting Event 已完成；pending → reviewed 已完成；pending / reviewed → voided 已完成；config-based mapping foundation 已完成但 disabled；Accounting Event → Journal Draft → Revenue / COGS Recognition 仍是 future backlog。
- Accounting Event convert skeleton 已由 Phase 4D-1 完成。
- No automatic journal draft generation yet。
- No automatic revenue recognition。
- No automatic COGS recognition。

## Receivables / Vehicle Sale / UI Hints

- Receivables / Vehicle pages 已加入 sales / payments / delivery semantics UI hints。
- Transaction Completion MVP 已補齊資料欄位、後端 action、payload 與 UI；語意提示仍只作為 UX 說明。
- 收款完成只代表款項已記錄。
- `mark sold` 只代表銷售與車輛售出狀態銜接。
- 交車完成 / 完成交易目前只記錄 completion 狀態，不是會計認列。
- 收入與 COGS 認列目前不會自動產生。
- Sales / Payments / Delivery semantics hints are frontend UX only；正式權限、狀態、tenant scope、資料一致性仍由後端負責。

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
- `module.vehicles.sales.completion.view`
- `module.vehicles.sales.completion.confirm`

### 權限狀態

- `admin` 預設取得車輛、價格、成本、銷售、銷售收款與 completion view + confirm 完整權限。
- `sales` 預設可維護客戶與車輛、建立 / 查看銷售、查看收款狀態與 completion view；不預設新增 / 作廢收款、mark sold、completion confirm、敏感個資、價格、成本或稽核權限。
- `accounting` 預設可查看客戶、車輛、銷售、收款與 completion view，並可建立 / 作廢收款與執行 receivables mark-sold；不預設建立銷售、completion confirm、敏感個資、成本或稽核權限。
- `inventory` 預設可建立 / 更新車輛、查看成本與 completion view；不預設銷售寫入、收款、客戶、completion confirm 或稽核權限。
- `viewer` 預設僅有 dashboard、車輛與客戶最小只讀，不預設價格、成本、銷售、收款、completion 或敏感個資權限。
- `pricing` / `costs` / `sales` 為 `module.vehicles.*` 下的 nested permissions。
- Staff Permission matrix 已支援 `vehicles.pricing`、`vehicles.costs`、`vehicles.sales`、`vehicles.sales.payments` 與 `vehicles.sales.completion` 權限分組。

## Audit Events

目前整理的 operation audit events：

- `vehicle.created`
- `vehicle.updated`
- `vehicle_cost.created`
- `vehicle_cost.updated`
- `vehicle_sale.created`
- `vehicle_sale.updated`
- `vehicle_sale.transaction_completed`
- `vehicle_sale_payment.created`
- `vehicle_sale_payment.voided`
- `company_settings.updated`
- `customer.created`
- `customer.updated`

Audit 資料原則：

- Vehicle audit 僅記錄主要業務欄位，避免將內部備註等潛在敏感內容寫入快照。
- Vehicle costs audit 只記錄白名單欄位，不記 `internal_notes`。
- Vehicle sales audit 只記錄白名單欄位，不記 tenant / actor / internal-only sensitive 欄位。
- Vehicle sale completion audit 只記錄 completion 白名單欄位，不記 tenant raw ids、敏感個資、profit / gross margin 或 accounting journal 欄位。
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
- Accounting Event readonly workspace 已存在。
- Completion → pending Accounting Event 已完成。
- Accounting Event review 已完成。
- Accounting Event void 已完成。
- Config-based mapping foundation 已完成。
- Accounting Event convert skeleton 已由 Phase 4D-1 完成。
- 尚未由 Accounting Event 產生 Journal Draft。
- database-backed mapping / mapping UI 仍是 future direction。
- 尚未自動 revenue recognition。
- 尚未自動 COGS recognition。
- 尚未計算 profit / gross margin。
- 尚未做 AR / AP。
- 尚未做 Cash / Bank。
- 尚未做 Invoice。
- 尚未做 Reports。
- 尚未做 refund / return / reversal flow。
- 尚未做 delivery checklist / 文件上傳 / 交車照片。
- 尚未做 full accounting automation。
- 尚未做 full security hardening。
- 車輛成本管理目前為 Phase 2 獨立列表與 create / edit 工作台，不是完整會計；不做應付帳款、付款沖帳、成本報表、PDF / Excel 或 profit / gross margin payload。
- 尚未做租賃模組。
- 客戶模組已完成 MVP 與交易紀錄；尚未串接合約或完整 CRM。
- 尚未做完整會計分錄、退款流程、發票、報表 / PDF / Excel。
- 尚未做毛利 payload（profit / gross_profit / gross_margin）。
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

- Phase B 已完成：Sales / Payments / Delivery semantics UI hints。
- Manual browser QA execution if not yet done。
- Phase 0 / 1 / 2 / 3 / 4A / 4B / 4C / 4C-2 / 4D-1 / 4D-2-spec / 4D-2A / 4D-2A-1 已完成：Accounting Event spec、foundation、readonly workspace、Completion → pending Accounting Event、pending → reviewed、pending / reviewed → voided、Account Mapping Spec、config-based mapping foundation、convert skeleton、journal draft generation spec、convert preflight service、runtime mapping decision spec。
- 後續可做：Accounting Event Phase 4D-2A-2 database-backed mapping foundation，no UI，no draft generation。
- 後續可做：Accounting Event Phase 4D-2A-3 mapping admin UI，optional。
- 後續可做：Accounting Event Phase 4D-2B revenue-side journal draft generation only。
- 後續可做：Account Mapping UI / database-backed mapping。
- 後續可做：Accounting Event → Journal Draft。
- 後續可做：Revenue Recognition。
- 後續可做：COGS Recognition。
- 後續可做：Profit / Gross Margin reports。
- 後續可做：Reversal / refund / return flow。
- 後續可做：Delivery checklist / documents / photos。
- 後續可做：Full security hardening。
- 租賃流程。
- 合約與完整 CRM 延伸。
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
