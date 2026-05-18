# Theme Tokens 指南

## Token 定義位置
- CSS 變數：[`resources/css/app.css`](resources/css/app.css)
- Tailwind 映射：[`tailwind.config.js`](tailwind.config.js)

## 核心原則
- 僅使用語意 class（例如 `bg-surface`、`text-primary`、`border-default`）。
- 禁止在頁面直接硬編碼 `#hex`、`rgba(...)` 作為主要色彩。
- 互動狀態（hover/active/focus/disabled）統一走 token：
  - hover：`bg-hover`
  - active/selected：`bg-active` + `border-active`
  - focus：`focus:ring-focus` / `ring-focus`
  - disabled：以 `disabled:opacity-*` + `disabled:cursor-not-allowed` 為主

## Theme 切換機制
- 由 [`resources/js/Layouts/DashboardLayout.jsx`](resources/js/Layouts/DashboardLayout.jsx) 控制 `data-theme`。
- 使用 `localStorage` 記住使用者偏好（light/dark）。
- [`resources/js/Components/Dashboard/Header.jsx`](resources/js/Components/Dashboard/Header.jsx) 提供切換 UI。

## 擴充方式（最小安全改動）
1. 先在 [`resources/css/app.css`](resources/css/app.css) 的 `:root` 與 `[data-theme='dark']` 新增變數。
2. 若需 Tailwind 語意別名，再補到 [`tailwind.config.js`](tailwind.config.js)。
3. 元件只替換 class，不改業務邏輯、RBAC、路由或 API。

## 避免硬編碼清單
- 不要新增 `text-zinc-*`、`bg-slate-*`、`border-white/10` 當主題主色。
- 不要新增 `bg-[#...]`、`text-[#...]`、`shadow-[inset_...]` 作為選中態主視覺。
- 若必須保留狀態色（success/warning/danger），優先使用既有語意或最小範圍保留。
