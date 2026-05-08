export const sidebarItems = [
    {
        key: 'dashboard.overview',
        title: '總覽',
        route: 'dashboard',
        icon: 'home',
        permissions: [],
    },
    {
        key: 'dashboard.staff.management',
        title: '員工管理',
        route: 'staff-management.index',
        icon: 'shield',
        permissions: ['permissions.view', 'roles.view', 'admin.access'],
    },
];
