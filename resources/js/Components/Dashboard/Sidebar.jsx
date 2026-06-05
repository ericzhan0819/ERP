import { Link, usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import { SIDEBAR_DEFAULT_ICON_KEY } from '@/config/sidebar.ts';

const shellClass = 'hidden shrink-0 flex-col overflow-hidden border-r border-default bg-surface/95 backdrop-blur-xl lg:flex';
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
    'test-module': (
        <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8">
            <path d="M4 7h16M7 4v16M17 4v16M4 17h16" strokeLinecap="round" />
        </svg>
    ),
};

const resolveSectionedModules = (visibleModules) => (Array.isArray(visibleModules) ? visibleModules : []);

const isRouteActive = (patterns = []) => {
    if (typeof route !== 'function') return false;
    return patterns.some((pattern) => route().current(pattern));
};

const resolveHref = (item) => {
    if (item?.href) return item.href;
    if (!item?.route_name) return '#';
    if (typeof route === 'function' && route().has(item.route_name)) {
        return route(item.route_name);
    }
    return '#';
};

const hasActiveChild = (children = []) => {
    return children.some((child) => isItemActive(child));
};

const isItemActive = (item) => {
    return isRouteActive(item?.active ?? []) || hasActiveChild(item?.children ?? []);
};

const SidebarNode = ({ item, collapsed = false, level = 0 }) => {
    const active = isItemActive(item);
    const iconKey = item.icon_key ?? item.icon;
    const icon = iconMap[iconKey] ?? iconMap[SIDEBAR_DEFAULT_ICON_KEY];
    const hasChildren = (item.children ?? []).length > 0;

    const paddingLeftClass = level > 0 && !collapsed ? 'pl-9' : '';

    if (item.route_name || item.href) {
        return (
            <div className="space-y-2">
                <Link
                    href={resolveHref(item)}
                    className={`${itemBaseClass} ${collapsed ? 'justify-center px-0' : paddingLeftClass} ${
                        active
                            ? 'bg-active text-accent border-l-2 border-active'
                            : 'text-muted hover:bg-hover hover:text-primary'
                     }`}
                    title={collapsed ? item.label : undefined}
                >
                    <span className={active ? 'text-accent' : 'text-muted group-hover:text-accent'}>{icon}</span>
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
                    active ? 'bg-active text-accent border-l-2 border-active' : 'text-muted'
                }`}
                title={collapsed ? item.label : undefined}
            >
                <span className={active ? 'text-accent' : 'text-muted'}>{icon}</span>
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

export default function Sidebar({ collapsed = false, pinned = false, onMouseEnter, onMouseLeave }) {
    const { auth, brand } = usePage().props;
    const widthClass = useMemo(() => (collapsed ? 'w-[90px]' : 'w-[290px]'), [collapsed]);
    const visibleSections = useMemo(() => {
        return resolveSectionedModules(auth?.visibleModules ?? []);
    }, [auth?.visibleModules]);
    const companyCode = brand?.code ?? 'OO';
    const brandName = brand?.brand_name;
    const brandNameEn = brand?.brand_name_en;
    const brandSubtitle = brand?.brand_subtitle;
    const brandSecondary = brandNameEn || brandSubtitle;

    return (
        <aside
            className={`${shellClass} ${widthClass} transition-[width] duration-200 ease-out`}
            onMouseEnter={onMouseEnter}
            onMouseLeave={onMouseLeave}
            data-sidebar-pinned={pinned ? 'true' : 'false'}
        >
            <div className={`flex h-[88px] items-center px-5 ${collapsed ? 'justify-center' : 'justify-between'}`}>
                <div className="flex items-center gap-3">
                    <div className="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-active bg-accent-subtle text-sm font-semibold text-accent">
                        {companyCode}
                    </div>
                    {!collapsed && (
                        <div>
                            <p className="text-[10px] font-semibold uppercase tracking-[0.34em] text-accent/80">{brandName}</p>
                            <p className="mt-1 text-sm font-semibold tracking-wide text-primary">{brandSecondary}</p>
                        </div>
                    )}
                </div>
            </div>

            <nav className="flex-1 overflow-y-auto px-4 py-6">
                <div className="mb-4 px-3 text-[10px] font-semibold uppercase leading-5 tracking-[0.28em] text-muted">
                    {collapsed ? '•••' : 'Menu'}
                </div>
                <div className="flex flex-col gap-4">
                    {visibleSections.map((section) => (
                        <section key={section.section}>
                            {!collapsed && (
                                <div className="mb-2 px-3 text-[10px] font-semibold uppercase tracking-[0.24em] text-muted">{section.section}</div>
                            )}
                            <div className="flex flex-col gap-2">
                                {(section.items ?? []).map((item) => (
                                    <SidebarNode key={item.key} item={item} collapsed={collapsed} />
                                ))}
                            </div>
                        </section>
                    ))}
                </div>
            </nav>
        </aside>
    );
}
