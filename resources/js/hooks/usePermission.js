import { useMemo } from 'react';
import { usePage } from '@inertiajs/react';
import { can as canHelper, hasRole as hasRoleHelper, resolvePermissions, resolveRole } from '@/utils/permission';

/**
 * 可重用權限 Hook：
 * - 沿用 Inertia page props 既有 auth/user 資料來源
 * - 統一提供 role / permissions 給 UI 使用
 * - 暴露 can()、hasRole() 讓 Sidebar、Widget、按鈕可直接判斷
 */
export default function usePermission() {
    const page = usePage();

    const role = useMemo(() => resolveRole(page.props), [page.props]);
    const permissions = useMemo(() => resolvePermissions(page.props), [page.props]);

    const subject = useMemo(
        () => ({
            role,
            permissions,
        }),
        [role, permissions],
    );

    /**
     * 封裝判斷入口，避免各頁重複建立 subject。
     */
    const can = (requirement) => canHelper(subject, requirement);

    /**
     * 角色判斷入口：支援單角色字串與多角色陣列。
     */
    const hasRole = (requiredRoles) => hasRoleHelper(role, requiredRoles);

    return {
        role,
        permissions,
        can,
        hasRole,
    };
}
