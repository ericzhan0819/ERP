# ERP Foundation Plan

## 目標

建立 Laravel + Inertia + React 的中古車 ERP 最終地基。方向參考 TailAdmin 的清楚分層與側欄節奏，但不照搬 demo、Blade、Alpine 或 React Router。

## 1. 技術棧定案

- 後端：Laravel。
- 頁面橋接：Inertia。
- 前端：React。
- 建置：Vite。
- 樣式：Tailwind。
- 路由：使用 Laravel named routes + Inertia Link。
- 不使用 React Router。
- 不使用 Alpine。
- 不使用 Blade UI components。

## 2. 前端結構

```text
resources/js/
  Layouts/
    DashboardLayout.jsx
  Components/Dashboard/
    Header.jsx
    Sidebar.jsx
    MobileSidebar.jsx
    cards/
    tables/
    badges/
  config/
    sidebar.ts
```

- DashboardLayout：主外殼，負責 Header、Sidebar、MobileSidebar 與內容區排列。
- Header：只處理頂部導覽、使用者入口與行動版開關。
- Sidebar：桌面版選單呈現，不承擔權限判斷。
- MobileSidebar：行動版抽屜，選單資料與 Sidebar 同源。
- sidebar.ts：唯一選單設定來源，支援 key、label、route、icon、section、badge 等欄位。
- 未來元件資料夾：cards、tables、badges，分別承載 ERP 指標卡、資料表格、狀態/風險標籤。

## 3. 後端結構

- routes/web.php：所有頁面使用 named routes，作為 Inertia Link 與後端 route access 的一致來源。
- Inertia::render：每個頁面的唯一入口，不新增 Blade 頁面入口。
- HandleInertiaRequests.php：只共享必要 props，例如 auth.user、accountStatus、visibleModules、flash。
- Controller：只負責請求協調，不散落角色或模組判斷。

## 4. 帳號系統

- Account/Auth 負責登入、登出、User、帳號狀態。
- 帳號狀態包含 active、disabled、pending 等基本存取狀態。
- Auth 僅回答「是否登入」與「帳號是否可用」。
- Auth 不處理模組權限。

## 5. 權限系統

- PermissionService 作為所有權限判斷的統一入口。
- Controller、middleware、shared props 均透過 PermissionService 取得結果。
- 未來可接 Spatie，但 Spatie 只能作為 PermissionService 的內部實作。
- 前端與 Sidebar 不直接依賴 Spatie。
- 避免 role / permission 判斷散落在 controller、React 元件與選單設定。

## 6. 模組系統

- modules table：記錄系統模組。
- module key：作為模組穩定識別，例如 staff、inventory、orders。
- visibleModules：後端根據帳號、角色、模組狀態與權限計算後傳給前端。
- route access：後端依 module key 與 named route 控制頁面存取。
- 模組啟用、停用、排序、顯示名稱應由資料層或設定層管理。

## 7. Sidebar 原則

- Sidebar 不直接判斷 role。
- Sidebar 不直接查 Spatie。
- Sidebar 不自行推導模組權限。
- Sidebar 只讀取後端傳來的 visibleModules。
- sidebar.ts 只定義選單結構；是否顯示由 visibleModules 決定。
- 桌面與行動 Sidebar 必須使用同一份選單來源。

## 8. 開發順序

1. 穩定 Layout / Sidebar。
2. 重建 Auth。
3. 建立 Role 基礎。
4. 建立 Module Permission。
5. 最後才做 Staff / Inventory / Orders CRUD。

## 9. 禁止事項

- 不導入 React Router。
- 不導入 Alpine。
- 不搬 Blade components。
- 不直接複製 TailAdmin demo data。
- 不讓 Spatie 判斷散落在前端與 controller。
- 不建立第二套路由或 UI 系統。
- 不為了 TailAdmin 範例大幅重構現有 Inertia 架構。

## 10. 成功標準

- Laravel named routes 是頁面與權限的共同依據。
- Inertia::render 是頁面入口。
- sidebar.ts 是唯一選單來源。
- visibleModules 是 Sidebar 顯示依據。
- PermissionService 是權限判斷唯一入口。
- UI 結構保持透明、有序、穩定，支援後續 ERP 模組漸進擴充。
