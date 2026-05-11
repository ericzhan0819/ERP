/**
 * 側欄項目型別：key 為穩定識別，moduleKey 僅預留給後續權限層使用。
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
 * MVP 側欄唯一選單來源：現階段只保留可到達入口，不在前端做角色或權限判斷。
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
];
