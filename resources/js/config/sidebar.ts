/**
 * 技術註解：前端設定僅負責 icon component key 映射，
 * 不可承擔權限、可見性、route 或模組文案來源，避免與後端 module registry 漂移。
 */
export type SidebarIconKey = string;

/**
 * 技術註解：此映射為 UI 安全 fallback，當後端傳入 icon key 未配置時，
 * 由 Sidebar 退回 default icon 以維持可用性，不影響授權判斷。
 */
export const SIDEBAR_DEFAULT_ICON_KEY: SidebarIconKey = 'dashboard';
