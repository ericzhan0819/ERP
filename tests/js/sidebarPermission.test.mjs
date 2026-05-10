import test from 'node:test';
import assert from 'node:assert/strict';
import { filterSidebarByPermission } from '../../resources/js/utils/permission.js';

const sidebarItems = [
    { id: 'back-to-dashboard', title: '回到儀表板', icon: 'dashboard', route: 'employee-system.overview', children: [] },
    { id: 'dashboard', title: 'Operations Overview', icon: 'dashboard', route: 'employee-system.overview', children: [] },
    { id: 'staff', title: '員工管理', icon: 'employees', route: 'staff-management.index', roles: ['Admin'], children: [] },
];

/**
 * 技術註解：以最小風險方式直接驗證 sidebar 過濾規則，聚焦角色可見項目。
 */
test('Admin 可見儀表板與員工管理', () => {
    const result = filterSidebarByPermission(sidebarItems, {
        id: 1,
        role: 'Admin',
        permissions: [],
    });

    const ids = result.map((item) => item.id);
    assert.ok(ids.includes('back-to-dashboard'));
    assert.ok(ids.includes('staff'));
});

test('Manager 僅可見儀表板，不可見員工管理', () => {
    const result = filterSidebarByPermission(sidebarItems, {
        id: 2,
        role: 'Manager',
        permissions: [],
    });

    const ids = result.map((item) => item.id);
    assert.ok(ids.includes('back-to-dashboard'));
    assert.ok(!ids.includes('staff'));
});

test('Staff 僅可見儀表板，不可見員工管理', () => {
    const result = filterSidebarByPermission(sidebarItems, {
        id: 3,
        role: 'Staff',
        permissions: [],
    });

    const ids = result.map((item) => item.id);
    assert.ok(ids.includes('back-to-dashboard'));
    assert.ok(!ids.includes('staff'));
});
