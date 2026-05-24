import React from 'react';
import { Link } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';

/**
 * 技術註解：維持極簡列表頁，僅呈現 Read Foundation Slice 必要欄位，避免提前引入大型 UI 或進階互動。
 */
export default function VehiclesIndex({ auth, vehicles = [], lifecycleStatuses = {}, can = {} }) {
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

                <div className="overflow-x-auto rounded-2xl border border-default bg-surface">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="border-b border-default text-left text-secondary">
                                <th className="px-4 py-3">庫存編號</th>
                                <th className="px-4 py-3">品牌</th>
                                <th className="px-4 py-3">車型</th>
                                <th className="px-4 py-3">年份</th>
                                <th className="px-4 py-3">狀態</th>
                                <th className="px-4 py-3">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            {vehicles.map((vehicle) => (
                                <tr key={vehicle.id} className="border-b border-default/70 text-primary last:border-b-0">
                                    <td className="px-4 py-3">{vehicle.stock_number}</td>
                                    <td className="px-4 py-3">{vehicle.brand}</td>
                                    <td className="px-4 py-3">{vehicle.model}</td>
                                    <td className="px-4 py-3 text-secondary">{vehicle.model_year ?? '-'}</td>
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
                        </tbody>
                    </table>
                </div>
            </div>
        </DashboardLayout>
    );
}
