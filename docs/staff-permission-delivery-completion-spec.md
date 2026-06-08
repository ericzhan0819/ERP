# Staff Permission Delivery / Completion Spec

## 1. 目的

本文件定義未來 `Confirm Delivery` / `Complete Transaction` 權限若要加入 Staff Permission Matrix 時，應如何命名、分組、顯示、授權與分階段實作。

本文件只作為內部工程規格與後續 Roo Code 任務依據，不代表目前 repo 已存在 delivery / completion 權限、route、action、欄位或功能。

本階段明確不做：

- 本文件只定義規格。
- 本次不新增 permission。
- 本次不改 `RolePermissionSeeder`。
- 本次不改 `StaffPermissionController`。
- 本次不改 `StaffPermissions/Index.jsx`。
- 本次不改 action whitelist。
- 本次不新增 route / action / 欄位。
- 本次不實作 `Confirm Delivery` / `Complete Transaction`。
- 本次不產生 accounting event / journal draft。
- 本次不認列 revenue / COGS。

## 2. 背景

目前專案已完成或已文件化下列基礎：

- Staff Permission matrix。
- RBAC / Module Registry。
- Vehicle Sales。
- Receivables / Payments。
- Receivables mark-sold action。
- Accounting Journals post / void。
- `docs/confirm-delivery-transaction-completion-spec.md`。
- `docs/confirm-delivery-ui-permission-spec.md`。

目前 Staff Permission matrix 現況：

- `StaffPermissionController` 會依 permission name 拆成 matrix key。
- 目前支援 `module.{module}.{action}`、`module.{module}.{sub_scope}.{action}`，以及更深層 nested permission。
- 目前已有 `vehicles.sales.payments`、`receivables`、`vehicles.costs`、`vehicles.pricing` 等分組。
- `StaffPermissions/Index.jsx` 主要消費後端 `permissionMatrix` 與 `actionLabels`，不應硬編 delivery / completion 權限分組。
- 正式授權仍由後端 RBAC / Policy / Controller / tenant scope 控制，前端顯示只作 UX。

## 3. 目前限制

### 3.1 Action Whitelist 限制

目前 `RolePermissionSeeder::ACTION_WHITELIST` 為：

```txt
view, create, update, delete, export, approve, post, void, mark-sold, manage
```

目前 whitelist 尚未包含：

- `confirm`
- `complete`

如果未來直接新增下列候選 permission，會遇到 whitelist 檢查問題，除非先調整 whitelist：

- `module.vehicles.sales.delivery.confirm`
- `module.vehicles.sales.complete`

本階段不改 whitelist。正式實作前必須先決定 action 命名是否沿用既有 whitelist action，或新增 `confirm` / `complete`。

### 3.2 StaffPermissionController Actions 限制

目前 `StaffPermissionController` matrix actions 為：

```txt
view, create, update, delete, export, approve, void, mark-sold, manage
```

目前尚未包含：

- `confirm`
- `complete`

若未來新增這些 action，也需同步更新：

- `$actions`
- `actionLabels`

本階段不修改。

### 3.3 SUB_SCOPE_LABELS 限制

目前 `SUB_SCOPE_LABELS` 尚未包含：

- `vehicles.sales.delivery`
- `vehicles.sales.completion`

若未來新增 nested permission，需要補中文 label，避免 matrix 顯示 fallback 英文。

本階段不修改。

## 4. Permission 命名策略

### 4.1 策略 A：新增 confirm / complete actions

候選 permission：

- `module.vehicles.sales.delivery.view`
- `module.vehicles.sales.delivery.confirm`
- `module.vehicles.sales.complete`
- `module.vehicles.sales.completion.view`
- `module.vehicles.sales.completion.confirm`

優點：

- 語意清楚。
- `confirm` 明確表示確認交車。
- `complete` 明確表示完成交易。
- 比使用 `update` 更不容易誤解。

缺點：

- 必須修改 `RolePermissionSeeder::ACTION_WHITELIST`。
- 必須修改 `StaffPermissionController` 的 `$actions`。
- 必須新增 `actionLabels.confirm = 確認`、`actionLabels.complete = 完成`，或更精準中文。
- 需要測試 matrix 顯示與角色更新。

### 4.2 策略 B：沿用既有 action whitelist

候選 permission：

- `module.vehicles.sales.delivery.view`
- `module.vehicles.sales.delivery.update`
- `module.vehicles.sales.completion.view`
- `module.vehicles.sales.completion.update`
- `module.vehicles.sales.delivery.manage`

優點：

- 不需要新增 whitelist action。
- 較少改 RBAC 基礎。
- Staff Permission Matrix 可較容易顯示。

缺點：

- `update` / `manage` 語意太寬，容易把普通銷售更新與交車確認混在一起。
- 不符合 `Confirm Delivery` 是敏感業務節點的語意。
- 未來 audit / policy / UI 會比較不直覺。

### 4.3 建議方向

中期建議採用策略 A，讓敏感業務節點有明確 action：

- `module.vehicles.sales.delivery.view`
- `module.vehicles.sales.delivery.confirm`
- `module.vehicles.sales.completion.view`
- `module.vehicles.sales.completion.confirm`

若短期只做最小組合，可考慮：

- `module.vehicles.sales.delivery.confirm`
- `module.vehicles.sales.complete`

正式實作前必須先決定是否新增 `confirm` / `complete` 到 whitelist。本階段不新增任何 permission。

## 5. Matrix 分組建議

### 5.1 vehicles.sales.delivery

中文 label 候選：

- 車輛交車。
- 交車確認。
- 車輛銷售交車。

建議 label：

```txt
車輛交車
```

可能 actions：

- `view`：查看交車狀態。
- `confirm`：確認交車。

### 5.2 vehicles.sales.completion

中文 label 候選：

- 交易完成。
- 車輛交易完成。
- 銷售完成。

建議 label：

```txt
交易完成
```

可能 actions：

- `view`：查看交易完成狀態。
- `confirm`：完成交易。

### 5.3 是否合併 delivery / completion

合併優點：

- UI 較簡單。
- 初期權限較少。
- 適合 MVP。

合併缺點：

- 若未來交車與交易完成分離，會需要再拆權限。
- 會計事件可能更接近 completion，而不是 delivery。
- 營運交車與會計完成可能責任不同。

建議：

- 短期可以先用單一 `vehicles.sales.delivery` 或 `vehicles.sales.completion`。
- 正式實作前必須依資料模型決定。
- 如果目前仍不確定，應先不新增權限，只保留分組規格。

## 6. Action Label 建議

未來若新增 action，Staff Permission matrix action label 建議如下：

| action | 中文 label | 備註 |
| --- | --- | --- |
| `confirm` | 確認 | 適合交車確認或交易完成確認。 |
| `complete` | 完成 | 若採 `module.vehicles.sales.complete`，可顯示為完成。 |
| `mark-sold` | 標記成交 | 既有 action，不代表 confirm delivery。 |
| `view` | 檢視 | 既有 action，可用於查看狀態。 |

規則：

- `mark-sold` 不應被拿來代表 confirm delivery。
- `update` 不應被拿來代表 confirm delivery，除非短期刻意採用策略 B。
- `manage` 不應作為一般授權，避免權限過大。

## 7. 角色預設策略

以下只寫方向，不實作。角色預設需等正式實作前由業務流程決策。

| 角色 | 未來預設方向 |
| --- | --- |
| `admin` | 可查看 delivery / completion，可 confirm delivery，可 complete transaction。 |
| `sales` | 可查看 delivery / completion；是否可 confirm delivery 需由業務流程決定；不建議預設 complete transaction，除非公司流程要求業務自行完成交易。 |
| `accounting` | 可查看 completion；可能需要查看 delivery 狀態以判斷 accounting event；不建議預設 confirm delivery；不建議預設 complete transaction，除非會計角色負責最終交易完成覆核。 |
| `inventory` | 可查看 delivery；可能協助交車狀態；不建議預設 complete transaction。 |
| `viewer` | 通常只可 view，不可 confirm / complete。 |

本階段不改 role templates。

## 8. UI 顯示規則

未來 Staff Permission Matrix UI 原則：

- delivery / completion 權限應獨立分組，不混在一般「車輛銷售」更新權限。
- 不應讓 `module.vehicles.sales.update` 等同於交車確認。
- 不應讓 `module.receivables.mark-sold` 等同於交車確認。
- 不應讓 `module.vehicles.sales.view` 等同於查看所有 delivery / completion 細節，除非正式規格決定。
- Matrix 顯示應由後端 `permissionMatrix` 生成，前端不硬編。
- 若 `actionLabels` 沒有對應 action，UI 會顯示原 action key，因此正式實作前需補中文 label。

## 9. 後端實作影響清單

若未來真的新增 delivery / completion permission，可能需要修改：

- `RolePermissionSeeder::ACTION_WHITELIST`。
- `$permissionDefinitions`。
- `$roleTemplates`。
- `StaffPermissionController::$actions`。
- `StaffPermissionController::SUB_SCOPE_LABELS`。
- `StaffPermissionController` 回傳的 `actionLabels`。
- 相關 Feature tests。
- Permission cache reset 流程。
- `README.md` / `CURRENT_STATE.md` 權限清單。
- 未來 Delivery / Completion Policy。

本文件不做以上修改。

## 10. 測試方向

未來需要測試：

- 新 permission 是否被 seeder 建立。
- whitelist 是否允許 `confirm` / `complete`。
- Staff Permission matrix 是否顯示 delivery / completion 分組。
- action labels 是否顯示中文。
- `admin` 是否預設有必要權限。
- `sales` / `accounting` / `inventory` / `viewer` 預設權限是否符合決策。
- 沒有 delivery permission 的角色不可看到或執行 `Confirm Delivery` action。
- 直接打 route 仍由後端擋 403 / 404。
- permission cache reset 後權限正常。

## 11. 明確不做的功能

本文件不做：

- 不新增 permission。
- 不修改 `RolePermissionSeeder`。
- 不修改 `ACTION_WHITELIST`。
- 不修改 `StaffPermissionController`。
- 不修改 `StaffPermissions/Index.jsx`。
- 不新增 `actionLabels`。
- 不新增 `SUB_SCOPE_LABELS`。
- 不修改 role templates。
- 不新增 route。
- 不新增 policy。
- 不新增 request。
- 不新增 controller action。
- 不新增 migration。
- 不新增 delivery / completion 欄位。
- 不新增 Confirm Delivery UI。
- 不新增 Complete Transaction UI。
- 不新增 accounting event。
- 不新增 journal draft。
- 不認列 revenue。
- 不認列 COGS。
- 不計算 profit / gross margin。

## 12. 後續小步實作建議

以下只列 backlog，不實作：

- Phase A：完成本文件。
- Phase B：決定 permission 命名策略 A 或 B。
- Phase C：若採策略 A，先補 `confirm` / `complete` whitelist 與 actionLabels。
- Phase D：補 permission definitions 與 `SUB_SCOPE_LABELS`。
- Phase E：補 role template 預設策略。
- Phase F：補 Staff Permission matrix tests。
- Phase G：補 `README.md` / `CURRENT_STATE.md` 權限清單。
- Phase H：才進入 delivery / completion 後端 action 規格。
- Phase I：delivery / completion 資料模型實作。
- Phase J：Confirm Delivery UI action。
- Phase K：Accounting Event draft。
- Phase L：Revenue / COGS recognition。

Phase B 以後都不是本次工作。
