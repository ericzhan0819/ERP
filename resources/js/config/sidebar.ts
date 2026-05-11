/**
 * 側欄子項目型別：支援巢狀資料結構。
 */
export type SidebarItem = {
    id: string;
    title: string;
    icon: string;
    route: string | null;
    children?: SidebarItem[];
};

/**
 * MVP 側欄模組設定：純 UI Demo 僅保留可到達的展示入口，避免無後端路由的死連結。
 */
export const sidebarItems: SidebarItem[] = [
    {
        id: 'dashboard',
        title: '總覽',
        icon: 'dashboard',
        route: 'employee-system.overview',
        children: [],
    },
];
