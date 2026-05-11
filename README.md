# ERP SaaS Platform

企業內部營運與管理系統（ERP SaaS Platform）。

本專案以 Laravel、Inertia.js、React 與 TailwindCSS 建構，提供模組化、可擴充的企業營運平台，整合企業網站與 ERP 後台管理系統。

系統架構以：

- Module Registry
- RBAC（Role-Based Access Control）
- Dynamic Module Access
- Responsive Dashboard UI

為核心，支援企業後台模組化擴充與權限管理。

---

# System Status

目前開發中（Early Development）。

核心後台框架、模組系統與權限地基已完成，後續將逐步擴充業務模組。

---

# Features

## Frontend

- Welcome Landing Page
- Responsive Design (RWD)
- Modern Enterprise UI
- Hidden Login Entry

---

## ERP Dashboard

### Dashboard UI

- Dashboard Layout
- Sidebar Navigation
- Mobile Sidebar
- Hamburger Menu
- KPI Widgets
- Recent Activities
- System Status
- Quick Actions

### Access Control System

- Spatie RBAC Integration
- Role-Based Access Control
- Direct Permission Override
- DB-based Module Registry
- Dynamic Module Visibility
- Module Access Middleware
- Dynamic Sidebar Rendering
- Staff Permission Management
- Dynamic Module Enable / Disable

---

# Architecture

## Module Registry

系統模組由資料庫 `modules` table 管理。

每個模組包含：

- key
- label
- route_name
- base_permission
- icon
- sort_order
- is_active

支援：

- 動態模組開關
- Sidebar 自動更新
- Route Access Control

---

## RBAC Model

系統採用：

- Roles
- Permissions
- Direct Permissions

### Permission Design

- Role = 主要身份
- Direct Permission = 特例覆蓋

### Module Access

模組入口權限由：

```txt
modules.base_permission
```

控制。

頁面內操作權限則由：

```txt
permission actions
```

控制。

---

# Tech Stack

## Backend

- Laravel
- Laravel Sail
- Spatie Laravel Permission

## Frontend

- React
- Inertia.js
- TailwindCSS
- Vite

## Database

- MySQL

---

# Development

## Start Sail

```bash
./vendor/bin/sail up -d
```

## Start Vite

```bash
npm run dev
```

---

# Database

## Fresh Migration + Seed

```bash
./vendor/bin/sail artisan migrate:fresh --seed
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

# Permission Principles

- Sidebar 不自行判斷角色與權限
- 所有模組可見性由後端統一計算
- 前端僅消費 `auth.visibleModules`
- 模組入口由 middleware 控制
- 權限判斷集中於 PermissionService

---

# License

(c) 2026 OO INTERNATIONAL. All rights reserved.