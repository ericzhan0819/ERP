/**
 * 技術註解：統一稽核頁時間呈現格式，避免各頁面自行格式化導致顯示不一致。
 * 安全註解：僅處理顯示字串，不改動原始資料內容，避免影響後端稽核資料完整性。
 */
export function formatDateTime(value) {
    if (!value) {
        return '-';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '-';
    }

    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hour = String(date.getHours()).padStart(2, '0');
    const minute = String(date.getMinutes()).padStart(2, '0');

    return `${year}/${month}/${day} ${hour}:${minute}`;
}

/**
 * 技術註解：統一日期欄位的唯讀顯示格式，避免後端日期序列化為 ISO datetime 時裸顯到車輛 UI。
 * 安全註解：僅轉換顯示字串，不回寫或改動原始資料，避免影響成本、銷售等財務紀錄完整性。
 */
export function formatDate(value) {
    if (!value) {
        return '—';
    }

    const rawValue = String(value);
    const isoDateMatch = rawValue.match(/^\d{4}-\d{2}-\d{2}/);

    if (isoDateMatch) {
        return isoDateMatch[0];
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}
