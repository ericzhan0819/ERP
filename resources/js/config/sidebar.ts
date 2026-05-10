/**
 * 側欄子項目型別：支援巢狀資料結構。
 */
export type SidebarItem = {
    id: string;
    title: string;
    icon: string;
    route: string | null;
    permission: string[];
    roles?: string[];
    modules?: string[];
    children: SidebarItem[];
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
        title: 'Operations Overview',
        icon: 'dashboard',
        route: 'employee-system.overview',
        permission: [],
        roles: ['Admin', 'Manager', 'Staff'],
        modules: ['dashboard'],
        children: [],
    },
    {
        id: 'staff',
        title: '員工管理',
        icon: 'employees',
        route: 'staff-management.index',
        permission: [],
        // 技術註解：前端僅顯示 Admin，與後端路由 role:Admin 雙保護。
        roles: ['Admin'],
        modules: [],
        children: [],
    },
];
