import { Link } from '@inertiajs/react';

const itemBaseClass = 'group flex items-center gap-3 rounded-xl border px-3 py-2 text-sm tracking-wide transition-all';

const iconMap = {
    dashboard: (
        <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8">
            <path d="M3 10.5 12 3l9 7.5" strokeLinecap="round" strokeLinejoin="round" />
            <path d="M5.25 9.75V21h13.5V9.75" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    ),
    vehicles: (
        <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8">
            <path d="M4 14h16" strokeLinecap="round" />
            <path d="M6 14 8 9h8l2 5" strokeLinecap="round" strokeLinejoin="round" />
            <circle cx="8" cy="17" r="1.5" />
            <circle cx="16" cy="17" r="1.5" />
        </svg>
    ),
    customers: (
        <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8">
            <circle cx="12" cy="8" r="3" />
            <path d="M5 20a7 7 0 0 1 14 0" strokeLinecap="round" />
        </svg>
    ),
    orders: (
        <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8">
            <rect x="4" y="4" width="16" height="16" rx="2" />
            <path d="M8 9h8M8 13h8M8 17h5" strokeLinecap="round" />
        </svg>
    ),
    employees: (
        <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8">
            <circle cx="9" cy="8" r="2.5" />
            <circle cx="16" cy="9" r="2" />
            <path d="M4.5 19a5 5 0 0 1 9 0M14 19a4 4 0 0 1 6 0" strokeLinecap="round" />
        </svg>
    ),
    finance: (
        <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8">
            <path d="M12 3v18" strokeLinecap="round" />
            <path d="M16 7.5c0-1.7-1.8-3-4-3s-4 1.3-4 3 1.8 3 4 3 4 1.3 4 3-1.8 3-4 3-4-1.3-4-3" strokeLinecap="round" />
        </svg>
    ),
    settings: (
        <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8">
            <circle cx="12" cy="12" r="3" />
            <path d="M19.4 15a1 1 0 0 0 .2 1.1l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1 1 0 0 0-1.1-.2 1 1 0 0 0-.6.9V20a2 2 0 1 1-4 0v-.2a1 1 0 0 0-.6-.9 1 1 0 0 0-1.1.2l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1 1 0 0 0 .2-1.1 1 1 0 0 0-.9-.6H4a2 2 0 1 1 0-4h.2a1 1 0 0 0 .9-.6 1 1 0 0 0-.2-1.1l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1 1 0 0 0 1.1.2 1 1 0 0 0 .6-.9V4a2 2 0 1 1 4 0v.2a1 1 0 0 0 .6.9 1 1 0 0 0 1.1-.2l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1 1 0 0 0-.2 1.1 1 1 0 0 0 .9.6H20a2 2 0 1 1 0 4h-.2a1 1 0 0 0-.9.6Z" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    ),
};

/**
 * 以 route name 判定 active，避免 null route 造成錯誤。
 */
const isRouteActive = (routeName) => {
    if (!routeName) return false;
    return route().current(routeName);
};

/**
 * 遞迴判斷子節點是否為 active。
 */
const isItemActive = (item) => {
    return isRouteActive(item?.route) || (item?.children ?? []).some((child) => isItemActive(child));
};

/**
 * Mobile 單一節點渲染：點擊可導頁項目後即自動關閉側欄。
 */
const MobileSidebarNode = ({ item, onClose, level = 0 }) => {
    const active = isItemActive(item);
    const hasChildren = (item.children ?? []).length > 0;
    const paddingLeftClass = level > 0 ? 'pl-6' : '';

    if (item.route) {
        return (
            <div className="space-y-2">
                <Link
                    href={route(item.route)}
                    onClick={onClose}
                    className={`${itemBaseClass} ${paddingLeftClass} ${
                        active
                            ? 'border-cyan-300/40 bg-cyan-300/10 text-cyan-200'
                            : 'border-white/10 bg-white/[0.02] text-zinc-300 hover:border-cyan-300/30 hover:text-cyan-200'
                    }`}
                >
                    <span className="text-cyan-300/90">{iconMap[item.icon] ?? iconMap.dashboard}</span>
                    <span>{item.title}</span>
                </Link>

                {hasChildren && (
                    <div className="space-y-2">
                        {item.children.map((child) => (
                            <MobileSidebarNode key={child.id} item={child} onClose={onClose} level={level + 1} />
                        ))}
                    </div>
                )}
            </div>
        );
    }

    return (
        <div className="space-y-2">
            <div
                className={`${itemBaseClass} ${paddingLeftClass} ${
                    active ? 'border-cyan-300/30 bg-cyan-300/5 text-cyan-200' : 'border-white/10 bg-white/[0.02] text-zinc-300'
                }`}
            >
                <span className="text-cyan-300/90">{iconMap[item.icon] ?? iconMap.dashboard}</span>
                <span>{item.title}</span>
            </div>

            {hasChildren && (
                <div className="space-y-2">
                    {item.children.map((child) => (
                        <MobileSidebarNode key={child.id} item={child} onClose={onClose} level={level + 1} />
                    ))}
                </div>
            )}
        </div>
    );
};

export default function MobileSidebar({ open = false, onClose, items = [] }) {
    return (
        <div
            className={`fixed inset-0 z-50 lg:hidden transition-opacity duration-200 ${open ? 'pointer-events-auto opacity-100' : 'pointer-events-none opacity-0'}`}
            aria-hidden={!open}
        >
            <button
                type="button"
                onClick={onClose}
                className="absolute inset-0 bg-[#050816]/70 backdrop-blur-sm transition-opacity duration-200"
                aria-label="Close sidebar"
            />

            <aside
                className={`relative h-full w-72 max-w-[86vw] border-r border-white/10 bg-[#0B1120] p-4 shadow-2xl transition-transform duration-300 ease-out ${
                    open ? 'translate-x-0' : '-translate-x-full'
                }`}
            >
                <div className="mb-4 flex items-center justify-between border-b border-white/10 pb-4">
                    <p className="text-[10px] font-semibold uppercase tracking-[0.35em] text-cyan-300/70">ERP Dashboard</p>
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-md border border-white/15 px-3 py-2 text-xs text-zinc-300 active:scale-[0.98]"
                    >
                        關閉
                    </button>
                </div>

                <nav className="space-y-2 overflow-y-auto pb-6">
                    {items.map((item) => (
                        <MobileSidebarNode key={item.id} item={item} onClose={onClose} />
                    ))}
                </nav>
            </aside>
        </div>
    );
}
