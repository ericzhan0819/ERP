# README.md

# ERP

企業內部營運與管理系統。

本專案以 Laravel、Inertia.js、React 與 TailwindCSS 建構，整合公司官網與 ERP 後台，提供統一化的企業營運介面與模組化管理架構。

整體設計以現代企業化 UI 為核心，採用簡潔、高資訊密度與響應式設計，支援桌面與移動端裝置操作。

---

## 系統狀態

目前開發中（Early Development）

---

## 已完成功能

### 前台系統

* Welcome 首頁
* 響應式設計（RWD）
* 新版企業化 UI 配色
* 隱藏式登入入口

### ERP 後台

* Dashboard Layout
* Sidebar Navigation
* Mobile Sidebar
* Hamburger Menu 收合系統
* Dashboard Widgets
* KPI Cards
* Recent Activities
* System Status
* Quick Actions
* 三階權限系統基礎架構

---

## 技術棧

* Laravel
* Inertia.js
* React
* TailwindCSS
* Vite

---

## 權限 Seed 流程（MVP）

權限名稱單一來源：[`config/permissions.php`](config/permissions.php)

未來新增新模組權限時，必須先加入單一來源，再執行：

```bash
./vendor/bin/sail artisan db:seed --class=RoleSeeder
./vendor/bin/sail artisan permission:cache-reset
```

---

## 版權宣告

* (c) 2026 OO INTERNATIONAL. 保留所有權利。
