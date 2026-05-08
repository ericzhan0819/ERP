const toArray = (value) => (Array.isArray(value) ? value : []);

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
export const resolveModules = (pageProps = {}) => {
    const authUser = pageProps?.auth?.user ?? {};
    const merged = [
        ...toArray(pageProps?.modules),
        ...toArray(pageProps?.auth?.modules),
        ...toArray(authUser?.modules),
        ...toArray(authUser?.module_access),
        ...toArray(authUser?.module_names),
    ];

    return [...new Set(normalizeArray(merged).filter(Boolean))];
};

/**
 * 角色判斷：支援單一角色或多角色陣列。
 */
export const hasRole = (currentRole, requiredRoles = []) => {
    const required = normalizeArray(requiredRoles);
    if (required.length === 0) return true;
    if (!currentRole || typeof currentRole !== 'string') return false;
    return required.includes(currentRole);
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
    const modules = new Set(normalizeArray(subject?.modules));

    // 簡寫：can(ctx, 'permission.name')
    if (typeof requirement === 'string') {
        return permissions.has(requirement);
    }

    if (!requirement || typeof requirement !== 'object') {
        return true;
    }

    const requiredPermission = typeof requirement.permission === 'string' ? requirement.permission : null;
    const requiredModule = typeof requirement.module === 'string' ? requirement.module : null;
    const requiredModules = normalizeArray(requirement.modules);
    const requiredRoles = normalizeArray(requirement.role ?? requirement.roles);

    if (requiredRoles.length > 0 && !hasRole(role, requiredRoles)) return false;
    if (requiredPermission && !permissions.has(requiredPermission)) return false;
    if (requiredModule && !modules.has(requiredModule)) return false;
    if (requiredModules.length > 0 && !requiredModules.some((moduleKey) => modules.has(moduleKey))) return false;

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

const getRequiredModules = (item = {}) => {
    return Array.isArray(item?.modules) ? item.modules : normalizeArray(item?.module);
};

/**
 * 依權限遞迴過濾 Sidebar 項目，保留巢狀結構。
 */
export const filterSidebarByPermission = (items = [], userPermissions = [], currentRole = null, userModules = []) => {
    const subject = {
        role: currentRole,
        permissions: userPermissions,
        modules: userModules,
    };

    return toArray(items)
        .map((item) => {
            const filteredChildren = filterSidebarByPermission(item?.children ?? [], userPermissions, currentRole, userModules);
            return {
                ...item,
                children: filteredChildren,
            };
        })
        .filter((item) => {
            const requiredPermissions = getRequiredPermissions(item);
            const requiredRoles = getRequiredRoles(item);
            const requiredModules = getRequiredModules(item);

            const canSeeSelf =
                hasAnyPermission(userPermissions, requiredPermissions) &&
                can(subject, {
                    role: requiredRoles.length > 0 ? requiredRoles : undefined,
                    modules: requiredModules,
                });
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
