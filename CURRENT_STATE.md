# 目前專案狀態

## 狀態摘要

- 目前專案是純 UI Demo，沒有真實 Auth、User、Role、Permission 流程。
- `HandleInertiaRequests` 固定共享 `auth.user = null`。
- 登入頁只做前端展示，送出後直接導向展示主控台。
- Sidebar 是靜態 demo 選單，只保留「總覽」，不連接權限系統。

## 目前保留路由

- `GET /`：顯示 `Welcome`。
- `GET /login`：顯示 `Auth/Login`，route name 為 `login`。
- `GET /employee-system`：顯示 `Dashboard/index`，route name 為 `employee-system.overview`。
- `GET /dashboard`：重新導向 `/employee-system`，route name 為 `dashboard`。

## 目前保留頁面與 Layout

### 頁面

- `resources/js/Pages/Welcome.jsx`：官網／入口展示頁。
- `resources/js/Pages/Auth/Login.jsx`：登入 UI Demo，不送後端認證請求。
- `resources/js/Pages/Dashboard/index.jsx`：營運總覽 UI Demo，資料為前端靜態展示。

### Layout

- `resources/js/Layouts/DashboardLayout.jsx`：目前主控台實際使用的版型。
- `resources/js/Layouts/AuthenticatedLayout.jsx`：仍存在，但依賴 `auth.user` 與已不存在的 auth route，不是目前 demo 主流程。
- `resources/js/Layouts/GuestLayout.jsx`：仍存在，屬於基礎 guest 版型。

### Dashboard 元件

- `resources/js/Components/Dashboard/Header.jsx`：使用 demo 使用者資料。
- `resources/js/Components/Dashboard/Sidebar.jsx`：桌面側欄。
- `resources/js/Components/Dashboard/MobileSidebar.jsx`：行動版側欄。
- `resources/js/config/sidebar.ts`：靜態 sidebar 設定，目前只含 `dashboard` 項目。

## 已移除／目前不存在的後端功能

- 沒有 `User` model。
- 沒有 Role、Permission、ModulePermission 相關 model、controller、migration、seeder 或設定檔。
- 沒有登入、登出、註冊、密碼重設、profile 等 Auth routes。
- 沒有使用者、角色、權限資料表 migration。
- `database/seeders/DatabaseSeeder.php` 不建立任何 user、role、permission seed data。
- `app/Http/Controllers/` 目前只保留基底 `Controller.php`。

## 目前資料庫與設定狀態

- `database/migrations/` 只保留 cache 與 jobs migration。
- `database/seeders/` 只保留 `DatabaseSeeder.php`。
- `config/` 保留 Laravel 基礎設定：`app.php`、`auth.php`、`cache.php`、`database.php`、`filesystems.php`、`logging.php`、`mail.php`、`queue.php`、`services.php`、`session.php`。
- 沒有 `config/permissions.php`。

## 未來若要重建登入與權限系統

建議從以下項目開始，逐步恢復，不要直接假設目前已存在：

1. 新增 `app/Models/User.php` 與 users table migration。
2. 建立登入／登出 routes 與對應 controller。
3. 調整 `app/Http/Middleware/HandleInertiaRequests.php`，共享真實 `auth.user`。
4. 規劃 roles、permissions、role_user、permission_role 或 module_permissions 等資料表。
5. 建立 role／permission seeders，並由 `DatabaseSeeder.php` 明確呼叫。
6. 將 `resources/js/config/sidebar.ts` 從靜態 demo 選單改為可依權限過濾的設定。
7. 檢查 `AuthenticatedLayout.jsx` 內仍引用的 `profile.edit`、`logout` 等 route，確認重建後才接回主流程。
