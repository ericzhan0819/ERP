import React from 'react';
import { Link, router } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';

/**
 * 技術註解：維持極簡列表頁，僅呈現 Read Foundation Slice 必要欄位，避免提前引入大型 UI 或進階互動。
 */
export default function VehiclesIndex({ auth, vehicles = { data: [], links: [] }, filters = {}, lifecycleStatuses = {}, can = {} }) {
    const [search, setSearch] = React.useState(filters.search ?? '');
    const [lifecycleStatus, setLifecycleStatus] = React.useState(filters.lifecycle_status ?? '');

    const handleSearch = (event) => {
        event.preventDefault();

        router.get(route('employee-system.vehicles.index'), {
            search,
            lifecycle_status: lifecycleStatus,
        }, {
            preserveState: true,
            replace: true,
        });
    };

    const handleClear = () => {
        setSearch('');
        setLifecycleStatus('');

        router.get(route('employee-system.vehicles.index'), {}, {
            preserveState: true,
            replace: true,
        });
    };

    /**
     * 技術註解：價格欄位僅依後端下發的 can 權限旗標控制，避免前端自行推導角色造成授權判斷漂移。
     */
    const canViewVehiclePricing = can.view_vehicle_pricing === true;

    return (
        <DashboardLayout user={auth.user}>
            <div className="space-y-4 p-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold text-primary">車輛列表</h1>
                    {can.create_vehicle && (
                        <Link
                            href={route('employee-system.vehicles.create')}
                            className="rounded-lg bg-accent px-4 py-2 text-sm font-medium text-white"
                        >
                            新增車輛
                        </Link>
                    )}
                </div>

                <form onSubmit={handleSearch} className="rounded-2xl border border-default bg-surface p-4">
                    <div className="grid gap-3 md:grid-cols-3">
                        <input
                            type="text"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="搜尋庫存編號 / VIN / 車牌 / 品牌 / 車型"
                            className="rounded-lg border border-default bg-surface px-3 py-2 text-sm text-primary placeholder:text-muted focus:border-accent focus:outline-none"
                        />

                        <select
                            value={lifecycleStatus}
                            onChange={(event) => setLifecycleStatus(event.target.value)}
                            className="rounded-lg border border-default bg-surface px-3 py-2 text-sm text-primary focus:border-accent focus:outline-none"
                        >
                            <option value="">全部狀態</option>
                            {Object.entries(lifecycleStatuses).map(([value, label]) => (
                                <option key={value} value={value}>{label}</option>
                            ))}
                        </select>

                        <div className="flex items-center gap-2">
                            <button
                                type="submit"
                                className="rounded-lg bg-accent px-4 py-2 text-sm font-medium text-white"
                            >
                                查詢
                            </button>
                            <button
                                type="button"
                                onClick={handleClear}
                                className="rounded-lg border border-default px-4 py-2 text-sm font-medium text-secondary"
                            >
                                清除篩選
                            </button>
                        </div>
                    </div>
                </form>

                <div className="overflow-x-auto rounded-2xl border border-default bg-surface">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="border-b border-default text-left text-secondary">
                                <th className="px-4 py-3">庫存編號</th>
                                <th className="px-4 py-3">品牌</th>
                                <th className="px-4 py-3">車型</th>
                                <th className="px-4 py-3">年份</th>
                                {canViewVehiclePricing && <th className="px-4 py-3">開價</th>}
                                {canViewVehiclePricing && <th className="px-4 py-3">底價</th>}
                                <th className="px-4 py-3">狀態</th>
                                <th className="px-4 py-3">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            {vehicles.data.map((vehicle) => (
                                <tr key={vehicle.id} className="border-b border-default/70 text-primary last:border-b-0">
                                    <td className="px-4 py-3">{vehicle.stock_number}</td>
                                    <td className="px-4 py-3">{vehicle.brand}</td>
                                    <td className="px-4 py-3">{vehicle.model}</td>
                                    <td className="px-4 py-3 text-secondary">{vehicle.model_year ?? '-'}</td>
                                    {canViewVehiclePricing && <td className="px-4 py-3 text-secondary">{vehicle.asking_price ?? '-'}</td>}
                                    {canViewVehiclePricing && <td className="px-4 py-3 text-secondary">{vehicle.floor_price ?? '-'}</td>}
                                    <td className="px-4 py-3 text-secondary">{lifecycleStatuses[vehicle.lifecycle_status] ?? vehicle.lifecycle_status}</td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-3">
                                            <Link href={route('employee-system.vehicles.show', vehicle.id)} className="text-accent underline decoration-1 underline-offset-2">查看</Link>
                                            {can.update_vehicle && (
                                                <Link href={route('employee-system.vehicles.edit', vehicle.id)} className="text-accent underline decoration-1 underline-offset-2">編輯</Link>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {vehicles.data.length === 0 && (
                                <tr>
                                    <td colSpan={canViewVehiclePricing ? 8 : 6} className="px-4 py-8 text-center text-muted">
                                        無符合條件的車輛資料
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    {vehicles.links.map((link, index) => (
                        <Link
                            // 技術註解：pagination links 由後端輸出，前端僅做安全渲染與導頁，避免自組 URL 造成參數不一致。
                            key={`${link.url ?? 'null'}-${index}`}
                            href={link.url ?? '#'}
                            className={`rounded-md border px-3 py-1.5 text-sm ${link.active
                                ? 'border-accent text-accent'
                                : 'border-default text-secondary'} ${link.url === null ? 'pointer-events-none opacity-50' : ''}`}
                        >
                            {link.label
                                .replace('&laquo; Previous', '上一頁')
                                .replace('Next &raquo;', '下一頁')}
                        </Link>
                    ))}
                </div>
            </div>
        </DashboardLayout>
    );
}
