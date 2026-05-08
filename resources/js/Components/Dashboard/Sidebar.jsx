import { Link } from '@inertiajs/react';
import { useMemo } from 'react';

const shellClass = 'hidden shrink-0 border-r border-white/10 bg-[#0B1120]/90 backdrop-blur-xl lg:flex lg:flex-col';
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

const isRouteActive = (routeName) => {
    if (!routeName) return false;
    return route().current(routeName);
};

const hasActiveChild = (children = []) => {
    return children.some((child) => isItemActive(child));
};

const isItemActive = (item) => {
    return isRouteActive(item?.route) || hasActiveChild(item?.children ?? []);
};

const SidebarNode = ({ item, collapsed = false, level = 0 }) => {
    const active = isItemActive(item);
    const icon = iconMap[item.icon] ?? iconMap.dashboard;
    const hasChildren = (item.children ?? []).length > 0;

    const paddingLeftClass = level > 0 ? 'pl-6' : '';

    if (item.route) {
        return (
            <div className="space-y-2">
                <Link
                    href={route(item.route)}
                    className={`${itemBaseClass} ${paddingLeftClass} ${
                        active
                            ? 'border-cyan-300/40 bg-cyan-300/10 text-cyan-200'
                            : 'border-white/10 bg-white/[0.02] text-zinc-300 hover:border-cyan-300/30 hover:text-cyan-200'
                    }`}
                    title={collapsed ? item.title : undefined}
                >
                    <span className="text-cyan-300/90">{icon}</span>
                    {!collapsed && <span>{item.title}</span>}
                </Link>

                {!collapsed && hasChildren && (
                    <div className="space-y-2">
                        {item.children.map((child) => (
                            <SidebarNode key={child.id} item={child} level={level + 1} collapsed={collapsed} />
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
                title={collapsed ? item.title : undefined}
            >
                <span className="text-cyan-300/90">{icon}</span>
                {!collapsed && <span>{item.title}</span>}
            </div>

            {!collapsed && hasChildren && (
                <div className="space-y-2">
                    {item.children.map((child) => (
                        <SidebarNode key={child.id} item={child} level={level + 1} collapsed={collapsed} />
                    ))}
                </div>
            )}
        </div>
    );
};

export default function Sidebar({ items = [], collapsed = false, onToggleCollapse }) {
    const widthClass = useMemo(() => (collapsed ? 'w-20' : 'w-64'), [collapsed]);

    return (
        <aside className={`${shellClass} ${widthClass}`}>
            <div className="flex items-center justify-between border-b border-white/10 px-5 py-5">
                {!collapsed && <p className="text-[10px] font-semibold uppercase tracking-[0.35em] text-cyan-300/70">ERP Dashboard</p>}
                <button
                    type="button"
                    onClick={onToggleCollapse}
                    className="rounded-md border border-white/15 px-2 py-1 text-xs text-zinc-300"
                    aria-label="Toggle sidebar collapse"
                >
                    {collapsed ? '展開' : '收合'}
                </button>
            </div>

            <nav className="space-y-2 p-4">
                {items.map((item) => (
                    <SidebarNode key={item.id} item={item} collapsed={collapsed} />
                ))}
            </nav>
        </aside>
    );
}
