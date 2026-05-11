const toArray = (value) => (Array.isArray(value) ? value : []);

const normalizeIdArray = (value) => {
    if (!Array.isArray(value)) return [];
    return value
        .map((item) => Number(item))
        .filter((item) => Number.isInteger(item) && item > 0);
};

/**
 * 將單值/陣列轉為乾淨字串陣列，避免 null/空字串污染判斷。
 */
const normalizeArray = (value) => {
    if (Array.isArray(value)) {
        return value.filter((item) => typeof item === 'string' && item.trim() !== '').map((item) => item.trim());
    }

    if (typeof value === 'string' && value.trim() !== '') {
        return [value.trim()];
    }

    return [];
};

/**
 * 角色字串正規化：統一去空白並轉小寫，避免大小寫不一致造成誤判。
 */
const normalizeRoleValue = (value) => {
    if (typeof value !== 'string') return null;
    const normalized = value.trim().toLowerCase();
    return normalized === '' ? null : normalized;
};

/**
 * 從多個可能欄位來源合併權限，避免後端欄位命名差異造成前端崩潰。
 */
export const resolvePermissions = (pageProps = {}) => {
    const authUser = pageProps?.auth?.user ?? {};

    const merged = [
        ...toArray(pageProps?.permissions),
        ...toArray(pageProps?.auth?.permissions),
        ...toArray(authUser?.permissions),
        ...toArray(authUser?.permission_names),
    ];

    return [...new Set(merged.filter(Boolean))];
};

/**
 * 解析目前登入者角色（單一主角色）。
 */
export const resolveRole = (pageProps = {}) => {
    const authUser = pageProps?.auth?.user ?? {};
    const roleCandidates = [
        authUser?.role,
        authUser?.role_name,
        authUser?.current_role,
        toArray(authUser?.roles)[0],
        toArray(pageProps?.auth?.roles)[0],
    ];

    const role = roleCandidates.find((item) => typeof item === 'string' && item.trim() !== '');
    return role ? role.trim() : null;
};

/**
 * 解析模組存取清單，支援多種欄位命名。
 */
/**
 * 角色判斷：支援單一角色或多角色陣列。
 */
export const hasRole = (currentRole, requiredRoles = []) => {
    const required = normalizeArray(requiredRoles)
        .map((role) => normalizeRoleValue(role))
        .filter(Boolean);
    if (required.length === 0) return true;
    const normalizedCurrentRole = normalizeRoleValue(currentRole);
    if (!normalizedCurrentRole) return false;
    return required.includes(normalizedCurrentRole);
};

/**
 * 核心判斷工具：
 * - permission：檢查單一權限
 * - module：檢查模組存取
 * - role/roles：檢查角色
 */
export const can = (
    subject = {},
    requirement,
) => {
    const role = subject?.role ?? null;
    const permissions = new Set(normalizeArray(subject?.permissions));

    // 簡寫：can(ctx, 'permission.name')
    if (typeof requirement === 'string') {
        return permissions.has(requirement);
    }

    if (!requirement || typeof requirement !== 'object') {
        return true;
    }

    const requiredPermission = typeof requirement.permission === 'string' ? requirement.permission : null;
    const requiredRoles = normalizeArray(requirement.role ?? requirement.roles);

    if (requiredRoles.length > 0 && !hasRole(role, requiredRoles)) return false;
    if (requiredPermission && !permissions.has(requiredPermission)) return false;
    return true;
};

/**
 * 檢查目前使用者是否擁有任一需求權限；需求為空視為可見。
 */
export const hasAnyPermission = (userPermissions = [], requiredPermissions = []) => {
    const required = toArray(requiredPermissions);
    if (required.length === 0) return true;

    const permissionSet = new Set(toArray(userPermissions));
    return required.some((permission) => permissionSet.has(permission));
};

/**
 * 兼容舊欄位 `permissions` 與新欄位 `permission`。
 */
const getRequiredPermissions = (item = {}) => {
    return Array.isArray(item?.permission) ? item.permission : toArray(item?.permissions);
};

const getRequiredRoles = (item = {}) => {
    return Array.isArray(item?.roles) ? item.roles : normalizeArray(item?.role);
};

const canSeeItem = (item = {}, user = {}) => {
    const role = user?.role ?? null;
    const permissions = normalizeArray(user?.permissions);
    const userId = Number(user?.id ?? 0);

    const requiredRoles = normalizeArray(item?.roles);
    const requiredPermissions = normalizeArray(item?.permissions);
    const requiredUsers = normalizeIdArray(item?.users);

    const roleOk = requiredRoles.length === 0 || hasRole(role, requiredRoles);
    const permissionOk =
        requiredPermissions.length === 0 || requiredPermissions.some((permission) => permissions.includes(permission));
    const userOk = requiredUsers.length === 0 || requiredUsers.includes(userId);

    return roleOk && permissionOk && userOk;
};

/**
 * 依權限遞迴過濾 Sidebar 項目，保留巢狀結構。
 */
export const filterSidebarByPermission = (items = [], user = {}) => {
    return toArray(items)
        .map((item) => {
            const filteredChildren = filterSidebarByPermission(item?.children ?? [], user);
            return {
                ...item,
                children: filteredChildren,
            };
        })
        .filter((item) => {
            const canSeeSelf = canSeeItem(item, user);
            const hasVisibleChildren = toArray(item?.children).length > 0;
            return canSeeSelf || hasVisibleChildren;
        });
};

/**
 * Widget 顯示判斷，供 Dashboard 模組快速共用。
 */
export const canShowWidget = (userPermissions = [], requiredPermissions = []) => {
    return hasAnyPermission(userPermissions, requiredPermissions);
};

/**
 * 依 modulePermissions 合併 Sidebar 設定：
 * - 有對應 module_key：覆蓋 roles/users/enabled（permissions 維持 sidebar 原值）
 * - 無對應資料：保留 sidebar 原設定（fallback）
 * - enabled=false：直接排除該模組
 */
export const mergeSidebarWithModulePermissions = (sidebarItems = [], modulePermissions = []) => {
    const moduleList = toArray(modulePermissions);

    // 空陣列時完全 fallback，維持既有行為。
    if (moduleList.length === 0) {
        return toArray(sidebarItems);
    }

    const moduleMap = new Map(
        moduleList
            .filter((item) => item && typeof item === 'object' && typeof item.module_key === 'string')
            .map((item) => [item.module_key, item]),
    );

    return toArray(sidebarItems)
        .map((item) => {
            const override = moduleMap.get(item?.id);
            if (!override) return item;

            const nextItem = {
                ...item,
                roles: Array.isArray(override?.roles) ? override.roles : item.roles,
                users: Array.isArray(override?.users) ? override.users : item.users,
                enabled: override?.enabled,
            };

            return nextItem;
        })
        .filter((item) => item?.enabled !== false)
        .map(({ enabled, ...item }) => item);
};
