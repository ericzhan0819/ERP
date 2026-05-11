/**
 * 側欄項目型別：moduleKey 對應後端 auth.visibleModules，不在前端判斷角色。
 */
export type SidebarItem = {
    key: string;
    label: string;
    icon: string;
    routeName?: string;
    href?: string;
    moduleKey?: string;
    children?: SidebarItem[];
};

/**
 * 側欄 UI 對應表：可見性不可由此檔判斷，只能由 auth.visibleModules 決定。
 */
export const sidebarItems: SidebarItem[] = [
    {
        key: 'dashboard.overview',
        label: '總覽',
        icon: 'dashboard',
        routeName: 'employee-system.overview',
        moduleKey: 'dashboard',
        children: [],
    },
    {
        key: 'staff-permission.index',
        label: '員工權限',
        icon: 'employees',
        routeName: 'employee-system.staff-permissions.index',
        moduleKey: 'staff-permission',
        children: [],
    },
    {
        key: 'test-module.index',
        label: '測試模塊',
        icon: 'test-module',
        routeName: 'employee-system.test-module',
        moduleKey: 'test-module',
        children: [],
    },
];
