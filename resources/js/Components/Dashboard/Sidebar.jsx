import { Link } from '@inertiajs/react';
import { useMemo } from 'react';
import { sidebarItems } from '@/config/sidebar.ts';

const shellClass = 'hidden shrink-0 flex-col overflow-hidden border-r border-white/10 bg-[#0B1120]/95 backdrop-blur-xl lg:flex';
const itemBaseClass = 'group relative flex min-h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium tracking-wide transition-all duration-150';

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

const isRouteActive = (routeName) => {
    if (!routeName) return false;
    if (typeof route !== 'function') return false;
    return route().has(routeName) && route().current(routeName);
};

const resolveHref = (item) => {
    if (item?.href) return item.href;
    if (!item?.routeName) return '#';
    if (typeof route === 'function' && route().has(item.routeName)) {
        return route(item.routeName);
    }
    return '#';
};

const hasActiveChild = (children = []) => {
    return children.some((child) => isItemActive(child));
};

const isItemActive = (item) => {
    return isRouteActive(item?.routeName) || hasActiveChild(item?.children ?? []);
};

const SidebarNode = ({ item, collapsed = false, level = 0 }) => {
    const active = isItemActive(item);
    const icon = iconMap[item.icon] ?? iconMap.dashboard;
    const hasChildren = (item.children ?? []).length > 0;

    const paddingLeftClass = level > 0 && !collapsed ? 'pl-9' : '';

    if (item.routeName || item.href) {
        return (
            <div className="space-y-2">
                <Link
                    href={resolveHref(item)}
                    className={`${itemBaseClass} ${collapsed ? 'justify-center px-0' : paddingLeftClass} ${
                        active
                            ? 'bg-cyan-300/10 text-cyan-100 shadow-[inset_3px_0_0_rgba(103,232,249,0.85)]'
                            : 'text-zinc-400 hover:bg-white/[0.04] hover:text-zinc-100'
                    }`}
                    title={collapsed ? item.label : undefined}
                >
                    <span className={active ? 'text-cyan-200' : 'text-zinc-500 group-hover:text-cyan-300'}>{icon}</span>
                    {!collapsed && <span className="truncate">{item.label}</span>}
                </Link>

                {!collapsed && hasChildren && (
                    <div className="space-y-2">
                        {item.children.map((child) => (
                            <SidebarNode key={child.key} item={child} level={level + 1} collapsed={collapsed} />
                        ))}
                    </div>
                )}
            </div>
        );
    }

    return (
        <div className="space-y-2">
            <div
                className={`${itemBaseClass} ${collapsed ? 'justify-center px-0' : paddingLeftClass} ${
                    active ? 'bg-cyan-300/10 text-cyan-100 shadow-[inset_3px_0_0_rgba(103,232,249,0.85)]' : 'text-zinc-400'
                }`}
                title={collapsed ? item.label : undefined}
            >
                <span className={active ? 'text-cyan-200' : 'text-zinc-500'}>{icon}</span>
                {!collapsed && <span className="truncate">{item.label}</span>}
            </div>

            {!collapsed && hasChildren && (
                <div className="space-y-2">
                    {item.children.map((child) => (
                        <SidebarNode key={child.key} item={child} level={level + 1} collapsed={collapsed} />
                    ))}
                </div>
            )}
        </div>
    );
};

export default function Sidebar({ items = sidebarItems, collapsed = false, pinned = false, onMouseEnter, onMouseLeave }) {
    const widthClass = useMemo(() => (collapsed ? 'w-[90px]' : 'w-[290px]'), [collapsed]);

    return (
        <aside
            className={`${shellClass} ${widthClass} transition-[width] duration-200 ease-out`}
            onMouseEnter={onMouseEnter}
            onMouseLeave={onMouseLeave}
            data-sidebar-pinned={pinned ? 'true' : 'false'}
        >
            <div className={`flex h-[88px] items-center px-5 ${collapsed ? 'justify-center' : 'justify-between'}`}>
                <div className="flex items-center gap-3">
                    <div className="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-cyan-300/30 bg-cyan-300/10 text-sm font-semibold text-cyan-100">
                        ERP
                    </div>
                    {!collapsed && (
                        <div>
                            <p className="text-[10px] font-semibold uppercase tracking-[0.34em] text-cyan-300/70">Used Car</p>
                            <p className="mt-1 text-sm font-semibold tracking-wide text-zinc-100">ERP Dashboard</p>
                        </div>
                    )}
                </div>
            </div>

            <nav className="flex-1 overflow-y-auto px-4 py-6">
                <div className="mb-4 px-3 text-[10px] font-semibold uppercase leading-5 tracking-[0.28em] text-zinc-500">
                    {collapsed ? '•••' : 'Menu'}
                </div>
                <div className="flex flex-col gap-2">
                    {items.map((item) => (
                        <SidebarNode key={item.key} item={item} collapsed={collapsed} />
                    ))}
                </div>
            </nav>
        </aside>
    );
}
