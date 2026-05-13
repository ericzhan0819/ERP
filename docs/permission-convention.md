# RBAC / Module 權限命名規範

## 架構原則

- `modules`：功能入口註冊表（feature registry），只描述模組是否存在、是否啟用與入口權限。
- `permissions`：動作權限表，描述使用者可執行的具體操作。
- `roles`：身份集合，用來批次授予多個權限。
- `direct permissions`：僅作為特例覆蓋，不取代角色設計。
- `visibleModules`：Sidebar 唯一資料來源，由後端依 `modules.is_active` + `base_permission` 計算後輸出至 `auth.visibleModules`。

## 命名規範

### 模組入口權限

```txt
module.{module-key}.view
```

範例：

- `module.dashboard.view`
- `module.staff-permission.view`

### 動作權限

```txt
{domain}.{resource}.{action}
```

範例：

- `staff.employee.create`
- `staff.employee.update`
- `staff.employee.delete`
- `inventory.product.view`
- `inventory.product.create`
- `crm.customer.update`

## 禁止規則

- 前端不可用 `role` 判斷 Sidebar。
- 前端不可用 `permissions` 判斷 Sidebar。
- Controller 不可直接 `syncRoles` / `syncPermissions`。
- 不可把 `modules` 當 action permissions。
- 不可使用 `module.crm.edit` / `module.crm.delete` 這類混合型命名。

## Route 責任分離

- 模組入口：`module.access:{module-key}`。
- 動作授權：`permission:{permission-name}` 或 Policy。
- UI 顯示：`auth.visibleModules`。

## 新增模組 Checklist

- [ ] 建 `modules` row。
- [ ] 建 `base_permission`。
- [ ] 建 action permissions。
- [ ] 加到 seeder。
- [ ] 加 route middleware。
- [ ] 確認 `visibleModules` 有出現。
- [ ] 確認 disabled module 會 403。
