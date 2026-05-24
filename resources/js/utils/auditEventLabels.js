// 稽核事件中文對照：保留資料庫英文事件鍵，僅在前端顯示層做本地化。
const auditEventLabels = {
    'auth.login.success': '登入成功',
    'auth.login.failed': '登入失敗',
    'auth.login.inactive': '帳號停用登入失敗',
    'auth.logout': '登出',
    'vehicle.created': '車輛建立',
    'vehicle.updated': '車輛更新',
    'profile.updated': '個人資料更新',
};

/**
 * 將 event/action 鍵轉為中文標籤。
 * 風險控管：若找不到對照時回退原始值，避免誤隱藏關鍵事件資訊。
 */
export function formatAuditEvent(value) {
    return auditEventLabels[value] ?? value ?? '-';
}

export { auditEventLabels };

