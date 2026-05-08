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
        title: 'Dashboard',
        icon: 'dashboard',
        route: 'dashboard',
        permission: [],
        roles: ['Admin', 'Manager', 'Staff'],
        modules: ['dashboard'],
        children: [],
    },
    {
        id: 'vehicles',
        title: 'Vehicles',
        icon: 'vehicles',
        route: null,
        permission: [],
        roles: ['Admin', 'Manager', 'Staff'],
        modules: ['vehicles'],
        children: [
            {
                id: 'vehicles.list',
                title: 'Vehicle List',
                icon: 'vehicles',
                route: null,
                permission: [],
                roles: ['Admin', 'Manager', 'Staff'],
                modules: ['vehicles'],
                children: [],
            },
        ],
    },
    {
        id: 'customers',
        title: 'Customers',
        icon: 'customers',
        route: null,
        permission: [],
        roles: ['Admin', 'Manager', 'Staff'],
        modules: ['customers'],
        children: [],
    },
    {
        id: 'orders',
        title: 'Orders',
        icon: 'orders',
        route: null,
        permission: [],
        roles: ['Admin', 'Manager'],
        modules: ['orders'],
        children: [],
    },
    {
        id: 'permission-management',
        title: '權限管理',
        icon: 'employees',
        route: 'staff-management.index',
        permission: ['permissions.view'],
        // 僅 Admin 顯示，並要求 permissions.view。
        roles: ['Admin'],
        modules: [],
        children: [],
    },
    {
        id: 'finance',
        title: 'Finance',
        icon: 'finance',
        route: null,
        permission: [],
        roles: ['Admin', 'Manager'],
        modules: ['finance'],
        children: [],
    },
    {
        id: 'settings',
        title: 'Settings',
        icon: 'settings',
        route: null,
        permission: [],
        roles: ['Admin'],
        modules: ['settings'],
        children: [],
    },
];
