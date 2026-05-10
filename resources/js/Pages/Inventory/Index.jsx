import DashboardLayout from '@/Layouts/DashboardLayout';

/**
 * Inventory 最小可運作頁面：
 * - 供 Sidebar 導航使用，避免 404。
 * - 保持 Dashboard persistent layout 架構。
 */
export default function InventoryIndex() {
    return (
        <section className="rounded-2xl border border-white/10 bg-white/[0.03] p-4 sm:p-5 backdrop-blur-xl">
            <h2 className="text-base font-semibold text-zinc-100">Inventory</h2>
            <p className="mt-1 text-sm text-zinc-400">庫存頁面已接入 Inertia，僅切換 main 內容區塊。</p>
        </section>
    );
}

InventoryIndex.layout = (page) => <DashboardLayout title="Inventory">{page}</DashboardLayout>;

