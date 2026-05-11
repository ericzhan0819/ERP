import { Link } from '@inertiajs/react';

const itemBaseClass = 'group flex min-h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium tracking-wide transition-all duration-150';

const iconMap = {
    dashboard: (
        <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8">
            <path d="M3 10.5 12 3l9 7.5" strokeLinecap="round" strokeLinejoin="round" />
            <path d="M5.25 9.75V21h13.5V9.75" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    ),
    employees: (
        <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8">
            <circle cx="9" cy="8" r="2.5" />
            <circle cx="16" cy="9" r="2" />
            <path d="M4.5 19a5 5 0 0 1 9 0M14 19a4 4 0 0 1 6 0" strokeLinecap="round" />
        </svg>
    ),
};

/**
 * 以 route name 判定 active，避免 null route 造成錯誤。
 */
const isRouteActive = (routeName) => {
    if (!routeName) return false;
    if (typeof route !== 'function') return false;
    return route().has(routeName) && route().current(routeName);
};

const resolveHref = (item) => {
    if (!item?.route) return '#';
    if (typeof route === 'function' && route().has(item.route)) {
        return route(item.route);
    }
    return '#';
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
    const paddingLeftClass = level > 0 ? 'pl-9' : '';

    if (item.route) {
        return (
            <div className="space-y-2">
                <Link
                    href={resolveHref(item)}
                    onClick={onClose}
                    className={`${itemBaseClass} ${paddingLeftClass} ${
                        active
                            ? 'bg-cyan-300/10 text-cyan-100 shadow-[inset_3px_0_0_rgba(103,232,249,0.85)]'
                            : 'text-zinc-400 hover:bg-white/[0.04] hover:text-zinc-100'
                    }`}
                >
                    <span className={active ? 'text-cyan-200' : 'text-zinc-500 group-hover:text-cyan-300'}>{iconMap[item.icon] ?? iconMap.dashboard}</span>
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
                    active ? 'bg-cyan-300/10 text-cyan-100 shadow-[inset_3px_0_0_rgba(103,232,249,0.85)]' : 'text-zinc-400'
                }`}
            >
                <span className={active ? 'text-cyan-200' : 'text-zinc-500'}>{iconMap[item.icon] ?? iconMap.dashboard}</span>
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
                className={`relative flex h-full w-[290px] max-w-[86vw] flex-col overflow-hidden border-r border-white/10 bg-[#0B1120] shadow-2xl transition-transform duration-200 ease-out ${
                    open ? 'translate-x-0' : '-translate-x-full'
                }`}
            >
                {/* 行動版品牌區固定 88px，讓抽屜分線與 Header 下緣維持同一水平基準。 */}
                <div className="flex h-[88px] shrink-0 items-center justify-between border-b border-white/10 px-5">
                    <div className="flex items-center gap-3">
                        <div className="grid h-10 w-10 place-items-center rounded-xl border border-cyan-300/30 bg-cyan-300/10 text-sm font-semibold text-cyan-100">
                            ERP
                        </div>
                        <div>
                            <p className="text-[10px] font-semibold uppercase tracking-[0.34em] text-cyan-300/70">Used Car</p>
                            <p className="mt-1 text-sm font-semibold tracking-wide text-zinc-100">ERP Dashboard</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="grid h-10 w-10 place-items-center rounded-xl border border-white/10 text-zinc-300 active:scale-[0.98]"
                        aria-label="Close sidebar"
                    >
                        <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8">
                            <path d="M6 6l12 12M18 6 6 18" strokeLinecap="round" />
                        </svg>
                    </button>
                </div>

                <nav className="flex-1 overflow-y-auto px-4 py-6">
                    <div className="mb-4 px-3 text-[10px] font-semibold uppercase leading-5 tracking-[0.28em] text-zinc-500">Menu</div>
                    <div className="flex flex-col gap-2">
                        {items.map((item) => (
                            <MobileSidebarNode key={item.id} item={item} onClose={onClose} />
                        ))}
                    </div>
                </nav>
            </aside>
        </div>
    );
}
