/**
 * 側欄子項目型別：支援巢狀資料結構。
 */
export type SidebarItem = {
    id: string;
    title: string;
    icon: string;
    route: string | null;
    permissions?: string[];
    roles?: string[];
    users?: number[];
    children?: SidebarItem[];
};

/**
 * 側欄模組設定：
 * - Admin：全部可見
 * - Manager：顯示部分管理功能
 * - Staff：僅基礎功能
 */
export const sidebarItems: SidebarItem[] = [
    {
        id: 'dashboard',
        title: '總覽',
        icon: 'dashboard',
        route: 'employee-system.overview',
        children: [],
    },
    {
        id: 'staff',
        title: '員工管理',
        icon: 'employees',
        route: 'staff-management.index',
        // 技術註解：前端僅顯示 Admin，與後端路由 role:Admin 雙保護。
        roles: ['Admin'],
        children: [],
    },
    {
        id: 'permission-test-module',
        title: '測試模塊',
        icon: 'dashboard',
        route: 'permission-test-module',
        // 技術註解：僅作為權限可見性測試入口，不對既有 dashboard/staff 語意做任何變更。
        children: [],
    },
];
