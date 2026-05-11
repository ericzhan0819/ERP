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
 * 側欄唯一選單來源：僅保留 Dashboard 與測試模組入口。
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
        key: 'test-module.index',
        label: '測試模塊',
        icon: 'test-module',
        routeName: 'employee-system.test-module',
        moduleKey: 'test-module',
        children: [],
    },
];
