import Header from '@/Components/Dashboard/Header';
import MobileSidebar from '@/Components/Dashboard/MobileSidebar';
import Sidebar from '@/Components/Dashboard/Sidebar';
import { Head } from '@inertiajs/react';
import { useState } from 'react';

export default function DashboardLayout({ title = 'Dashboard', children }) {
    const [mobileOpen, setMobileOpen] = useState(false);
    /**
     * 桌面側欄收合狀態：僅影響桌面版顯示，不干擾行動版抽屜。
     */
    const [sidebarCollapsed, setSidebarCollapsed] = useState(true);
    /**
     * 使用者手動展開後視為固定狀態；未固定時才允許滑鼠靠近自動展開、離開自動收合。
     */
    const [sidebarPinned, setSidebarPinned] = useState(false);

    return (
        <div
            className="min-h-screen w-full overflow-x-hidden text-zinc-100 antialiased"
            style={{
                background:
                    'radial-gradient(circle at top left, rgba(34,211,238,0.06), transparent 28%), radial-gradient(circle at bottom right, rgba(168,85,247,0.06), transparent 35%), linear-gradient(135deg, #050816 0%, #0B1120 45%, #111827 100%)',
            }}
        >
            <Head title={title} />

            <div className="flex h-screen overflow-hidden">
                <Sidebar
                    collapsed={sidebarCollapsed}
                    pinned={sidebarPinned}
                    onMouseEnter={() => {
                        if (!sidebarPinned) setSidebarCollapsed(false);
                    }}
                    onMouseLeave={() => {
                        if (!sidebarPinned) setSidebarCollapsed(true);
                    }}
                />

                <div className="relative flex min-w-0 flex-1 flex-col overflow-x-hidden overflow-y-auto">
                    <Header
                        title={title}
                        sidebarCollapsed={sidebarCollapsed}
                        sidebarPinned={sidebarPinned}
                        onToggleSidebar={() => {
                            const nextPinned = !sidebarPinned;

                            setSidebarPinned(nextPinned);
                            setSidebarCollapsed(!nextPinned);
                        }}
                        onOpenMobileSidebar={() => setMobileOpen(true)}
                    />

                    <main className="flex-1">
                        <div className="mx-auto w-full max-w-screen-2xl p-4 md:p-6">{children}</div>
                    </main>
                </div>
            </div>

            {mobileOpen && (
                <MobileSidebar
                    open={mobileOpen}
                    onClose={() => setMobileOpen(false)}
                />
            )}
        </div>
    );
}
