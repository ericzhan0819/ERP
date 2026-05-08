import Header from '@/Components/Dashboard/Header';
import MobileSidebar from '@/Components/Dashboard/MobileSidebar';
import Sidebar from '@/Components/Dashboard/Sidebar';
import { sidebarItems } from '@/config/sidebar.ts';
import usePermission from '@/hooks/usePermission';
import { filterSidebarByPermission } from '@/utils/permission';
import { Head, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

export default function DashboardLayout({ title = 'Dashboard', children }) {
    const page = usePage();
    const user = page.props?.auth?.user ?? null;
    const currentUrl = page.url;
    const { role, permissions, modules } = usePermission();

    /**
     * 以容錯方式統整權限來源，避免後端欄位命名不同時出錯。
     */
    const visibleSidebarItems = useMemo(
        () => filterSidebarByPermission(sidebarItems, permissions, role, modules),
        [permissions, role, modules],
    );

    const [mobileOpen, setMobileOpen] = useState(false);
    /**
     * 桌面側欄收合狀態：僅影響桌面版顯示，不干擾行動版抽屜。
     */
    const [sidebarCollapsed, setSidebarCollapsed] = useState(false);

    /**
     * 路由切換時自動關閉行動版側欄，避免導頁後殘留開啟狀態。
     */
    useEffect(() => {
        setMobileOpen(false);
    }, [currentUrl]);

    return (
        <div
            className="min-h-screen w-full overflow-x-hidden text-zinc-100"
            style={{
                background:
                    'radial-gradient(circle at top left, rgba(34,211,238,0.06), transparent 28%), radial-gradient(circle at bottom right, rgba(168,85,247,0.06), transparent 35%), linear-gradient(135deg, #050816 0%, #0B1120 45%, #111827 100%)',
            }}
        >
            <Head title={title} />

            <div className="flex min-h-screen">
                <Sidebar
                    items={visibleSidebarItems}
                    collapsed={sidebarCollapsed}
                    onToggleCollapse={() => setSidebarCollapsed((prev) => !prev)}
                />

                <div className="flex min-h-screen min-w-0 flex-1 flex-col overflow-x-hidden">
                    <Header user={user} onOpenMobileSidebar={() => setMobileOpen(true)} />

                    <main className="flex-1 p-4 sm:p-6 lg:p-8">
                        <div className="mx-auto w-full max-w-7xl">{children}</div>
                    </main>
                </div>
            </div>

            <MobileSidebar
                open={mobileOpen}
                onClose={() => setMobileOpen(false)}
                items={visibleSidebarItems}
            />
        </div>
    );
}
