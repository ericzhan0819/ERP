import test from 'node:test';
import assert from 'node:assert/strict';
import {
    filterSidebarByPermission,
    mergeSidebarWithModulePermissions,
} from '../../resources/js/utils/permission.js';

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

test('modulePermissions 空陣列時維持 fallback：sidebar 行為與原設定一致', () => {
    const merged = mergeSidebarWithModulePermissions(sidebarItems, []);

    const adminResult = filterSidebarByPermission(merged, {
        id: 1,
        role: 'Admin',
        permissions: [],
    });
    const staffResult = filterSidebarByPermission(merged, {
        id: 3,
        role: 'Staff',
        permissions: [],
    });

    const adminIds = adminResult.map((item) => item.id);
    const staffIds = staffResult.map((item) => item.id);

    assert.ok(adminIds.includes('dashboard'));
    assert.ok(staffIds.includes('dashboard'));
    assert.ok(adminIds.includes('staff'));
    assert.ok(!staffIds.includes('staff'));
});

test('enabled=false 的模組會從 sidebar 被排除', () => {
    const merged = mergeSidebarWithModulePermissions(sidebarItems, [
        { module_key: 'staff', enabled: false, roles: ['Admin'], users: [] },
    ]);

    const ids = merged.map((item) => item.id);
    assert.ok(!ids.includes('staff'));
    assert.ok(ids.includes('dashboard'));
});

test('有後端 modulePermissions 時，roles/users 覆蓋生效', () => {
    const merged = mergeSidebarWithModulePermissions(sidebarItems, [
        { module_key: 'staff', enabled: true, roles: ['Manager'], users: [2] },
    ]);

    const managerAllowed = filterSidebarByPermission(merged, {
        id: 2,
        role: 'Manager',
        permissions: [],
    });
    const managerDenied = filterSidebarByPermission(merged, {
        id: 999,
        role: 'Manager',
        permissions: [],
    });
    const adminDenied = filterSidebarByPermission(merged, {
        id: 1,
        role: 'Admin',
        permissions: [],
    });

    const allowedIds = managerAllowed.map((item) => item.id);
    const managerDeniedIds = managerDenied.map((item) => item.id);
    const adminDeniedIds = adminDenied.map((item) => item.id);

    assert.ok(allowedIds.includes('staff'));
    assert.ok(!managerDeniedIds.includes('staff'));
    assert.ok(!adminDeniedIds.includes('staff'));
    assert.ok(allowedIds.includes('dashboard'));
});
