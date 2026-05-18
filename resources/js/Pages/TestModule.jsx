import DashboardLayout from '@/Layouts/DashboardLayout';

export default function TestModule() {
    return (
        <DashboardLayout title="測試模塊">
            {/* 技術註解：此頁僅作為 admin RBAC 可達性的最小驗證頁，不承載 CRUD 功能。 */}
            <section className="rounded-2xl border border-default bg-surface/70 p-6 backdrop-blur-xl">
                <p className="text-[11px] font-semibold uppercase tracking-[0.28em] text-accent">RBAC Checkpoint</p>
                <h1 className="mt-3 text-2xl font-semibold tracking-tight text-primary">測試模塊</h1>
                <p className="mt-4 max-w-2xl text-sm leading-6 text-secondary">
                    這是最小 Spatie RBAC 地基的權限驗證頁；只有 admin 角色可透過模組權限進入。
                </p>
            </section>
        </DashboardLayout>
    );
}
