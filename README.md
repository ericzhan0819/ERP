OO 國際車業內部管理系統

專為中古車業設計的營運中樞，核心在於建立絕對透明的收車與財務秩序。
系統核心邏輯

    網格導向：基於 Swiss Style 的嚴謹數據佈局

    作業序列：涵蓋收車、整備、銷售、帳務之全流程閉環

    安全入口：前端採用物理底部隱藏式登入節點

技術架構

    框架：Laravel 13 / Inertia.js (React)

    資料庫：MySQL

    語言規範：PHP 8.4+, 繁體中文程式碼註解

部署流程

    安裝依賴：
    composer install
    npm install

    環境配置：
    cp .env.example .env
    php artisan key:generate

    啟動環境：
    npm run dev
    php artisan serve

    2026-05-08

    升級核心框架至 Laravel 13.8.0 與 PHP 8.4.20。

    實作 Swiss Style 響應式佈局，優化在庫總量與成交筆數之數據展示層。

    調整管理登入路徑，將入口節點遷移至頁面底部物理區塊，防止行動端誤觸。

    補全全域程式碼繁體中文註解，強化系統可維護性。

版權宣告

(c) 2026 OO INTERNATIONAL. 保留所有權利。