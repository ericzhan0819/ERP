# TailAdmin 參考分析

## 檢視範圍

- 主專案：`resources/js/Layouts/DashboardLayout.jsx`、`resources/js/Components/Dashboard/Sidebar.jsx`、`resources/js/config/sidebar.ts`、`routes/web.php`、`app/Http/Middleware/HandleInertiaRequests.php`。
- React 範例：`Material/free-react-tailwind-admin-dashboard`。
- Laravel 範例：`Material/tailadmin-laravel`。

## TailAdmin React 可借鏡的前端架構

### Layout

- `src/layout/AppLayout.tsx` 採用固定外殼：Sidebar、Backdrop、Header、內容容器分層清楚。
- 可借鏡：保留主專案 `DashboardLayout.jsx` 現有結構，只微調寬度、間距、狀態命名與行動版遮罩，不需要改路由系統。
- 主專案目前已具備相近模式：左側 `Sidebar`、上方 `Header`、行動版 `MobileSidebar`、內容區 `max-w-screen-2xl`。

### Sidebar 狀態

- `src/context/SidebarContext.tsx` 將 `isExpanded`、`isHovered`、`isMobileOpen` 集中管理。
- 可借鏡：若未來側欄狀態跨多層元件傳遞，可新增輕量 React Context；目前主專案狀態只在 `DashboardLayout.jsx` 使用，維持 `useState` 風險最低。

### Menu config

- React 範例在 `AppSidebar.tsx` 內宣告 `navItems`、`othersItems`，支援分類、圖示、子選單、active 判斷。
- 主專案已使用 `resources/js/config/sidebar.ts` 抽離設定，方向更適合 ERP 權限與模組擴充。
- 建議：延續主專案 `SidebarItem` 型別，漸進加入 `section`、`permission`、`badge` 等欄位；不要把範例的硬編碼 menu 搬入元件。

### Components

- 可借鏡元件類型：統計卡、表格卡、圖表卡、表單輸入、徽章、警示元件。
- 建議只移植互動邏輯與視覺規格，不直接複製 demo 資料或 ecommerce 命名。
- ERP 優先元件：`MetricCard`、`DataTableCard`、`RiskBadge`、`CostBreakdownCard`、`VehicleStatusPill`，以庫存成本、維修費、銷售佣金為資料主軸。

## TailAdmin Laravel 可借鏡的後端結構

- `routes/web.php` 以命名路由對應各頁面，適合作為主專案 route name 規劃參考。
- `resources/views/layouts`、`resources/views/components` 展示了 layout、header、sidebar、card、table 的分層方式，可作為「元件責任切分」參考。
- `resources/js/components/chart` 顯示圖表初始化可獨立封裝；主專案應改以 React component 或 hook 實作，而非 Blade script 初始化。
- Laravel 範例後端主要是展示型路由與 Blade view，沒有可直接套用的 ERP domain service；可參考命名清晰度，不建議搬遷架構。

## 不適合直接搬進主專案的內容

- Blade：主專案是 Inertia + React，直接搬入 `*.blade.php` 元件會形成兩套 UI 系統。
- Alpine：Laravel 範例使用 Alpine store 管理 sidebar/theme；主專案應維持 React state/context，避免狀態來源分裂。
- Next.js routing：若參考其他 TailAdmin React/Next 版本，其檔案式 routing、layout convention 不適用於目前 Laravel route + Inertia render。
- React Router：`free-react-tailwind-admin-dashboard` 使用 `react-router` 的 `Outlet`、`Link`、`useLocation`；主專案應保留 Inertia `Link` 與 Laravel named route。
- Demo assets/data：範例 ecommerce、profile、calendar 資料不符合中古車 ERP 語意，不宜直接導入。

## 建議採用的最終結構（最小風險）

維持現有 Laravel + Inertia + React + Vite 結構，只做漸進式補強：

```text
resources/js/
  Layouts/
    DashboardLayout.jsx        # 保留主外殼與 Inertia Head
  Components/Dashboard/
    Header.jsx                 # 保留，必要時微調互動
    Sidebar.jsx                # 保留，逐步支援 section / badge / permission
    MobileSidebar.jsx          # 保留行動版抽屜
    cards/                     # 未來新增 ERP 指標卡
    tables/                    # 未來新增 ERP 表格卡
    badges/                    # 未來新增風險/狀態標籤
  config/
    sidebar.ts                 # 作為單一 menu config 來源
  Pages/
    Dashboard/
      index.jsx                # 維持 Inertia page
routes/
  web.php                      # 保留 Laravel named routes + Inertia::render
app/Http/Middleware/
  HandleInertiaRequests.php    # 僅共享必要 auth/permission/menu props
```

## 採用原則

- 保留 Inertia 作為頁面邊界，不導入 React Router 或 Next.js routing。
- 保留 React 狀態管理，不導入 Alpine。
- 保留現有 `DashboardLayout.jsx` 與 `Sidebar.jsx`，只做小步增量。
- 後端只以 Laravel named routes 與 Inertia shared props 補強，不搬 TailAdmin Blade view。
- 視覺上採用 TailAdmin 的清楚層級、側欄收合、卡片密度與表格節奏；語意與資料模型改為中古車 ERP。

## 結論

最適合的路線是「借鏡 TailAdmin React 的 layout/sidebar/component 分工，參考 TailAdmin Laravel 的 route 命名與 Blade 分層思維，但不直接搬入 Blade、Alpine、React Router 或 Next.js routing」。主專案目前架構已接近目標，應以 `sidebar.ts` 為單一選單來源，逐步增加 ERP 專用 dashboard components，維持最小風險與低重構成本。
