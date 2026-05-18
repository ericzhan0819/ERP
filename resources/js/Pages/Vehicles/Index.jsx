import React from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';

/**
 * 技術註解：維持極簡列表頁，僅呈現 Read Foundation Slice 必要欄位，避免提前引入大型 UI 或進階互動。
 */
export default function VehiclesIndex({ auth, vehicles = [] }) {
    return (
        <DashboardLayout user={auth.user}>
            <div className="space-y-4 p-6">
                <h1 className="text-xl font-semibold text-primary">Vehicles</h1>

                <ul className="space-y-2">
                    {vehicles.map((vehicle) => (
                        <li key={vehicle.id} className="rounded-xl border border-default bg-surface p-3">
                            <a
                                href={route('employee-system.vehicles.show', vehicle.id)}
                                className="text-accent underline decoration-1 underline-offset-2 hover:text-secondary"
                            >
                                {vehicle.stock_number} - {vehicle.brand} {vehicle.model}
                            </a>
                        </li>
                    ))}
                </ul>
            </div>
        </DashboardLayout>
    );
}
