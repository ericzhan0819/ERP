// 稽核事件中文對照：保留資料庫英文事件鍵，僅在前端顯示層做本地化。
const auditEventLabels = {
    'auth.login.success': '登入成功',
    'auth.login.failed': '登入失敗',
    'auth.login.inactive': '帳號停用登入失敗',
    'auth.logout': '登出',
    'vehicle.created': '新增車輛',
    'vehicle.create': '新增車輛',
    'vehicles.created': '新增車輛',
    'vehicle.updated': '車輛更新',
    'vehicle.update': '車輛更新',
    'vehicles.updated': '車輛更新',
    'profile.updated': '個人資料更新',
    'company_settings.updated': '更新公司設定',
    'company-settings.updated': '更新公司設定',
    'company_settings.update': '更新公司設定',
    'company-settings.update': '更新公司設定',
};

// 稽核模組中文對照：僅影響顯示，不改動資料庫儲存鍵值。
const auditModuleLabels = {
    'company-settings': '公司設定',
    company_settings: '公司設定',
    vehicles: '車輛管理',
    vehicle: '車輛管理',
};

// 舊版英文描述顯示相容：僅在顯示層轉譯，不回寫舊資料，避免批次改資料造成稽核可追溯性風險。
const auditDescriptionLabels = {
    'Vehicle created': '新增車輛',
    'Vehicle updated': '更新車輛資料',
};

/**
 * 將 event/action 鍵轉為中文標籤。
 * 風險控管：若找不到對照時回退原始值，避免誤隱藏關鍵事件資訊。
 */
export function formatAuditEvent(value) {
    return auditEventLabels[value] ?? value ?? '-';
}

/**
 * 將 metadata.module 鍵轉為中文標籤。
 * 風險控管：保留找不到 mapping 的原始值，避免稽核資訊遺失。
 */
export function formatAuditModule(value) {
    return auditModuleLabels[value] ?? value ?? '-';
}

/**
 * 將舊英文 description/detail 轉為中文，未命中時回退原值避免資訊遺失。
 */
export function formatAuditDescription(value) {
    return auditDescriptionLabels[value] ?? value ?? '-';
}

export { auditEventLabels, auditModuleLabels, auditDescriptionLabels };
