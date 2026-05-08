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

## 開發紀錄

### V1.0.0 (2026-05-08)

升級核心框架並完成前後端整合。

完成新版企業化 Welcome 首頁設計，建立響應式版面與整體視覺風格。

完成隱藏式登入入口設計，作為後台 ERP 系統入口。

完成基礎權限架構與登入流程調整，移除公開註冊功能。

---

### V1.1.0 (2026-05-09)

完成 ERP Dashboard 核心架構建置。

新增模組化 Dashboard Layout，建立 Sidebar、Mobile Sidebar 與 Header 系統，統一後台與 Welcome 頁面的企業化視覺語言。

實作行動端 Hamburger Menu 收合邏輯，提升移動裝置操作效率。

建立 Dashboard Widgets 基礎架構，包含 KPI Cards、Recent Activities、System Status 與 Quick Actions 區塊。

完成三階權限系統前端顯示邏輯，依據角色動態控制 Sidebar 與 Dashboard 模組顯示內容。

優化 Dashboard 響應式版面配置，支援 Desktop、Tablet 與 Mobile 顯示模式。

---

## 已知問題

* 行動端仍存在白邊 overflow 問題
* 部分 glow effect 於小尺寸裝置會產生溢出
* Sidebar 分隔線尚未與主介面完全對齊
* 權限系統仍存在部分顯示異常
* staff-management 路由尚未正確連線

---

## 開發計畫

* 修復權限系統
* 修復 Mobile UI 問題
* 建立 Vehicles Module
* 建立 CRM Module
* 建立 Finance Module

---

## 版權宣告

* (c) 2026 OO INTERNATIONAL. 保留所有權利。