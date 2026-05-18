import Header from '@/Components/Dashboard/Header';
import MobileSidebar from '@/Components/Dashboard/MobileSidebar';
import Sidebar from '@/Components/Dashboard/Sidebar';
import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';

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
    const [theme, setTheme] = useState('light');

    useEffect(() => {
        const savedTheme = window.localStorage.getItem('erp-theme');
        const nextTheme = savedTheme === 'dark' ? 'dark' : 'light';
        // 技術註解：以 data-theme 作為唯一主題切換入口，避免樣式判斷散落造成維護風險。
        document.documentElement.dataset.theme = nextTheme;
        setTheme(nextTheme);
    }, []);

    const toggleTheme = () => {
        const nextTheme = theme === 'dark' ? 'light' : 'dark';
        document.documentElement.dataset.theme = nextTheme;
        window.localStorage.setItem('erp-theme', nextTheme);
        setTheme(nextTheme);
    };

    return (
        <div className="min-h-screen w-full overflow-x-hidden bg-app text-primary antialiased">
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
                        theme={theme}
                        onToggleTheme={toggleTheme}
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
